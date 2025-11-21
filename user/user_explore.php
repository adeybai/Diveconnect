<?php
// user/user_explore.php — Explore Mabini (Logged-in Diver View)

session_start();

// Require login for this page
if (empty($_SESSION['user_id'])) {
  header('Location: user_login.php');
  exit;
}

// Add database connection for dive master destinations
require '../includes/db.php';

// Fetch dive master destinations with diver information
$diver_destinations_query = "
    SELECT dd.*, d.fullname as diver_name, d.profile_pic as diver_photo, d.price as diver_price, d.specialty 
    FROM diver_destinations dd 
    JOIN divers d ON dd.diver_id = d.id 
    WHERE dd.is_active = 1 
    AND d.verification_status = 'approved'
    ORDER BY dd.created_at DESC
";
$diver_destinations_result = $conn->query($diver_destinations_query);

// Where "Book a Dive" should go for logged-in users
$bookUrl = 'user_dashboard.php';

// Get user info for personalized greeting
$user_id = $_SESSION['user_id'];
$user_query = "SELECT fullname FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();
$user_name = $user ? $user['fullname'] : 'Diver';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Explore Mabini — DiveConnect</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
  <style>
    /* Smooth floating animation */
    .card-float {
      transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .card-float:hover {
      transform: translateY(-12px);
      box-shadow: 0 20px 40px rgba(37, 99, 235, 0.2);
    }
    
    /* Image zoom effect */
    .image-zoom {
      overflow: hidden;
    }
    .image-zoom img {
      transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .image-zoom:hover img {
      transform: scale(1.15);
    }
    
    /* Gradient overlay animation */
    .gradient-overlay {
      background: linear-gradient(
        to top,
        rgba(0,0,0,0.7) 0%,
        rgba(0,0,0,0.3) 50%,
        transparent 100%
      );
      opacity: 0;
      transition: opacity 0.4s ease;
    }
    .image-zoom:hover .gradient-overlay {
      opacity: 1;
    }
    
    /* Heart pulse animation */
    @keyframes heartbeat {
      0%, 100% { transform: scale(1); }
      10%, 30% { transform: scale(1.1); }
      20%, 40% { transform: scale(1); }
    }
    .heart-icon:hover {
      animation: heartbeat 1s ease-in-out;
    }
    
    /* Badge shine effect */
    .rating-badge {
      background: linear-gradient(
        135deg,
        rgba(255,255,255,0.95) 0%,
        rgba(255,255,255,0.85) 100%
      );
      backdrop-filter: blur(10px);
    }
    
    /* Button hover glow */
    .btn-glow {
      position: relative;
      overflow: hidden;
    }
    .btn-glow::before {
      content: '';
      position: absolute;
      top: 50%;
      left: 50%;
      width: 0;
      height: 0;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.3);
      transform: translate(-50%, -50%);
      transition: width 0.6s, height 0.6s;
    }
    .btn-glow:hover::before {
      width: 300px;
      height: 300px;
    }
    
    /* Parallax hero */
    .hero-pattern {
      background-image: 
        radial-gradient(circle at 20% 50%, rgba(59, 130, 246, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 80% 80%, rgba(96, 165, 250, 0.1) 0%, transparent 50%);
    }
    
    /* Loading animation */
    .loading-pulse {
      animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }
    
    @keyframes pulse {
      0%, 100% { opacity: 1; }
      50% { opacity: .5; }
    }
    
    /* Fade in animation */
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    .fade-in {
      animation: fadeIn 0.6s ease-out forwards;
    }

    /* Premium badge for dive master destinations */
    .premium-badge {
      background: linear-gradient(135deg, #f59e0b, #d97706);
      box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
    }

    /* Verified badge */
    .verified-badge {
      background: linear-gradient(135deg, #10b981, #059669);
      box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
    }

    /* Premium card glow effect */
    .premium-card {
      border: 1px solid rgba(245, 158, 11, 0.2);
    }

    .premium-card:hover {
      border-color: rgba(245, 158, 11, 0.4);
      box-shadow: 0 20px 40px rgba(245, 158, 11, 0.15);
    }
  </style>
</head>
<body class="bg-gradient-to-br from-gray-50 via-blue-50 to-gray-50 text-gray-800">
  <!-- HEADER (User version) -->
  <header class="bg-blue-700 text-white shadow-lg sticky top-0 z-50">
    <div class="container mx-auto flex items-center justify-between p-4">
      <!-- LOGO: always go to user_dashboard (do NOT logout) -->
      <a href="user_dashboard.php" class="flex items-center gap-2">
        <img src="../assets/images/GROUP_4_-_DIVE_CONNECT-removebg-preview.png"
             class="h-14"
             alt="DiveConnect Logo">
      </a>

      <!-- DESKTOP NAV -->
      <nav class="hidden md:flex items-center gap-6 font-medium">
        <a href="user_dashboard.php" class="hover:text-gray-200 transition">Dashboard</a>
        <a href="user_search_divers.php" class="hover:text-gray-200 transition">Divers</a>
        <a href="user_explore.php" class="bg-white text-blue-700 px-4 py-2 rounded-lg shadow hover:bg-blue-100 font-semibold transition">
          Explore
        </a>
        <a href="<?= htmlspecialchars($bookUrl, ENT_QUOTES, 'UTF-8') ?>" class="hover:text-gray-200 transition">
          Book a Dive
        </a>
        <!-- User dropdown -->
        <div class="relative group">
          <button class="flex items-center gap-2 hover:text-gray-200 transition">
            <span>Hi, <?= htmlspecialchars(explode(' ', $user_name)[0]) ?></span>
            <i class="ri-arrow-down-s-line"></i>
          </button>
          <div class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg py-2 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 z-50">
            <a href="user_profile.php" class="block px-4 py-2 text-gray-800 hover:bg-blue-50">My Profile</a>
            <a href="user_bookings.php" class="block px-4 py-2 text-gray-800 hover:bg-blue-50">My Bookings</a>
            <div class="border-t my-1"></div>
            <a href="../includes/logout.php" class="block px-4 py-2 text-red-600 hover:bg-red-50">Logout</a>
          </div>
        </div>
      </nav>

      <!-- MOBILE MENU BUTTON -->
      <button id="mobileMenuBtn" class="md:hidden flex items-center px-3 py-2 border rounded text-white border-white">
        <svg class="fill-current h-6 w-6" viewBox="0 0 20 20">
          <path d="M0 3h20v2H0zM0 9h20v2H0zM0 15h20v2H0z"/>
        </svg>
      </button>
    </div>

    <!-- MOBILE NAV -->
    <div id="mobileMenu" class="hidden md:hidden px-4 pb-3 space-y-2 bg-blue-800">
      <a href="user_dashboard.php" class="block px-4 py-2 hover:bg-blue-900 rounded transition">Dashboard</a>
      <a href="user_search_divers.php" class="block px-4 py-2 hover:bg-blue-900 rounded transition">Divers</a>
      <a href="user_explore.php" class="block px-4 py-2 bg-blue-900 rounded">Explore</a>
      <a href="<?= htmlspecialchars($bookUrl, ENT_QUOTES, 'UTF-8') ?>" class="block px-4 py-2 hover:bg-blue-900 rounded transition">
        Book a Dive
      </a>
      <div class="border-t border-blue-700 pt-2">
        <a href="user_profile.php" class="block px-4 py-2 hover:bg-blue-900 rounded transition">My Profile</a>
        <a href="user_bookings.php" class="block px-4 py-2 hover:bg-blue-900 rounded transition">My Bookings</a>
        <a href="../includes/logout.php" class="block px-4 py-2 text-red-300 hover:bg-blue-900 rounded transition">Logout</a>
      </div>
    </div>
  </header>

  <!-- HERO SECTION -->
  <section class="relative bg-gradient-to-br from-blue-700 via-blue-600 to-blue-800 text-white py-20 overflow-hidden hero-pattern">
    <div class="absolute inset-0 opacity-10">
      <div class="absolute top-10 left-10 w-32 h-32 bg-white rounded-full blur-3xl"></div>
      <div class="absolute bottom-10 right-10 w-40 h-40 bg-blue-300 rounded-full blur-3xl"></div>
    </div>
    <div class="container mx-auto px-6 relative z-10 text-center">
      <h1 class="text-5xl md:text-6xl font-bold mb-6 drop-shadow-lg fade-in">
        Explore the Wonders of Mabini
      </h1>
      <p class="text-xl text-blue-100 max-w-3xl mx-auto leading-relaxed mb-8 fade-in" style="animation-delay: 0.2s">
        Discover the best dive spots, scenic beaches, and underwater treasures that make Mabini, Batangas a diver's paradise.
      </p>
      <div class="flex justify-center gap-4 flex-wrap fade-in" style="animation-delay: 0.4s">
        <span class="bg-white/20 backdrop-blur-sm px-6 py-2 rounded-full text-sm font-medium">10+ Dive Sites</span>
        <span class="bg-white/20 backdrop-blur-sm px-6 py-2 rounded-full text-sm font-medium">Crystal Clear Waters</span>
        <span class="bg-white/20 backdrop-blur-sm px-6 py-2 rounded-full text-sm font-medium">Marine Sanctuary</span>
      </div>
    </div>
  </section>

  <!-- DIVE MASTER DESTINATIONS SECTION - MOVED TO TOP -->
  <?php if ($diver_destinations_result->num_rows > 0): ?>
  <div class="container mx-auto px-6 mt-16">
    <div class="text-center mb-12 fade-in">
      <div class="inline-flex items-center gap-3 mb-4">
        <div class="premium-badge rounded-full px-4 py-2 text-white font-semibold text-sm">
          <i class="ri-star-fill mr-1"></i> Premium
        </div>
        <h2 class="text-3xl md:text-4xl font-bold text-gray-800">Dive Master Experiences</h2>
        <div class="verified-badge rounded-full px-4 py-2 text-white font-semibold text-sm">
          <i class="ri-shield-check-fill mr-1"></i> Verified
        </div>
      </div>
      <p class="text-gray-600 max-w-2xl mx-auto text-lg">
        Exclusive dive spots curated and guided by our professional dive masters
      </p>
    </div>

    <div class="grid gap-8 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3" id="dive-master-grid">
      <?php 
      $index = 0;
      while ($dest = $diver_destinations_result->fetch_assoc()): 
        $delay = $index * 0.1;
        $index++;
      ?>
      <div class='card-float premium-card relative bg-white rounded-2xl overflow-hidden shadow-lg hover:shadow-2xl group fade-in' style='animation-delay: <?= $delay ?>s'>
        <div class="image-zoom relative">
          <img src="../<?= htmlspecialchars($dest['image_path']) ?>" alt="<?= htmlspecialchars($dest['title']) ?>" class="w-full h-64 object-cover">
          <div class="gradient-overlay absolute inset-0"></div>
          
          <!-- Premium Dive Master Badge -->
          <div class='absolute top-4 left-4 premium-badge rounded-full px-3 py-1 shadow-lg z-10 flex items-center gap-2'>
            <i class="ri-user-star-fill text-white text-sm"></i>
            <span class="text-sm font-semibold text-white">Dive Master</span>
          </div>
          
          <!-- Price Badge -->
          <div class='absolute top-4 right-4 rating-badge rounded-full px-4 py-2 shadow-lg z-10'>
            <div class='flex items-center gap-1'>
              <span class="font-bold text-blue-600">₱<?= number_format(floatval($dest['price_per_diver']), 2) ?></span>
            </div>
          </div>

          <!-- Diver Profile -->
          <div class='absolute bottom-4 left-4 bg-black/60 backdrop-blur-sm rounded-full px-3 py-2 shadow-lg z-10 flex items-center gap-2'>
            <img src="../admin/uploads/<?= htmlspecialchars($dest['diver_photo'] ?: 'default.png') ?>" 
                 class="w-8 h-8 rounded-full object-cover border-2 border-white">
            <div class="text-white">
              <div class="text-sm font-semibold"><?= htmlspecialchars(explode(' ', $dest['diver_name'])[0]) ?></div>
              <div class="text-xs text-blue-200"><?= htmlspecialchars($dest['specialty']) ?></div>
            </div>
          </div>
        </div>
        
        <div class="p-6">
          <h2 class="text-2xl font-bold text-blue-800 mb-2 group-hover:text-blue-900 transition"><?= htmlspecialchars($dest['title']) ?></h2>
          <div class="flex items-center text-gray-600 mb-2">
            <svg class="h-5 w-5 text-blue-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path d="M10 20s6-5.33 6-10A6 6 0 0 0 4 10c0 4.67 6 10 6 10z"/>
            </svg>
            <span class="text-sm font-medium"><?= htmlspecialchars($dest['location']) ?></span>
          </div>
          
          <p class="text-gray-700 mb-4 text-sm leading-relaxed"><?= htmlspecialchars($dest['description']) ?></p>
          
          <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
              <div class="flex text-yellow-400">
                <?php for ($i=1; $i<=5; $i++): ?>
                  <i class="ri-star-fill <?= $i <= $dest['rating'] ? 'text-yellow-400' : 'text-gray-300' ?> text-sm"></i>
                <?php endfor; ?>
              </div>
              <span class="text-sm text-gray-500"><?= $dest['rating'] ?>.0</span>
            </div>
            <div class="text-right">
              <div class="text-lg font-bold text-blue-600">₱<?= number_format(floatval($dest['price_per_diver']), 2) ?></div>
              <div class="text-xs text-gray-500">per diver</div>
            </div>
          </div>
          
          <a href="user_search_divers.php?diver=<?= $dest['diver_id'] ?>" class="btn-glow relative inline-block w-full text-center bg-gradient-to-r from-amber-500 to-amber-600 text-white px-6 py-3 rounded-xl font-semibold hover:from-amber-600 hover:to-amber-700 transition shadow-md hover:shadow-lg transform hover:scale-105">
            <i class="ri-user-star-fill mr-2"></i>
            Book with <?= htmlspecialchars(explode(' ', $dest['diver_name'])[0]) ?>
          </a>
        </div>
      </div>
      <?php endwhile; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- REGULAR DESTINATIONS SECTION -->
  <main class="container mx-auto py-16 px-6">
    <div class="text-center mb-12 fade-in">
      <h2 class="text-3xl md:text-4xl font-bold text-gray-800 mb-3">Popular Dive Destinations</h2>
      <p class="text-gray-600 max-w-2xl mx-auto">
        Handpicked locations for the ultimate underwater adventure
      </p>
    </div>

    <div class="grid gap-8 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3" id="destinations-grid">
      <?php 
      // NOTE: Adjust image paths to go one level up (../assets/...)
      $places = [
        ['title'=>'Twin Rocks Marine Sanctuary', 'img'=>'../assets/images/twinrocks.jpg',   'location'=>'Anilao, Mabini',   'rating'=>5, 'desc'=>'Premier marine sanctuary'],
        ['title'=>'Sombrero Island',             'img'=>'../assets/images/sombrero.jpg',   'location'=>'Maricaban Strait', 'rating'=>4, 'desc'=>'Iconic dive landmark'],
        ['title'=>'Mapating Cave Dive Site',     'img'=>'../assets/images/popog.jpg',      'location'=>'Mabini, Batangas', 'rating'=>5, 'desc'=>'Thrilling cave exploration'],
        ['title'=>'Kirby\'s Rock',               'img'=>'../assets/images/kirby.jpg',      'location'=>'Mabini, Batangas', 'rating'=>4, 'desc'=>'Vibrant coral gardens'],
        ['title'=>'Beatrice Rock',               'img'=>'../assets/images/beatrice.jpg',   'location'=>'Mabini, Batangas', 'rating'=>5, 'desc'=>'Rich marine biodiversity'],
        ['title'=>'Ligpo Island',                'img'=>'../assets/images/ligpo.jpg',      'location'=>'Mabini, Batangas', 'rating'=>5, 'desc'=>'Pristine island paradise'],
        ['title'=>'Arthur\'s Rock',              'img'=>'../assets/images/arthur.jpg',     'location'=>'Mabini, Batangas', 'rating'=>4, 'desc'=>'Macro photography haven'],
        ['title'=>'Cathedral Wall',              'img'=>'../assets/images/cathedral.jpg',  'location'=>'Mabini, Batangas', 'rating'=>5, 'desc'=>'Dramatic wall dive'],
        ['title'=>'Red Palm Beach',              'img'=>'../assets/images/palm.jpg',       'location'=>'Maricaban Strait', 'rating'=>4, 'desc'=>'Serene beach diving'],
        ['title'=>'Mainit Point',                'img'=>'../assets/images/mainit_point.jpg','location'=>'Mabini, Batangas','rating'=>5, 'desc'=>'Underwater hot springs'],
      ];

      foreach ($places as $index => $p) {
        $title    = htmlspecialchars($p['title'],    ENT_QUOTES, 'UTF-8');
        $img      = htmlspecialchars($p['img'],      ENT_QUOTES, 'UTF-8');
        $location = htmlspecialchars($p['location'], ENT_QUOTES, 'UTF-8');
        $desc     = htmlspecialchars($p['desc'],     ENT_QUOTES, 'UTF-8');
        $delay = $index * 0.1;

        echo "
        <div class='card-float relative bg-white rounded-2xl overflow-hidden shadow-lg hover:shadow-2xl group fade-in' style='animation-delay: {$delay}s'>
          <div class=\"image-zoom relative\">
            <img src=\"{$img}\" alt=\"{$title}\" class=\"w-full h-64 object-cover\">
            <div class=\"gradient-overlay absolute inset-0\"></div>
            
            <!-- Rating Badge -->
            <div class='absolute top-4 right-4 rating-badge rounded-full px-4 py-2 shadow-lg z-10'>
                <div class='flex items-center gap-1'>
                  <i class=\"ri-star-fill text-yellow-400\"></i>
                  <span class=\"font-bold text-blue-600\">{$p['rating']}.0</span>
                </div>
            </div>

            <!-- Description overlay -->
            <div class=\"absolute bottom-0 left-0 right-0 p-4 text-white transform translate-y-full group-hover:translate-y-0 transition-transform duration-300\">
              <p class=\"text-sm font-medium opacity-0 group-hover:opacity-100 transition-opacity delay-100\">{$desc}</p>
            </div>
          </div>
          
          <div class=\"p-6\">
            <h2 class=\"text-2xl font-bold text-blue-800 mb-2 group-hover:text-blue-900 transition\">{$title}</h2>
            <div class=\"flex items-center text-gray-600 mb-4\">
              <svg class=\"h-5 w-5 text-blue-500 mr-2 flex-shrink-0\" fill=\"currentColor\" viewBox=\"0 0 20 20\">
                <path d=\"M10 20s6-5.33 6-10A6 6 0 0 0 4 10c0 4.67 6 10 6 10z\"/>
              </svg>
              <span class=\"text-sm font-medium\">{$location}</span>
            </div>
            <a href=\"".htmlspecialchars($bookUrl, ENT_QUOTES, 'UTF-8')."\" class=\"btn-glow relative inline-block w-full text-center bg-gradient-to-r from-blue-600 to-blue-700 text-white px-6 py-3 rounded-xl font-semibold hover:from-blue-700 hover:to-blue-800 transition shadow-md hover:shadow-lg transform hover:scale-105\">
              Book a Dive
            </a>
          </div>
        </div>";
      }
      ?>
    </div>
  </main>

  <!-- FOOTER -->
  <footer class="bg-gradient-to-r from-blue-800 via-blue-700 to-blue-800 text-white text-center py-8 mt-16">
    <div class="container mx-auto px-6">
      <p class="text-lg font-medium">&copy; <?php echo date('Y'); ?> DiveConnect — Discover the Deep.</p>
      <p class="text-blue-200 text-sm mt-2">Your gateway to underwater adventures</p>
    </div>
  </footer>

  <script>
  document.getElementById('mobileMenuBtn').addEventListener('click', function () {
    document.getElementById('mobileMenu').classList.toggle('hidden');
  });

  // Add intersection observer for fade-in animations
  document.addEventListener('DOMContentLoaded', function() {
    const fadeElements = document.querySelectorAll('.fade-in');
    
    const fadeObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.style.opacity = 1;
          entry.target.style.transform = 'translateY(0)';
        }
      });
    }, { threshold: 0.1 });
    
    fadeElements.forEach(el => {
      fadeObserver.observe(el);
    });
  });
  </script>
</body>
</html>