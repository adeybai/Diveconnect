<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
require '../includes/db.php';

// Require admin session
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// helper function
function e($v) { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Home | DiveConnect</title>
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
</style>
</head>
<body class="min-h-screen bg-gray-100 font-sans">

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
    <div class="flex items-center gap-2 sm:gap-3">
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
        <a href="index.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg bg-blue-100 text-blue-700 font-semibold shadow-sm"><i class="ri-home-4-line text-xl"></i><span>Home</span></a>
        <a href="admin_dashboard.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50"><i class="ri-dashboard-line text-xl"></i><span>Dashboard</span></a>
        <a href="manage_admins.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50"><i class="ri-user-settings-line text-xl"></i><span>Manage Admins</span></a>
        <a href="manage_divers.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50"><i class="ri-user-star-line text-xl"></i><span>Manage Dive Master</span></a>
        <a href="verify_divers.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50"><i class="ri-shield-check-line text-xl"></i><span>Verify Master Divers</span></a>
        <a href="manage_user_divers.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50"><i class="ri-user-line text-xl"></i><span>Manage User Divers</span></a>
        <a href="verify_user_divers.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50"><i class="ri-user-shared-line text-xl"></i><span>Verify User Divers</span></a>
        <a href="manage_bookings.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50"><i class="ri-calendar-check-line text-xl"></i><span>Bookings</span></a>
        <a href="payments.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50"><i class="ri-bank-card-line text-xl"></i><span>Payments</span></a>
        <a href="manage_destinations.php" class="flex items-center gap-3 px-3 sm:px-4 py-3 rounded-lg hover:bg-blue-50 transition-colors">
          <i class="ri-map-pin-line text-xl"></i>
          <span>Destinations</span>
        </a>
      </nav>
    </div>
  </aside>

  <!-- MAIN CONTENT -->
  <main class="md:col-span-9">

    <!-- Background image - Responsive height -->
    <div class="relative w-full h-64 sm:h-80 md:h-96 lg:h-[500px] rounded-lg overflow-hidden shadow-xl mb-6 sm:mb-10">
      <img src="../assets/images/dive background.jpg" 
           alt="Dive Background" 
           class="object-cover w-full h-full brightness-75">

      <!-- Overlay text - Responsive sizing -->
      <div class="absolute inset-0 flex flex-col items-center justify-center text-center text-white px-4">
        <h1 class="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-bold drop-shadow-lg">DiveConnect</h1>
        <p class="mt-2 sm:mt-3 text-sm sm:text-base md:text-lg text-gray-200 max-w-xs sm:max-w-md md:max-w-2xl leading-relaxed">
          DiveConnect was created to connect divers, instructors, and dive centers across the Philippines — 
          empowering a community built on safety, exploration, and passion for the ocean.
        </p>
      </div>
    </div>

    <!-- Info Icons Section - Responsive Grid -->
    <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 text-center">
      
      <div class="bg-white rounded-xl shadow-lg hover:shadow-xl transition-shadow p-5 sm:p-6">
        <div class="bg-blue-50 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
          <i class="ri-smartphone-line text-3xl sm:text-4xl text-blue-600"></i>
        </div>
        <h3 class="text-base sm:text-lg font-semibold mb-2">Responsive</h3>
        <p class="text-gray-600 text-xs sm:text-sm leading-relaxed">
          DiveConnect adapts to any device — mobile, tablet, or desktop — providing smooth navigation for everyone.
        </p>
      </div>

      <div class="bg-white rounded-xl shadow-lg hover:shadow-xl transition-shadow p-5 sm:p-6">
        <div class="bg-teal-50 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
          <i class="ri-exchange-dollar-line text-3xl sm:text-4xl text-teal-600"></i>
        </div>
        <h3 class="text-base sm:text-lg font-semibold mb-2">Dynamic</h3>
        <p class="text-gray-600 text-xs sm:text-sm leading-relaxed">
          Real-time data updates let admins and divers interact seamlessly, ensuring up-to-date dive info.
        </p>
      </div>

      <div class="bg-white rounded-xl shadow-lg hover:shadow-xl transition-shadow p-5 sm:p-6">
        <div class="bg-purple-50 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
          <i class="ri-settings-5-line text-3xl sm:text-4xl text-purple-600"></i>
        </div>
        <h3 class="text-base sm:text-lg font-semibold mb-2">Flexible</h3>
        <p class="text-gray-600 text-xs sm:text-sm leading-relaxed">
          DiveConnect adapts to the needs of dive centers, instructors, and users for better operational control.
        </p>
      </div>

      <div class="bg-white rounded-xl shadow-lg hover:shadow-xl transition-shadow p-5 sm:p-6">
        <div class="bg-indigo-50 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
          <i class="ri-tools-line text-3xl sm:text-4xl text-indigo-600"></i>
        </div>
        <h3 class="text-base sm:text-lg font-semibold mb-2">Customizable</h3>
        <p class="text-gray-600 text-xs sm:text-sm leading-relaxed">
          Features can be tailored for each dive center or organization to match unique management requirements.
        </p>
      </div>
      
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