<?php
session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
include("../includes/db.php");
require '../library/mailer.php';
use DiveConnect\Mailer;

// ✅ Ensure only admin can access
if (!isset($_SESSION["admin_id"])) {
    header("Location: login_admin.php");
    exit;
}

// ✅ Approve or Reject diver
if (isset($_GET['action']) && isset($_GET['id'])) {
    $diver_id = intval($_GET['id']);
    $status = ($_GET['action'] === 'approve') ? 'approved' : 'rejected';

    // Update diver status
    $stmt = $conn->prepare("UPDATE divers SET verification_status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $diver_id);
    $stmt->execute();

    // Fetch diver info for email
    $diver = $conn->query("SELECT fullname, email FROM divers WHERE id = $diver_id")->fetch_assoc();
    $to = $diver['email'];
    $name = $diver['fullname'];

    // Send email
    try {
        $mailer = new Mailer();
        $mailer->sendVerificationStatus($to, $name, $status);
        $_SESSION['swal_message'] = ($status === 'approved')
            ? "Diver has been successfully approved!"
            : "Diver has been rejected.";
    } catch (Exception $e) {
        $_SESSION['swal_message'] = "Status updated, but email failed to send: " . $e->getMessage();
    }

    header("Location: verify_divers.php");
    exit;
}

// Function to get verification link based on organization
function getVerificationLink($pro_org) {
    $links = [
        'PADI' => 'https://apps.padi.com/scuba-diving/pro-chek/',
        'CMAS' => 'https://cmasdata.org/diver-verification.html',
        'IDEA-WDA' => 'https://www.idea-wda.org/checkpro/check-pro.php',
        'SNSI' => 'https://www.scubasnsi.com/professional-verification/',
        'SSI' => 'https://my.divessi.com/online_diver_check',
        'NAUI' => 'https://www.naui.org/verify-certification/',
        'SDI' => 'https://www.tdisdi.com/cert-search/',
        'NADD' => 'https://mynadd.naddeurope.com/CertificationProfessionalMember'
    ];
    foreach ($links as $org => $link) {
        if (stripos($pro_org, $org) !== false) return $link;
    }
    return null;
}

// Fetch pending divers
$result = $conn->query("SELECT * FROM divers WHERE verification_status = 'pending' ORDER BY specialty ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verify Divers | DiveConnect Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon/fonts/remixicon.css">
<style>
  .hide-scrollbar::-webkit-scrollbar { display: none; }
  .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

  @keyframes slideIn { from {transform: translateX(-100%); opacity:0} to{transform:translateX(0);opacity:1} }
  .menu-enter { animation: slideIn 0.3s ease-out; }

  @keyframes fade-in { from {opacity:0; transform:scale(0.95);} to{opacity:1; transform:scale(1);} }
  .animate-fade-in { animation: fade-in 0.2s ease-out; }
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
        <a href="admin_dashboard.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50"><i class="ri-dashboard-line text-xl"></i><span>Dashboard</span></a>
        <a href="manage_admins.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50"><i class="ri-user-settings-line text-xl"></i><span>Manage Admins</span></a>
        <a href="manage_divers.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50"><i class="ri-user-star-line text-xl"></i><span>Manage Dive Master</span></a>
        <a href="verify_divers.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg bg-blue-100 text-blue-700 font-semibold shadow-sm"><i class="ri-shield-check-line text-xl"></i><span>Verify Dive Master</span></a>
        <a href="manage_user_divers.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50"><i class="ri-user-line text-xl"></i><span>Manage User Divers</span></a>
        <a href="verify_user_divers.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50"><i class="ri-user-shared-line text-xl"></i><span>Verify User Divers</span></a>
        <a href="manage_destinations.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50 transition-colors">
          <i class="ri-map-pin-line text-xl"></i>
          <span>Destinations</span>
        </a>
      </nav>
    </div>
  </aside>

  <!-- MAIN CONTENT -->
  <main class="md:col-span-9">
    <div class="bg-white rounded-lg shadow p-6">
      <h2 class="text-xl font-semibold text-blue-700 mb-4 flex items-center gap-2"><i class="ri-pass-valid-line"></i> Pending Dive Master Verifications</h2>

      <?php if ($result->num_rows > 0): ?>
      <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <?php while ($row = $result->fetch_assoc()): ?>
        <?php
          $profile_pic_path = "uploads/" . $row['profile_pic'];
          $valid_id_path = "uploads/" . $row['valid_id'];
          $qr_code_path = !empty($row['qr_code']) ? "uploads/" . $row['qr_code'] : null;
          $verification_link = getVerificationLink($row['pro_org']);
        ?>
        <div class="bg-white border border-gray-200 rounded-xl shadow hover:shadow-xl transition transform hover:-translate-y-1 overflow-hidden">
          <img src="<?= htmlspecialchars($profile_pic_path); ?>" class="w-full h-40 object-cover" alt="Profile">
          <div class="p-5">
            <h2 class="font-semibold text-lg text-gray-800"><?= htmlspecialchars($row['fullname']); ?></h2>
            <p class="text-gray-500 text-sm mb-2"><?= htmlspecialchars($row['email']); ?></p>
            <p class="text-gray-700"><strong>Specialty:</strong> <?= htmlspecialchars($row['specialty']); ?></p>
            <p class="text-gray-700">
              <strong>Level:</strong> 
              <?php if (!empty($row['level'])): ?>
                <?= htmlspecialchars($row['level']); ?>
              <?php else: ?>
                <span class="text-red-500 italic">Not specified</span>
              <?php endif; ?>
            </p>

            <div class="mt-4">
              <button onclick="openModal(<?= $row['id']; ?>)" class="text-blue-600 hover:underline">View Details</button>
            </div>

            <div class="flex gap-3 mt-6">
              <a href="?action=approve&id=<?= $row['id']; ?>" class="flex-1 bg-green-600 hover:bg-green-700 text-white py-2 rounded-lg text-center font-semibold transition">Approve</a>
              <a href="?action=reject&id=<?= $row['id']; ?>" class="flex-1 bg-red-600 hover:bg-red-700 text-white py-2 rounded-lg text-center font-semibold transition">Reject</a>
            </div>
          </div>
        </div>

        <!-- MODAL -->
        <div id="modal-<?= $row['id']; ?>" class="fixed inset-0 bg-black bg-opacity-50 hidden justify-center items-center z-50">
              <div class="bg-white rounded-2xl shadow-xl w-[90%] max-w-2xl max-h-[90vh] overflow-y-auto relative animate-fade-in scale-95 md:scale-100">

                <div class="bg-blue-700 text-white p-4 flex justify-between items-center">
                  <h2 class="text-lg font-semibold">Diver Details</h2>
                  <button onclick="closeModal(<?= $row['id']; ?>)" class="hover:text-gray-300"><i class="ri-close-line text-2xl"></i></button>
                </div>

                <div class="p-6 space-y-6">
                  <div class="flex flex-col items-center text-center">
                    <img src="<?= htmlspecialchars($profile_pic_path); ?>" class="w-24 h-24 rounded-full object-cover shadow mb-3" alt="Profile">
                    <h3 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($row['fullname']); ?></h3>
                    <p class="text-gray-500"><?= htmlspecialchars($row['email']); ?></p>
                  </div>

                  <!-- PERSONAL INFORMATION -->
                  <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="font-semibold text-gray-800 mb-3 flex items-center gap-2">
                      <i class="ri-user-line"></i> Personal Information
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-700">
                      <div><p class="font-semibold">Full Name</p><p class="bg-white p-2 rounded border"><?= htmlspecialchars($row['fullname']); ?></p></div>
                      <div><p class="font-semibold">Email</p><p class="bg-white p-2 rounded border"><?= htmlspecialchars($row['email']); ?></p></div>
                      <div><p class="font-semibold">WhatsApp Number</p><p class="bg-white p-2 rounded border"><?= htmlspecialchars($row['whatsapp_number']); ?></p></div>
                      <div><p class="font-semibold">Nationality</p><p class="bg-white p-2 rounded border"><?= htmlspecialchars($row['nationality']); ?></p></div>
                      <div><p class="font-semibold">Language</p><p class="bg-white p-2 rounded border"><?= htmlspecialchars($row['language']); ?></p></div>
                    </div>
                  </div>

                  <!-- CERTIFICATION INFORMATION -->
                  <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="font-semibold text-gray-800 mb-3 flex items-center gap-2">
                      <i class="ri-pass-valid-line"></i> Certification Information
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-700">
                      <div><p class="font-semibold">Certifying Agency</p><p class="bg-white p-2 rounded border"><?= htmlspecialchars($row['pro_org']); ?></p></div>
                      <div>
                        <p class="font-semibold">Certification Level</p>
                        <p class="bg-white p-2 rounded border">
                          <?php if (!empty($row['level'])): ?>
                            <?= htmlspecialchars($row['level']); ?>
                          <?php else: ?>
                            <span class="text-red-500 italic">Not specified</span>
                          <?php endif; ?>
                        </p>
                      </div>
                      <div><p class="font-semibold">Diver ID</p><p class="bg-white p-2 rounded border"><?= htmlspecialchars($row['pro_diver_id']); ?></p></div>
                      <div><p class="font-semibold">Specialty</p><p class="bg-white p-2 rounded border"><?= htmlspecialchars($row['specialty']); ?></p></div>
                    </div>
                  </div>

                  <!-- VERIFICATION SECTION -->
                  <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 rounded-lg">
                    <div class="flex items-start gap-3">
                      <i class="ri-shield-check-line text-yellow-600 text-2xl"></i>
                      <div class="flex-1">
                        <p class="font-semibold text-gray-800 mb-1">Pro Diver ID Verification</p>
                        <p class="text-sm text-gray-600 mb-3">
                          <strong>ID:</strong> <?= htmlspecialchars($row['pro_diver_id']); ?><br>
                          <strong>Organization:</strong> <?= htmlspecialchars($row['pro_org']); ?>
                        </p>
                        
                        <?php if ($verification_link): ?>
                          <a href="<?= htmlspecialchars($verification_link); ?>" 
                             target="_blank" 
                             class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition">
                            <i class="ri-external-link-line"></i>
                            Verify on <?= htmlspecialchars($row['pro_org']); ?> Website
                          </a>
                          <p class="text-xs text-gray-500 mt-2">
                            <i class="ri-information-line"></i> Click to verify this ID on the official <?= htmlspecialchars($row['pro_org']); ?> verification portal
                          </p>
                        <?php else: ?>
                          <p class="text-sm text-red-600 italic">
                            <i class="ri-alert-line"></i> No verification link available for this organization
                          </p>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>

                  <!-- UPLOADED DOCUMENTS -->
                  <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="font-semibold text-gray-800 mb-3 flex items-center gap-2">
                      <i class="ri-file-upload-line"></i> Uploaded Documents
                    </h4>
                    
                    <!-- Profile Picture -->
                    <div class="mb-4">
                      <p class="font-semibold text-gray-800 mb-2">Profile Picture</p>
                      <?php if (!empty($row['profile_pic'])): ?>
                        <div class="flex justify-center">
                          <img src="<?= htmlspecialchars($profile_pic_path); ?>" class="w-48 h-48 object-cover rounded-lg border border-gray-200 shadow-sm hover:shadow-md">
                        </div>
                      <?php else: ?>
                        <p class="text-gray-400 italic text-center">No profile picture uploaded</p>
                      <?php endif; ?>
                    </div>

                    <!-- MasterDiver's ID -->
                    <div class="mb-4">
                      <p class="font-semibold text-gray-800 mb-2">MasterDiver's ID</p>
                      <div class="flex justify-center">
                        <img src="<?= htmlspecialchars($valid_id_path); ?>" class="w-64 h-auto rounded-lg border border-gray-200 shadow-sm hover:shadow-md object-contain">
                      </div>
                    </div>

                    <!-- GCash QR Code -->
                    <?php if ($qr_code_path): ?>
                    <div class="mb-4">
                      <p class="font-semibold text-gray-800 mb-2">GCash QR Code</p>
                      <div class="flex justify-center">
                        <img src="<?= htmlspecialchars($qr_code_path); ?>" class="w-48 h-auto rounded-lg border border-gray-200 shadow-sm hover:shadow-md object-contain">
                      </div>
                      <p class="text-xs text-gray-500 text-center mt-2">(Please verify if cropped to actual size)</p>
                    </div>
                    <?php endif; ?>
                  </div>

                  <div class="flex gap-3 mt-4">
                    <a href="?action=approve&id=<?= $row['id']; ?>" class="flex-1 bg-green-600 hover:bg-green-700 text-white py-2 rounded-lg text-center font-semibold transition">Approve</a>
                    <a href="?action=reject&id=<?= $row['id']; ?>" class="flex-1 bg-red-600 hover:bg-red-700 text-white py-2 rounded-lg text-center font-semibold transition">Reject</a>
                  </div>
                </div>
              </div>
            </div>
        <?php endwhile; ?>
      </div>
      <?php else: ?>
        <p class="text-gray-600 text-center mt-10">No pending divers for verification.</p>
      <?php endif; ?>
    </div>
  </main>
</div>

<footer class="bg-blue-700 text-white text-center py-4 mt-12">
  <div class="container mx-auto">
    <small>&copy; <?= date('Y') ?> DiveConnect. All rights reserved.</small>
  </div>
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

function openModal(id) {
  document.getElementById('modal-' + id).classList.remove('hidden');
  document.getElementById('modal-' + id).classList.add('flex');
}
function closeModal(id) {
  document.getElementById('modal-' + id).classList.remove('flex');
  document.getElementById('modal-' + id).classList.add('hidden');
}

<?php if (!empty($_SESSION['swal_message'])): ?>
Swal.fire({
  icon: 'success',
  title: 'Success',
  text: '<?= $_SESSION['swal_message']; ?>',
  confirmButtonColor: '#2563eb'
});
<?php unset($_SESSION['swal_message']); endif; ?>
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