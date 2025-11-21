<?php
session_start();
require 'includes/db.php';

// Handle search input
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$query = "
    SELECT id, fullname, specialty, level, profile_pic, price 
    FROM divers 
    WHERE fullname LIKE ? OR specialty LIKE ?
    ORDER BY fullname ASC
";
$stmt = $conn->prepare($query);
$like = "%$search%";
$stmt->bind_param("ss", $like, $like);
$stmt->execute();
$divers = $stmt->get_result();

// Get recommended divers (random selection)
$recommendQuery = "SELECT id, fullname, specialty, level, profile_pic, price FROM divers ORDER BY RAND() LIMIT 3";
$recommendResult = $conn->query($recommendQuery);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Explore Divers | DiveConnect</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  body {
    background-image: url('assets/images/dive background.jpg');
    background-size: cover;
    background-position: center;
    background-attachment: fixed;
    background-repeat: no-repeat;
  }
  
  body::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.3);
    z-index: -1;
  }
  
  .like-button {
    position: absolute;
    top: 12px;
    right: 12px;
    background-color: rgba(255, 255, 255, 0.9);
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
  }
  
  .like-button:hover {
    background-color: rgba(255, 255, 255, 1);
    transform: scale(1.1);
  }
  
  .like-button.liked {
    background-color: rgba(239, 68, 68, 0.1);
  }
  
  .like-icon {
    color: #ef4444;
    font-size: 20px;
    transition: all 0.3s ease;
  }
  
  .content-wrapper {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
  }
  
  .sidebar-card {
    background: rgba(255, 255, 255, 0.98);
    backdrop-filter: blur(5px);
  }
</style>
</head>
<body class="min-h-screen flex flex-col">

<!-- HEADER -->
<header class="bg-blue-600 text-white shadow-md relative z-40">
  <div class="container mx-auto flex justify-between items-center px-4 py-3">
    <a href="index.php" class="flex items-center gap-2">
      <img src="assets/images/GROUP_4_-_DIVE_CONNECT-removebg-preview.png" alt="Logo" class="h-12" />
    </a>

    <nav class="hidden md:flex items-center gap-4">
      <!-- <a href="index.php" class="hover:text-gray-200">Home</a> -->
      <a href="explore.php" class="hover:text-gray-200">Explore</a>
      <a href="about.php" class="hover:text-gray-200">About</a>
    </nav>

    <button id="mobileMenuBtn" class="md:hidden">
      <svg class="w-6 h-6" fill="white" viewBox="0 0 24 24">
        <path d="M4 6h16M4 12h16M4 18h16" />
      </svg>
    </button>
  </div>
</header>

<!-- MOBILE MENU -->
<div id="mobileMenu" class="hidden md:hidden bg-blue-600 text-white p-4 space-y-2">
  <a href="index.php" class="block hover:bg-blue-700 px-3 py-2 rounded">Home</a>
  <a href="explore.php" class="block hover:bg-blue-700 px-3 py-2 rounded">Explore</a>
  <a href="about.php" class="block hover:bg-blue-700 px-3 py-2 rounded">About</a>
</div>

<!-- SEARCH SECTION -->
<section class="bg-gradient-to-r from-blue-600 to-blue-800 py-10 text-center text-white shadow-lg">
  <h1 class="text-4xl font-bold mb-3">Find Your Perfect Dive Buddy</h1>
  <form method="GET" action="search_divers.php" class="flex justify-center mt-4 px-4">
    <input type="text" name="search" placeholder="Search divers by name or specialty..." 
           value="<?= htmlspecialchars($search) ?>"
           class="w-2/3 md:w-1/3 px-4 py-3 rounded-l-lg focus:outline-none text-gray-800 shadow-md" />
    <button type="submit" class="bg-white text-blue-700 px-6 py-3 rounded-r-lg font-semibold hover:bg-blue-100 shadow-md transition">
      Search
    </button>
  </form>
</section>

