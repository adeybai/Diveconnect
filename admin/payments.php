<?php
session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
require '../includes/db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

function e($v) { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

// Fetch counts (optional, if needed for stats)
$counts = [];
$tables = ['admins','users','divers','bookings','payments','availability','terms_conditions'];
foreach ($tables as $t) {
    $res = $conn->query("SELECT COUNT(*) FROM $t");
    $counts[$t] = $res ? $res->fetch_row()[0] : 0;
}

// Fetch current admin info (for GCash QR + Amount + VAT)
$admin_id = $_SESSION['admin_id'];
$stmt = $conn->prepare("SELECT fullname, email, gcash_qr, gcash_amount, vat_percent FROM admins WHERE admin_id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

// Handle GCash QR upload, amount, and VAT update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle QR upload
    if (isset($_FILES['gcash_qr']) && $_FILES['gcash_qr']['error'] === 0) {
        $targetDir = "../uploads/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        $filename = uniqid("qr_") . "_" . basename($_FILES['gcash_qr']['name']);
        $targetFile = $targetDir . $filename;
        if (move_uploaded_file($_FILES['gcash_qr']['tmp_name'], $targetFile)) {
            $qrPath = "uploads/" . $filename;
            $admin['gcash_qr'] = $qrPath;
        }
    } else {
        $qrPath = $admin['gcash_qr']; // keep existing if no new upload
    }

    // Handle amount and VAT update
    $gcash_amount = isset($_POST['gcash_amount']) ? floatval($_POST['gcash_amount']) : $admin['gcash_amount'];
    $vat_percent = isset($_POST['vat_percent']) ? floatval($_POST['vat_percent']) : $admin['vat_percent'];

    $update = $conn->prepare("UPDATE admins SET gcash_qr=?, gcash_amount=?, vat_percent=? WHERE admin_id=?");
    $update->bind_param("sdii", $qrPath, $gcash_amount, $vat_percent, $admin_id);
    $update->execute();

    $admin['gcash_amount'] = $gcash_amount;
    $admin['vat_percent'] = $vat_percent;
}

// Fetch payment summary from bookings
$sql = "
SELECT 
 d.id AS diver_id,
 d.fullname AS diver_name,
 COALESCE(SUM(b.grand_total), 0) AS total_earned,
 COUNT(b.id) AS total_bookings
FROM divers d
LEFT JOIN bookings b 
 ON b.diver_id = d.id 
 AND b.status IN('confirmed','completed')
GROUP BY d.id, d.fullname
ORDER BY total_earned DESC
";
$res = $conn->query($sql);
$payments = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

$total = array_sum(array_column($payments, 'total_earned'));

// Fetch master diver registration payments
$regRes = $conn->query("
SELECT id, fullname, email, gcash_receipt, created_at
FROM divers
WHERE gcash_receipt IS NOT NULL
ORDER BY created_at DESC
");
$diverRegs = $regRes ? $regRes->fetch_all(MYSQLI_ASSOC) : [];

$registration_fee = floatval($admin['gcash_amount']);
$total_registration_income = count($diverRegs) * $registration_fee;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Payments | DiveConnect</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon/fonts/remixicon.css">
<style>
  .hide-scrollbar::-webkit-scrollbar { display: none; }
  .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

  @keyframes slideIn { from {transform: translateX(-100%); opacity:0} to{transform:translateX(0);opacity:1} }
  .menu-enter { animation: slideIn 0.3s ease-out; }
</style>
</head>
<body class="flex flex-col min-h-screen bg-gray-100 font-sans">

<!-- HEADER -->
<header class="bg-blue-700 text-white shadow sticky top-0 z-50">
  <div class="container mx-auto flex justify-between items-center p-3 sm:p-4">
    <div class="flex items-center gap-2 sm:gap-3">
      <button id="menuToggle" class="md:hidden p-2 hover:bg-blue-600 rounded-lg transition-colors">
        <i class="ri-menu-line text-2xl"></i>
      </button>
      <img src="../assets/images/GROUP_4_-_DIVE_CONNECT-removebg-preview.png" alt="Logo" class="h-8 sm:h-10">
      <span class="hidden sm:inline text-lg font-semibold">DiveConnect</span>
    </div>
    <button id="logoutBtn" class="bg-red-500 hover:bg-red-600 px-3 sm:px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-md hover:shadow-lg">
      <i class="ri-logout-box-line mr-1"></i>
      <span class="hidden sm:inline">Logout</span>
      <span class="sm:hidden">Exit</span>
    </button>
    <form id="logoutForm" action="../index.php" method="post" class="hidden"></form>

  </div>
</header>
<!-- Mobile Menu Overlay -->
<div id="menuOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden md:hidden backdrop-blur-sm" onclick="closeMenu()"></div>

<div class="container mx-auto grid grid-cols-1 md:grid-cols-12 gap-4 sm:gap-6 mt-4 sm:mt-6 px-3 sm:px-4 pb-6">

  <!-- SIDEBAR -->
  <aside id="sidebar" class="md:col-span-3 bg-white rounded-lg shadow-xl fixed md:sticky top-0 left-0 h-full md:h-screen w-72 md:w-auto transform -translate-x-full md:translate-x-0 transition-transform duration-300 z-50 md:z-auto overflow-y-auto hide-scrollbar">
    <div class="md:hidden flex items-center justify-between p-4 border-b border-gray-200 bg-blue-50">
      <h3 class="text-lg font-bold text-blue-700">Admin Menu</h3>
      <button onclick="closeMenu()" class="p-2 hover:bg-blue-100 rounded-lg transition-colors">
        <i class="ri-close-line text-2xl text-gray-700"></i>
      </button>
    </div>
    <div class="p-4 sm:p-6">
      <h3 class="hidden md:block text-lg font-semibold text-blue-700 mb-4">Admin Menu</h3>
      <nav class="space-y-1.5 sm:space-y-2">
        <a href="index.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50"><i class="ri-home-4-line text-xl"></i><span>Home</span></a>
        <a href="admin_dashboard.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50"><i class="ri-dashboard-line text-xl"></i><span>Dashboard</span></a>
        <a href="manage_admins.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50"><i class="ri-user-settings-line text-xl"></i><span>Manage Admins</span></a>
        <a href="manage_divers.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50"><i class="ri-user-star-line text-xl"></i><span>Manage Dive Master</span></a>
        <a href="verify_divers.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50"><i class="ri-shield-check-line text-xl"></i><span>Verify Master Divers</span></a>
        <a href="manage_user_divers.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50"><i class="ri-user-line text-xl"></i><span>Manage User Divers</span></a>
        <a href="verify_user_divers.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50"><i class="ri-user-shared-line text-xl"></i><span>Verify User Divers</span></a>
        <a href="manage_bookings.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50"><i class="ri-calendar-check-line text-xl"></i><span>Bookings</span></a>
        <a href="payments.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg bg-blue-100 text-blue-700 font-semibold shadow-sm"><i class="ri-bank-card-line text-xl"></i><span>Payments</span></a>
        <a href="manage_destinations.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50 transition-colors">
          <i class="ri-map-pin-line text-xl"></i>
          <span>Destinations</span>
        </a>
      </nav>
    </div>
  </aside>

  <!-- MAIN CONTENT -->
  <main class="md:col-span-9 space-y-6">

    <!-- TITLE + TOTAL + QR BUTTON -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4">
      <div class="flex flex-col gap-2">
        <h2 class="text-2xl font-bold text-blue-700 flex items-center gap-2"><i class="ri-bank-card-line"></i> Payments Overview</h2>
        <button onclick="document.getElementById('qrModal').showModal()" 
                class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded w-fit text-sm shadow">
          📷 <?= $admin['gcash_qr'] ? 'View / Change QR / Amount' : 'Add GCash QR, Amount & VAT' ?>
        </button>
      </div>
      <div class="bg-green-100 text-green-800 px-4 py-2 rounded mt-3 sm:mt-0 shadow">
        <strong>Total Collected (Bookings):</strong> ₱<?= number_format($total,2) ?>
      </div>
    </div>

    <!-- BOOKINGS PAYMENT TABLE -->
    <div class="bg-white rounded-lg shadow p-6">
      <h3 class="text-xl font-semibold text-blue-700 mb-4">📊 Divers’ Booking Earnings</h3>
      <?php if (count($payments)): ?>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm text-left border">
            <thead class="bg-blue-50 text-blue-700">
              <tr>
                <th class="px-3 py-2 border">Diver</th>
                <th class="px-3 py-2 border text-right">Bookings</th>
                <th class="px-3 py-2 border text-right">Total Earned (₱)</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($payments as $p): ?>
              <tr class="border-b hover:bg-gray-50">
                <td class="px-3 py-2"><?= e($p['diver_name']) ?></td>
                <td class="px-3 py-2 text-right"><?= e($p['total_bookings']) ?></td>
                <td class="px-3 py-2 text-right font-semibold text-green-700">
                  ₱<?= number_format($p['total_earned'],2) ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="mt-4 bg-green-100 text-green-800 px-4 py-2 rounded shadow text-right">
          <strong>Total Collected:</strong> ₱<?= number_format($total,2) ?>
        </div>
      <?php else: ?>
        <p class="text-gray-600">No booking data yet.</p>
      <?php endif; ?>
    </div>

    <!-- MASTER DIVER REGISTRATION PAYMENTS -->
    <div class="bg-white rounded-lg shadow p-6">
      <h3 class="text-xl font-semibold text-blue-700 mb-4">🧾 Master Diver Registration Payments</h3>
      <?php if (count($diverRegs)): ?>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm text-left border">
            <thead class="bg-blue-50 text-blue-700">
              <tr>
                <th class="px-3 py-2 border">Full Name</th>
                <th class="px-3 py-2 border">Email</th>
                <th class="px-3 py-2 border text-center">GCash Receipt</th>
                <th class="px-3 py-2 border text-center">Date Registered</th>
                <th class="px-3 py-2 border text-right">Amount (₱)</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($diverRegs as $r): ?>
              <tr class="border-b hover:bg-gray-50">
                <td class="px-3 py-2"><?= e($r['fullname']) ?></td>
                <td class="px-3 py-2"><?= e($r['email']) ?></td>
                <td class="px-3 py-2 text-center">
                  <?php if (!empty($r['gcash_receipt'])): ?>
                    <a href="uploads/<?= e($r['gcash_receipt']) ?>" target="_blank" class="text-blue-600 hover:underline">View</a>
                  <?php else: ?>
                    <span class="text-gray-500 italic">N/A</span>
                  <?php endif; ?>
                </td>
                <td class="px-3 py-2 text-center"><?= e(date('Y-m-d', strtotime($r['created_at']))) ?></td>
                <td class="px-3 py-2 text-right font-semibold text-green-700">
                  ₱<?= number_format($registration_fee,2) ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="mt-4 bg-green-100 text-green-800 px-4 py-2 rounded shadow text-right">
          <strong>Total Collected (Registrations):</strong> ₱<?= number_format($total_registration_income,2) ?>
        </div>
      <?php else: ?>
        <p class="text-gray-600">No registered master divers yet.</p>
      <?php endif; ?>
    </div>

  </main>
</div>

<!-- QR MODAL -->
<dialog id="qrModal" class="rounded-lg shadow-lg p-6 w-96">
  <h3 class="text-lg font-semibold text-blue-700 mb-3">Admin GCash Settings</h3>

  <?php if ($admin['gcash_qr']): ?>
    <img src="../<?= e($admin['gcash_qr']) ?>" alt="GCash QR" class="w-56 mx-auto mb-3 rounded shadow">
  <?php else: ?>
    <p class="text-gray-500 mb-3">No QR uploaded yet.</p>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data" class="space-y-3">
    <label class="block text-sm font-medium text-gray-700">Upload new GCash QR:</label>
    <input type="file" name="gcash_qr" accept="image/*" class="w-full border border-gray-300 rounded p-2 text-sm">

    <label class="block text-sm font-medium text-gray-700 mt-2">Master Diver Registration Fee (₱):</label>
    <input type="number" name="gcash_amount" step="0.01" value="<?= e($admin['gcash_amount']) ?>" 
           class="w-full border border-gray-300 rounded p-2 text-sm" placeholder="Enter amount">

    <div class="flex justify-end gap-2 mt-3">
      <button type="button" onclick="document.getElementById('qrModal').close()" 
              class="bg-gray-400 text-white px-4 py-2 rounded">Close</button>
      <button type="submit" 
              class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Save</button>
    </div>
  </form>
</dialog>

<!-- FOOTER -->
<footer class="bg-blue-700 text-white text-center py-4 mt-12">
  <small>&copy; <?= date('Y') ?> DiveConnect. All rights reserved.</small>
</footer>

<script>
const menuToggle = document.getElementById('menuToggle');
const sidebar = document.getElementById('sidebar');
const menuOverlay = document.getElementById('menuOverlay');

function openMenu() {
  sidebar.classList.remove('-translate-x-full');
  sidebar.classList.add('menu-enter');
  menuOverlay.classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}
function closeMenu() {
  sidebar.classList.add('-translate-x-full');
  menuOverlay.classList.add('hidden');
  document.body.style.overflow = '';
}
menuToggle.addEventListener('click', openMenu);
</script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.getElementById('logoutBtn').addEventListener('click', function(e){
    e.preventDefault();
    Swal.fire({
        title: 'Are you sure?',
        text: "You will be logged out.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, logout',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('logoutForm').submit();
        }
    });
});
</script>

</body>
</html>
