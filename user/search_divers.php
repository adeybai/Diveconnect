<?php
require 'includes/db.php';
include '../header.php';

// Handle search input
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// ✅ FIXED: Only show verified/approved divers and use updated prices
$query = "
    SELECT id, fullname, specialty, level, profile_pic, price 
    FROM divers 
    WHERE (fullname LIKE ? OR specialty LIKE ?)
    AND verification_status IN ('verified', 'approved')
    ORDER BY fullname ASC
";
$stmt = $conn->prepare($query);
$like = "%$search%";
$stmt->bind_param("ss", $like, $like);
$stmt->execute();
$divers = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Explore Divers | DiveConnect</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon/fonts/remixicon.css">
<style>
  .card-hover {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  }
  .card-hover:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
  }
  
  .heart-beat {
    transition: all 0.3s ease;
  }
  .heart-beat:hover {
    transform: scale(1.2);
    color: #dc2626;
  }
</style>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col">

<!-- HEADER (from index.php style) -->
<header class="bg-blue-700 text-white shadow-md sticky top-0 z-40">
  <div class="container mx-auto flex justify-between items-center px-4 py-3">
    <a href="index.php" class="flex items-center gap-2">
      <img src="assets/images/GROUP_4_-_DIVE_CONNECT-removebg-preview.png" alt="Logo" class="h-12">
      <span class="font-bold text-xl">DiveConnect</span>
    </a>

    <nav class="hidden md:flex items-center gap-4">
      <a href="index.php" class="hover:text-gray-200 transition-colors">Home</a>
      <a href="explore.php" class="hover:text-gray-200 transition-colors">Explore</a>
      <a href="about.php" class="hover:text-gray-200 transition-colors">About</a>
      <a href="user/login_user.php" class="bg-white text-blue-700 px-4 py-2 rounded-lg font-semibold hover:bg-blue-100 transition-colors shadow-md">
        Login
      </a>
    </nav>

    <button id="mobileMenuBtn" class="md:hidden p-2 hover:bg-blue-600 rounded-lg transition-colors">
      <svg class="w-6 h-6" fill="white" viewBox="0 0 24 24">
        <path d="M4 6h16M4 12h16M4 18h16"/>
      </svg>
    </button>
  </div>
</header>

<!-- MOBILE MENU -->
<div id="mobileMenu" class="hidden md:hidden bg-blue-700 text-white p-4 space-y-2">
  <a href="index.php" class="block hover:bg-blue-800 px-3 py-2 rounded transition-colors">Home</a>
  <a href="explore.php" class="block hover:bg-blue-800 px-3 py-2 rounded transition-colors">Explore</a>
  <a href="about.php" class="block hover:bg-blue-800 px-3 py-2 rounded transition-colors">About</a>
  <a href="user/login_user.php" class="block bg-white text-blue-700 px-3 py-2 rounded font-semibold text-center">
    Login
  </a>
</div>

<!-- SEARCH SECTION -->
<section class="bg-gradient-to-r from-blue-600 to-blue-800 py-12 text-center text-white">
  <div class="container mx-auto px-6">
    <h1 class="text-4xl font-bold mb-4">Find Your Perfect Dive Buddy</h1>
    <p class="text-blue-100 text-lg mb-6 max-w-2xl mx-auto">
      Connect with professional dive masters who will guide you through unforgettable underwater adventures
    </p>
    <form method="GET" action="search_divers.php" class="flex justify-center mt-4">
      <div class="relative w-full max-w-2xl">
        <input type="text" 
               name="search" 
               placeholder="Search divers by name or specialty..." 
               value="<?= htmlspecialchars($search) ?>"
               class="w-full px-6 py-4 rounded-full focus:outline-none focus:ring-4 focus:ring-blue-300 text-gray-800 text-lg shadow-lg">
        <button type="submit" 
                class="absolute right-2 top-1/2 transform -translate-y-1/2 bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-full font-semibold transition-colors shadow-md flex items-center gap-2">
          <i class="ri-search-line"></i>
          Search
        </button>
      </div>
    </form>
  </div>
</section>