<!-- MAIN CONTENT AREA -->
<section class="container mx-auto px-4 py-10 flex-grow">
  <div class="flex flex-col lg:flex-row gap-6">
    
    <!-- LEFT SIDE: DIVER CARDS -->
    <div class="flex-grow lg:w-3/4">
      <?php if ($divers->num_rows > 0): ?>
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
        <?php while ($diver = $divers->fetch_assoc()): ?>
          <div class="content-wrapper rounded-lg shadow-lg hover:shadow-2xl transition transform hover:-translate-y-1 flex flex-col relative">
            <!-- Image Container with Like Button -->
            <div class="relative">
              <img src="admin/uploads/<?= htmlspecialchars(!empty($diver['profile_pic']) ? $diver['profile_pic'] : 'default.png') ?>" 
                   alt="<?= htmlspecialchars($diver['fullname']) ?>" 
                   class="w-full h-48 object-cover rounded-t-lg" />

              <!-- ❤️ LIKE BUTTON with data-diver-id -->
              <button class="like-button" 
                      data-diver-id="<?= $diver['id'] ?>" 
                      onclick="toggleLike(this, event)">
                <span class="like-icon">♥</span>
              </button>
            </div>

            <div class="p-4 flex flex-col flex-grow">
              <h3 class="font-bold text-lg text-gray-800"><?= htmlspecialchars($diver['fullname']) ?></h3>
              <p class="text-gray-600"><?= htmlspecialchars($diver['specialty'] ?? 'Diving Specialist') ?></p>
              <p class="text-gray-500 mb-3">Level: <?= htmlspecialchars($diver['level'] ?? 'N/A') ?></p>
              <!-- FIXED: Dynamic price from database -->
              <p class="text-blue-700 font-semibold mb-3">₱<?= number_format($diver['price'], 2) ?></p>

              <!-- BOOK DIVER -->
              <a href="user/user_login.php" 
                 class="mt-auto w-full bg-blue-600 hover:bg-blue-700 text-white text-center py-2 rounded font-semibold transition shadow-md">
                 Book Diver
              </a>
            </div>
          </div>
        <?php endwhile; ?>
      </div>
      <?php else: ?>
        <div class="content-wrapper p-8 rounded-lg shadow-lg text-center">
          <p class="text-gray-600 text-lg">No divers found matching your search.</p>
        </div>
      <?php endif; ?>
    </div>

    <!-- RIGHT SIDE: RECOMMENDATIONS -->
    <aside class="lg:w-1/4">
      <div class="sidebar-card rounded-lg shadow-lg p-6 sticky top-4">
        <h2 class="text-xl font-bold text-blue-700 mb-4">Divers you may also like</h2>
        <div class="space-y-4">
          <?php while ($recommend = $recommendResult->fetch_assoc()): ?>
            <div class="bg-white rounded-lg shadow-md hover:shadow-xl transition transform hover:-translate-y-1 overflow-hidden relative">
              <!-- Image with Like Button -->
              <div class="relative">
                <img src="admin/uploads/<?= htmlspecialchars(!empty($recommend['profile_pic']) ? $recommend['profile_pic'] : 'default.png') ?>" 
                     alt="<?= htmlspecialchars($recommend['fullname']) ?>" 
                     class="w-full h-40 object-cover" />

                <!-- Like Button -->
                <button class="like-button" 
                        data-diver-id="<?= $recommend['id'] ?>" 
                        onclick="toggleLike(this, event)">
                  <span class="like-icon">♥</span>
                </button>
              </div>
              <div class="p-3">
                <h3 class="font-bold text-sm text-gray-800"><?= htmlspecialchars($recommend['fullname']) ?></h3>
                <p class="text-xs text-gray-600"><?= htmlspecialchars($recommend['specialty'] ?? 'Diving Specialist') ?></p>
                <!-- FIXED: Dynamic price from database -->
                <p class="text-blue-700 font-semibold text-sm my-1">₱<?= number_format($recommend['price'], 2) ?></p>
                <a href="user/user_login.php" 
                   class="block w-full bg-blue-600 hover:bg-blue-700 text-white text-center py-2 rounded text-sm font-semibold transition mt-2">
                   VIEW
                </a>
              </div>
            </div>
          <?php endwhile; ?>
        </div>
      </div>
    </aside>
    
  </div>
</section>

<!-- FOOTER -->
<footer class="bg-blue-600 text-white text-center py-4 mt-auto shadow-lg">
  <p>&copy; <?= date('Y') ?> DiveConnect. All rights reserved.</p>
</footer>

<!-- SCRIPTS -->
<script>
// Mobile Menu Toggle
document.getElementById('mobileMenuBtn').addEventListener('click', () => {
  document.getElementById('mobileMenu').classList.toggle('hidden');
});

// Function to check login status and toggle like
function toggleLike(button, event) {
  event.preventDefault();
  event.stopPropagation();

  const isLiked = button.classList.toggle('liked');

  // Get diver ID from data attribute
  const diverId = button.getAttribute('data-diver-id');

  // Check login status from PHP session
  const isLoggedIn = <?= isset($_SESSION['user_id']) ? 'true' : 'false' ?>;

  if (!isLoggedIn) {
    // Remove liked class if user not logged in
    if (isLiked) {
      button.classList.remove('liked');
    }
    // Redirect to login or show login modal
    window.location.href = 'user/user_login.php';
    return;
  }

  // Optional: Send AJAX request to save favorite if logged in
  /*
  fetch('save_favorite.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ diver_id: diverId, liked: isLiked })
  });
  */

  // Animate scaling
  button.style.transform = 'scale(1.2)';
  setTimeout(() => {
    button.style.transform = '';
  }, 200);
}
</script>

</body>
</html>