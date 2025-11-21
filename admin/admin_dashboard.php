<?php
session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
require '../includes/db.php';

// Require admin session
if (!isset($_SESSION['admin_id'])) {
    header("Location: login_admin.php");
    exit;
}

// helper for escaping
function e($v) { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

// Fetch counts
$counts = [];
$tables = [
    'admins' => "SELECT COUNT(*) FROM admins",
    'users' => "SELECT COUNT(*) FROM users",
    'divers' => "SELECT COUNT(*) FROM divers",
    'bookings' => "SELECT COUNT(*) FROM bookings",
    'payments' => "SELECT COUNT(*) FROM payments",
    'availability' => "SELECT COUNT(*) FROM availability",
    'terms' => "SELECT COUNT(*) FROM terms_conditions"
];
foreach ($tables as $k => $sql) {
    $res = $conn->query($sql);
    $counts[$k] = $res ? $res->fetch_row()[0] : 0;
}

// Recent admins
$recent_admins = [];
$stmt = $conn->prepare("SELECT admin_id, fullname, email, role, created_at FROM admins ORDER BY created_at DESC LIMIT 6");
$stmt->execute();
$recent_admins = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Recent users (regular users - these are the User Divers)
$recent_users = [];
$stmt = $conn->prepare("SELECT id, fullname, email, phone, is_verified, created_at, profile_pic FROM users ORDER BY created_at DESC LIMIT 6");
$stmt->execute();
$recent_users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Recent divers (Dive Master - Professional Divers)
$recent_divers = [];
$stmt = $conn->prepare("SELECT id, fullname, email, specialty, level, profile_pic, created_at FROM divers ORDER BY created_at DESC LIMIT 6");
$stmt->execute();
$recent_divers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Upcoming availability
$upcoming_avail = [];
$stmt = $conn->prepare("
    SELECT a.id, a.available_date, a.available_time, a.status, d.fullname AS diver_name
    FROM availability a
    LEFT JOIN divers d ON a.diver_id = d.id
    WHERE a.status = 'available'
    ORDER BY a.available_date ASC, a.available_time ASC
    LIMIT 10
");
$stmt->execute();
$upcoming_avail = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Terms & Conditions (recent)
$terms = [];
$stmt = $conn->prepare("SELECT id, content, is_active, created_at FROM terms_conditions ORDER BY created_at DESC LIMIT 6");
$stmt->execute();
$terms = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Dashboard | DiveConnect</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon/fonts/remixicon.css">
<style>
  /* Smooth scrolling */
  html { scroll-behavior: smooth; }
  
  /* Hide scrollbar but keep functionality */
  .hide-scrollbar::-webkit-scrollbar { display: none; }
  .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
  
  /* Mobile menu animation */
  @keyframes slideIn {
    from { transform: translateX(-100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
  }
  .menu-enter { animation: slideIn 0.3s ease-out; }
  
  /* Backdrop blur for mobile menu */
  .backdrop-blur-sm { backdrop-filter: blur(4px); }
  
  /* Responsive table scroll */
  .table-container {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }
  
  /* Status badges */
  .status-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
  }
</style>
</head>
<body class="min-h-screen bg-gray-50 font-sans">

<!-- HEADER -->
<header class="bg-blue-700 text-white shadow-lg sticky top-0 z-50">
  <div class="container mx-auto flex items-center justify-between p-3 sm:p-4">
    <div class="flex items-center gap-2 sm:gap-3">
      <button id="menuToggle" class="md:hidden p-2 hover:bg-blue-600 rounded-lg transition-colors">
        <i class="ri-menu-line text-2xl"></i>
      </button>
      <img src="../assets/images/GROUP_4_-_DIVE_CONNECT-removebg-preview.png" alt="Logo" class="h-8 sm:h-10">
      <span class="hidden sm:inline text-lg font-semibold">DiveConnect</span>
    </div>
    <div class="flex items-center gap-2">
      <button id="logoutBtn" class="bg-red-500 hover:bg-red-600 px-3 sm:px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-md hover:shadow-lg">
        <i class="ri-logout-box-line mr-1"></i>
        <span class="hidden sm:inline">Logout</span>
        <span class="sm:hidden">Exit</span>
      </button>
      <form id="logoutForm" action="../index.php" method="post" class="hidden"></form>

    </div>
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
        <a href="admin_dashboard.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg bg-blue-100 text-blue-700 font-semibold shadow-sm"><i class="ri-dashboard-line text-xl"></i><span>Dashboard</span></a>
        <a href="manage_admins.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50"><i class="ri-user-settings-line text-xl"></i><span>Manage Admins</span></a>
        <a href="manage_divers.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50"><i class="ri-user-star-line text-xl"></i><span>Manage Dive Master</span></a>
        <a href="verify_divers.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50"><i class="ri-shield-check-line text-xl"></i><span>Verify Dive Master</span></a>
        <a href="manage_user_divers.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50"><i class="ri-user-line text-xl"></i><span>Manage User Divers</span></a>
        <a href="verify_user_divers.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50"><i class="ri-user-shared-line text-xl"></i><span>Verify User Divers</span></a>
        <a href="manage_destinations.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50 transition-colors">
          <i class="ri-map-pin-line text-xl"></i>
          <span>Destinations</span>
        </a>
      </nav>
    </div>
  </aside>

  <!-- MAIN -->
  <main class="md:col-span-9 space-y-4 sm:space-y-6">

    <!-- Dashboard cards - Responsive Grid -->
    <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
      
      <div class="bg-white p-4 sm:p-5 rounded-xl shadow-lg hover:shadow-xl transition-shadow flex items-center gap-4">
        <div class="bg-blue-100 text-blue-700 p-3 rounded-xl flex-shrink-0">
          <i class="ri-admin-line text-2xl sm:text-3xl"></i>
        </div>
        <div>
          <p class="text-xs sm:text-sm text-gray-500 font-medium">Admins</p>
          <p class="text-2xl sm:text-3xl font-bold text-gray-800"><?= e($counts['admins']) ?></p>
        </div>
      </div>

      <div class="bg-white p-4 sm:p-5 rounded-xl shadow-lg hover:shadow-xl transition-shadow flex items-center gap-4">
        <div class="bg-green-100 text-green-700 p-3 rounded-xl flex-shrink-0">
          <i class="ri-user-line text-2xl sm:text-3xl"></i>
        </div>
        <div>
          <p class="text-xs sm:text-sm text-gray-500 font-medium">User Divers</p>
          <p class="text-2xl sm:text-3xl font-bold text-gray-800"><?= e($counts['users']) ?></p>
        </div>
      </div>

      <div class="bg-white p-4 sm:p-5 rounded-xl shadow-lg hover:shadow-xl transition-shadow flex items-center gap-4">
        <div class="bg-purple-100 text-purple-700 p-3 rounded-xl flex-shrink-0">
          <i class="ri-vip-crown-line text-2xl sm:text-3xl"></i>
        </div>
        <div>
          <p class="text-xs sm:text-sm text-gray-500 font-medium">Dive Master</p>
          <p class="text-2xl sm:text-3xl font-bold text-gray-800"><?= e($counts['divers']) ?></p>
        </div>
      </div>

      <div class="bg-white p-4 sm:p-5 rounded-xl shadow-lg hover:shadow-xl transition-shadow flex items-center gap-4">
        <div class="bg-yellow-100 text-yellow-700 p-3 rounded-xl flex-shrink-0">
          <i class="ri-calendar-check-line text-2xl sm:text-3xl"></i>
        </div>
        <div>
          <p class="text-xs sm:text-sm text-gray-500 font-medium">Bookings</p>
          <p class="text-2xl sm:text-3xl font-bold text-gray-800"><?= e($counts['bookings']) ?></p>
        </div>
      </div>

      <div class="bg-white p-4 sm:p-5 rounded-xl shadow-lg hover:shadow-xl transition-shadow flex items-center gap-4 sm:col-span-2 lg:col-span-1">
        <div class="bg-indigo-100 text-indigo-700 p-3 rounded-xl flex-shrink-0">
          <i class="ri-time-line text-2xl sm:text-3xl"></i>
        </div>
        <div>
          <p class="text-xs sm:text-sm text-gray-500 font-medium">Availability</p>
          <p class="text-2xl sm:text-3xl font-bold text-gray-800"><?= e($counts['availability']) ?></p>
        </div>
      </div>

    </section>

    <!-- Manage Admins -->
    <section id="manage-admins" class="bg-white rounded-xl shadow-lg p-4 sm:p-6">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-base sm:text-lg font-bold text-blue-700 flex items-center gap-2">
          <i class="ri-admin-line text-xl"></i>
          <span>Manage Admins</span>
        </h3>
        <a href="manage_admins.php" class="text-xs sm:text-sm bg-blue-700 text-white px-3 py-1.5 sm:py-2 rounded-lg hover:bg-blue-800 transition-colors font-medium">
          Open
        </a>
      </div>
      <div class="table-container">
        <table class="min-w-full text-xs sm:text-sm text-left">
          <thead class="bg-gray-50 border-b-2 border-gray-200">
            <tr>
              <th class="px-2 sm:px-3 py-2 font-semibold text-gray-700">Fullname</th>
              <th class="px-2 sm:px-3 py-2 font-semibold text-gray-700">Email</th>
              <th class="px-2 sm:px-3 py-2 font-semibold text-gray-700">Role</th>
              <th class="px-2 sm:px-3 py-2 font-semibold text-gray-700 hidden sm:table-cell">Created</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recent_admins as $a): ?>
              <tr class="border-b hover:bg-gray-50 transition-colors">
                <td class="px-2 sm:px-3 py-2"><?= e($a['fullname']) ?></td>
                <td class="px-2 sm:px-3 py-2 truncate max-w-[150px]"><?= e($a['email']) ?></td>
                <td class="px-2 sm:px-3 py-2">
                  <span class="status-badge bg-blue-100 text-blue-700"><?= e($a['role']) ?></span>
                </td>
                <td class="px-2 sm:px-3 py-2 hidden sm:table-cell text-gray-500"><?= e($a['created_at']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Manage Dive Master -->
    <section id="manage-divers" class="bg-white rounded-xl shadow-lg p-4 sm:p-6">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-base sm:text-lg font-bold text-blue-700 flex items-center gap-2">
          <i class="ri-user-star-line text-xl"></i>
          <span>Manage Dive Master</span>
        </h3>
        <a href="manage_divers.php" class="text-xs sm:text-sm bg-blue-700 text-white px-3 py-1.5 sm:py-2 rounded-lg hover:bg-blue-800 transition-colors font-medium">
          Open
        </a>
      </div>
      <div class="table-container">
        <table class="min-w-full text-xs sm:text-sm text-left">
          <thead class="bg-gray-50 border-b-2 border-gray-200">
            <tr>
              <th class="px-2 sm:px-3 py-2 font-semibold text-gray-700">Name</th>
              <th class="px-2 sm:px-3 py-2 font-semibold text-gray-700">Specialty</th>
              <th class="px-2 sm:px-3 py-2 font-semibold text-gray-700 hidden sm:table-cell">Level</th>
              <th class="px-2 sm:px-3 py-2 font-semibold text-gray-700 hidden md:table-cell">Joined</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recent_divers as $d): ?>
              <tr class="border-b hover:bg-gray-50 transition-colors">
                <td class="px-2 sm:px-3 py-2">
                  <div class="flex items-center gap-2 sm:gap-3">
                    <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-full overflow-hidden bg-gray-200 flex-shrink-0">
                      <?php $dp = $d['profile_pic'] ? 'uploads/' . basename($d['profile_pic']) : '../assets/images/diver_default.png'; ?>
                      <img src="<?= e($dp) ?>" alt="<?= e($d['fullname']) ?>" class="object-cover w-full h-full">
                    </div>
                    <span class="truncate"><?= e($d['fullname']) ?></span>
                  </div>
                </td>
                <td class="px-2 sm:px-3 py-2 truncate max-w-[100px]"><?= e($d['specialty'] ?: '—') ?></td>
                <td class="px-2 sm:px-3 py-2 hidden sm:table-cell">
                  <span class="status-badge bg-purple-100 text-purple-700"><?= e($d['level'] ?: '—') ?></span>
                </td>
                <td class="px-2 sm:px-3 py-2 hidden md:table-cell text-gray-500"><?= e($d['created_at']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Manage User Divers -->
    <section id="manage-user-divers" class="bg-white rounded-xl shadow-lg p-4 sm:p-6">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-base sm:text-lg font-bold text-blue-700 flex items-center gap-2">
          <i class="ri-user-line text-xl"></i>
          <span>Manage User Divers</span>
        </h3>
        <a href="manage_user_divers.php" class="text-xs sm:text-sm bg-blue-700 text-white px-3 py-1.5 sm:py-2 rounded-lg hover:bg-blue-800 transition-colors font-medium">
          Open
        </a>
      </div>
      <div class="table-container">
        <table class="min-w-full text-xs sm:text-sm text-left">
          <thead class="bg-gray-50 border-b-2 border-gray-200">
            <tr>
              <th class="px-2 sm:px-3 py-2 font-semibold text-gray-700">Name</th>
              <th class="px-2 sm:px-3 py-2 font-semibold text-gray-700">Email</th>
              <th class="px-2 sm:px-3 py-2 font-semibold text-gray-700 hidden sm:table-cell">Phone</th>
              <th class="px-2 sm:px-3 py-2 font-semibold text-gray-700">Status</th>
              <th class="px-2 sm:px-3 py-2 font-semibold text-gray-700 hidden md:table-cell">Joined</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recent_users as $u): ?>
              <tr class="border-b hover:bg-gray-50 transition-colors">
                <td class="px-2 sm:px-3 py-2">
                  <div class="flex items-center gap-2 sm:gap-3">
                    <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-full overflow-hidden bg-gray-200 flex-shrink-0">
                      <?php $dp = $u['profile_pic'] ? 'uploads/' . basename($u['profile_pic']) : '../assets/images/user_default.png'; ?>
                      <img src="<?= e($dp) ?>" alt="<?= e($u['fullname']) ?>" class="object-cover w-full h-full">
                    </div>
                    <span class="truncate"><?= e($u['fullname']) ?></span>
                  </div>
                </td>
                <td class="px-2 sm:px-3 py-2 truncate max-w-[120px]"><?= e($u['email']) ?></td>
                <td class="px-2 sm:px-3 py-2 hidden sm:table-cell text-gray-600"><?= e($u['phone'] ?: '—') ?></td>
                <td class="px-2 sm:px-3 py-2">
                  <span class="status-badge <?= $u['is_verified'] ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' ?>">
                    <?= $u['is_verified'] ? 'Verified' : 'Pending' ?>
                  </span>
                </td>
                <td class="px-2 sm:px-3 py-2 hidden md:table-cell text-gray-500"><?= e($u['created_at']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Upcoming Availability -->
    <section id="availability" class="bg-white rounded-xl shadow-lg p-4 sm:p-6">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-base sm:text-lg font-bold text-blue-700 flex items-center gap-2">
          <i class="ri-calendar-todo-line text-xl"></i>
          <span>Upcoming Availability</span>
        </h3>
      </div>

      <?php if (count($upcoming_avail) > 0): ?>
        <div class="table-container">
          <table class="min-w-full text-xs sm:text-sm text-left">
            <thead class="bg-gray-50 border-b-2 border-gray-200">
              <tr>
                <th class="px-2 sm:px-3 py-2 font-semibold text-gray-700">Date</th>
                <th class="px-2 sm:px-3 py-2 font-semibold text-gray-700">Time</th>
                <th class="px-2 sm:px-3 py-2 font-semibold text-gray-700 hidden sm:table-cell">Diver</th>
                <th class="px-2 sm:px-3 py-2 font-semibold text-gray-700">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($upcoming_avail as $a): ?>
                <tr class="border-b hover:bg-gray-50 transition-colors">
                  <td class="px-2 sm:px-3 py-2 whitespace-nowrap text-gray-600"><?= e($a['available_date']) ?></td>
                  <td class="px-2 sm:px-3 py-2 whitespace-nowrap text-gray-600"><?= e($a['available_time']) ?></td>
                  <td class="px-2 sm:px-3 py-2 hidden sm:table-cell truncate max-w-[120px]"><?= e($a['diver_name'] ?: '—') ?></td>
                  <td class="px-2 sm:px-3 py-2">
                    <span class="status-badge bg-green-100 text-green-700"><?= e($a['status']) ?></span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="text-center py-8">
          <i class="ri-calendar-close-line text-4xl text-gray-300 mb-2"></i>
          <p class="text-gray-500">No availability listed.</p>
        </div>
      <?php endif; ?>
    </section>

  </main>
</div>

<!-- FOOTER -->
<footer class="bg-blue-700 text-white text-center py-4 sm:py-6 mt-8 sm:mt-12">
  <div class="container mx-auto px-4">
    <small class="text-xs sm:text-sm">&copy; <?= date('Y') ?> DiveConnect. All rights reserved.</small>
  </div>
</footer>

<!-- Mobile Menu Toggle Script -->
<script>
  const menuToggle = document.getElementById('menuToggle');
  const sidebar = document.getElementById('sidebar');
  const menuOverlay = document.getElementById('menuOverlay');

  function openMenu() {
    sidebar.classList.remove('-translate-x-full');
    sidebar.classList.add('menu-enter');
    menuOverlay.classList.remove('hidden');
    document.body.style.overflow = 'hidden'; // Prevent body scroll
  }

  function closeMenu() {
    sidebar.classList.add('-translate-x-full');
    menuOverlay.classList.add('hidden');
    document.body.style.overflow = ''; // Restore body scroll
  }

  menuToggle.addEventListener('click', openMenu);

  // Close menu when clicking on a link (for better UX)
  const navLinks = sidebar.querySelectorAll('a');
  navLinks.forEach(link => {
    link.addEventListener('click', () => {
      if (window.innerWidth < 768) {
        closeMenu();
      }
    });
  });

  // Close menu on window resize to desktop
  window.addEventListener('resize', () => {
    if (window.innerWidth >= 768) {
      closeMenu();
    }
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