<!-- DIVER CARDS -->
<section class="container mx-auto px-6 py-12">
  <?php if ($divers->num_rows > 0): ?>
  <div class="text-center mb-8">
    <h2 class="text-3xl font-bold text-gray-800 mb-2">
      <?= $search ? 'Search Results' : 'Professional Dive Masters' ?>
    </h2>
    <p class="text-gray-600">
      <?= $search ? "Found {$divers->num_rows} diver(s) matching '{$search}'" : 'Browse our certified dive professionals' ?>
    </p>
  </div>

  <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-8">
    <?php while ($diver = $divers->fetch_assoc()): 
      // ✅ FIXED: Use the updated price from database
      $diver_price = $diver['price'] ?? 1000.00;
      $profile_pic = !empty($diver['profile_pic']) ? 
          "admin/uploads/{$diver['profile_pic']}" : 
          "assets/images/diver_default.png";
    ?>
      <div class="card-hover bg-white rounded-2xl shadow-lg overflow-hidden flex flex-col">
        <!-- Profile Image -->
        <div class="relative">
          <img src="<?= htmlspecialchars($profile_pic) ?>" 
               alt="<?= htmlspecialchars($diver['fullname']) ?>" 
               class="w-full h-56 object-cover">
          
          <!-- Price Badge -->
          <div class="absolute top-4 right-4 bg-white/95 backdrop-blur-sm rounded-full px-4 py-2 shadow-lg">
            <span class="font-bold text-blue-700 text-lg">₱<?= number_format($diver_price, 2) ?></span>
          </div>
        </div>

        <!-- Diver Info -->
        <div class="p-6 flex flex-col flex-grow">
          <h3 class="font-bold text-xl text-gray-800 mb-2"><?= htmlspecialchars($diver['fullname']) ?></h3>
          <p class="text-blue-600 font-semibold mb-2 flex items-center gap-2">
            <i class="ri-user-star-line"></i>
            <?= htmlspecialchars($diver['specialty'] ?? 'Diving Specialist') ?>
          </p>
          <p class="text-gray-600 mb-3 flex items-center gap-2">
            <i class="ri-award-line"></i>
            Level: <?= htmlspecialchars($diver['level'] ?? 'Professional') ?>
          </p>

          <!-- Rating & Price -->
          <div class="mt-auto space-y-3">
            <div class="flex items-center justify-between">
              <div class="flex text-yellow-400">
                <i class="ri-star-fill"></i>
                <i class="ri-star-fill"></i>
                <i class="ri-star-fill"></i>
                <i class="ri-star-fill"></i>
                <i class="ri-star-half-fill"></i>
              </div>
              <span class="text-2xl font-bold text-blue-700">₱<?= number_format($diver_price, 2) ?></span>
            </div>

            <!-- Action Buttons -->
            <div class="flex gap-2">
              <!-- ❤️ LIKE Button -->
              <button class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 py-2 rounded-lg font-semibold transition-colors heart-beat flex items-center justify-center gap-2">
                <i class="ri-heart-line"></i>
                Like
              </button>
              
              <!-- BOOK DIVER Button -->
              <a href="user/login_user.php" 
                 class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-lg font-semibold transition-colors shadow-md hover:shadow-lg text-center">
                Book Now
              </a>
            </div>
          </div>
        </div>
      </div>
    <?php endwhile; ?>
  </div>
  <?php else: ?>
    <div class="text-center py-16">
      <i class="ri-user-search-line text-6xl text-gray-400 mb-4"></i>
      <h3 class="text-2xl font-bold text-gray-600 mb-2">No Divers Found</h3>
      <p class="text-gray-500 mb-6 max-w-md mx-auto">
        <?= $search ? 
            "No dive masters found matching '{$search}'. Try searching with different keywords." : 
            "No dive masters available at the moment. Please check back later." 
        ?>
      </p>
      <?php if ($search): ?>
        <a href="search_divers.php" 
           class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors inline-flex items-center gap-2">
          <i class="ri-arrow-go-back-line"></i>
          View All Divers
        </a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</section>

<!-- FOOTER -->
<footer class="bg-gradient-to-r from-blue-800 to-blue-700 text-white text-center py-6 mt-auto">
  <div class="container mx-auto">
    <p class="font-medium text-lg">&copy; <?= date('Y') ?> DiveConnect. All rights reserved.</p>
    <p class="text-blue-200 text-sm mt-1">Connecting divers with unforgettable experiences</p>
  </div>
</footer>

<!-- MOBILE MENU SCRIPT -->
<script>
document.getElementById('mobileMenuBtn').addEventListener('click', () => {
  document.getElementById('mobileMenu').classList.toggle('hidden');
});

// Add some interactive features
document.addEventListener('DOMContentLoaded', function() {
  // Heart like animation
  const likeButtons = document.querySelectorAll('.heart-beat');
  likeButtons.forEach(button => {
    button.addEventListener('click', function() {
      const icon = this.querySelector('i');
      if (icon.classList.contains('ri-heart-line')) {
        icon.classList.remove('ri-heart-line');
        icon.classList.add('ri-heart-fill', 'text-red-500');
        this.classList.add('text-red-500');
      } else {
        icon.classList.remove('ri-heart-fill', 'text-red-500');
        icon.classList.add('ri-heart-line');
        this.classList.remove('text-red-500');
      }
    });
  });
});
</script>

</body>
</html>