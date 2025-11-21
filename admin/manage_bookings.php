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

// Handle actions (approve, decline, delete)
if (isset($_GET['action'], $_GET['id'])) {
    $id = intval($_GET['id']);
    $action = $_GET['action'];
    $status = '';

    if ($action === 'approve') $status = 'approved';
    elseif ($action === 'decline') $status = 'declined';
    elseif ($action === 'delete') {
        $conn->query("DELETE FROM bookings WHERE id=$id");
        header("Location: manage_bookings.php");
        exit;
    }

    if ($status) {
        $conn->query("UPDATE bookings SET status='$status' WHERE id=$id");
        header("Location: manage_bookings.php");
        exit;
    }
}

// Fetch all bookings
$query = "
SELECT b.id, b.booking_date, b.status, b.payment_method, b.gcash_receipt, b.remarks,
       u.fullname AS user_name, d.fullname AS diver_name
FROM bookings b
LEFT JOIN users u ON b.user_id = u.id
LEFT JOIN divers d ON b.diver_id = d.id
ORDER BY b.created_at DESC";
$bookings = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Bookings | DiveConnect</title>
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
        <a href="manage_bookings.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg bg-blue-100 text-blue-700 font-semibold shadow-sm"><i class="ri-calendar-check-line text-xl"></i><span>Bookings</span></a>
        <a href="payments.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50"><i class="ri-bank-card-line text-xl"></i><span>Payments</span></a>
        <a href="manage_destinations.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50 transition-colors">
          <i class="ri-map-pin-line text-xl"></i>
          <span>Destinations</span>
        </a>
      </nav>
    </div>
  </aside>

  <!-- MAIN CONTENT -->
  <main class="md:col-span-9 space-y-6">
    <div class="bg-white rounded-lg shadow p-6">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4">
        <h2 class="text-2xl font-bold text-blue-700 flex items-center gap-2"><i class="ri-calendar-check-line"></i> Manage Bookings</h2>
        <input id="searchInput" type="text" placeholder="🔍 Search by diver or user..."
          class="border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-400 w-full sm:w-64 mt-3 sm:mt-0">
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full text-sm text-left border" id="bookingsTable">
          <thead class="bg-blue-50 text-blue-700">
            <tr>
              <th class="px-3 py-2 border text-center">#</th>
              <th class="px-3 py-2 border">User</th>
              <th class="px-3 py-2 border">Diver</th>
              <th class="px-3 py-2 border">Date</th>
              <th class="px-3 py-2 border">Payment</th>
              <th class="px-3 py-2 border text-center">Status</th>
              <th class="px-3 py-2 border text-center">Receipt</th>
              <th class="px-3 py-2 border">Remarks</th>
              <th class="px-3 py-2 border text-center">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <?php if ($bookings && $bookings->num_rows > 0): ?>
              <?php while ($row = $bookings->fetch_assoc()): ?>
                <tr class="hover:bg-gray-50">
                  <td class="px-3 py-2 text-center font-medium"><?= $row['id'] ?></td>
                  <td class="px-3 py-2"><?= htmlspecialchars($row['user_name']) ?></td>
                  <td class="px-3 py-2"><?= htmlspecialchars($row['diver_name']) ?></td>
                  <td class="px-3 py-2"><?= htmlspecialchars($row['booking_date']) ?></td>
                  <td class="px-3 py-2"><?= htmlspecialchars($row['payment_method']) ?></td>
                  <td class="px-3 py-2 text-center">
                    <span class="px-2 py-1 rounded text-xs font-semibold
                      <?= $row['status'] == 'approved' ? 'bg-green-100 text-green-700' :
                         ($row['status'] == 'declined' ? 'bg-red-100 text-red-700' :
                         'bg-yellow-100 text-yellow-700') ?>">
                      <?= ucfirst($row['status']) ?>
                    </span>
                  </td>
                  <td class="px-3 py-2 text-center">
                    <?php if ($row['gcash_receipt']): ?>
                      <a href="../user/uploads/<?= htmlspecialchars($row['gcash_receipt']) ?>" target="_blank"
                        class="text-blue-600 hover:underline">View</a>
                    <?php else: ?>
                      <span class="text-gray-400">N/A</span>
                    <?php endif; ?>
                  </td>
                  <td class="px-3 py-2 text-gray-700"><?= htmlspecialchars($row['remarks'] ?? '—') ?></td>
                  <td class="px-3 py-2 text-center">
                    <!-- Desktop Layout -->
                    <div class="hidden md:flex md:flex-wrap md:gap-2 md:justify-center">
                      <?php if ($row['status'] == 'pending'): ?>
                        <a href="?action=approve&id=<?= $row['id'] ?>"
                          class="px-3 py-1 bg-green-500 text-white rounded hover:bg-green-600 text-xs">Approve</a>
                        <a href="?action=decline&id=<?= $row['id'] ?>"
                          class="px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600 text-xs">Decline</a>
                      <?php endif; ?>
                      <a href="?action=delete&id=<?= $row['id'] ?>"
                        onclick="return confirm('Delete this booking?')"
                        class="px-3 py-1 bg-gray-300 text-gray-700 rounded hover:bg-gray-400 text-xs">Delete</a>
                    </div>
                    
                    <!-- Mobile Layout - Icon Buttons -->
                    <div class="flex md:hidden gap-1 justify-center">
                      <?php if ($row['status'] == 'pending'): ?>
                        <a href="?action=approve&id=<?= $row['id'] ?>"
                          class="p-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors shadow-sm"
                          title="Approve">
                          <i class="ri-check-line text-lg"></i>
                        </a>
                        <a href="?action=decline&id=<?= $row['id'] ?>"
                          class="p-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors shadow-sm"
                          title="Decline">
                          <i class="ri-close-line text-lg"></i>
                        </a>
                      <?php endif; ?>
                      <a href="?action=delete&id=<?= $row['id'] ?>"
                        onclick="return confirm('Delete this booking?')"
                        class="p-2 bg-gray-400 text-white rounded-lg hover:bg-gray-500 transition-colors shadow-sm"
                        title="Delete">
                        <i class="ri-delete-bin-line text-lg"></i>
                      </a>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="9" class="px-4 py-6 text-center text-gray-500">No bookings found.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

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

// Search filter
document.getElementById("searchInput").addEventListener("keyup", function () {
  const filter = this.value.toLowerCase();
  const rows = document.querySelectorAll("#bookingsTable tbody tr");
  rows.forEach(row => {
    const text = row.innerText.toLowerCase();
    row.style.display = text.includes(filter) ? "" : "none";
  });
});
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