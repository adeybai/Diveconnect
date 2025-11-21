<?php
// user/user_explore.php — Explore Mabini (Logged-in Diver View)

session_start();

// Require login for this page
if (empty($_SESSION['user_id'])) {
  header('Location: user_login.php');
  exit;
}

// Where "Book a Dive" should go for logged-in users
// Change to user_booking.php or add #anchor if needed.
$bookUrl = 'user_dashboard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Explore Mabini — DiveConnect</title>
  <script src="https://cdn.tailwindcss.com"></script>
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
        <!-- Adjust these links if you have user-specific versions -->
        <a href="user_search_divers.php" class="hover:text-gray-200 transition">Divers</a>
        <a href="user_explore.php" class="bg-white text-blue-700 px-4 py-2 rounded-lg shadow hover:bg-blue-100 font-semibold transition">
          Explore
        </a>
        <a href="../about.php" class="hover:text-gray-200 transition">About</a>
        <a href="<?= htmlspecialchars($bookUrl, ENT_QUOTES, 'UTF-8') ?>" class="hover:text-gray-200 transition">
          Book
        </a>
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
      <a href="../about.php" class="block px-4 py-2 hover:bg-blue-900 rounded transition">About</a>
      <a href="<?= htmlspecialchars($bookUrl, ENT_QUOTES, 'UTF-8') ?>" class="block px-4 py-2 hover:bg-blue-900 rounded transition">
        Book
      </a>
    </div>
  </header>

  <!-- HERO SECTION -->
  <section class="relative bg-gradient-to-br from-blue-700 via-blue-600 to-blue-800 text-white py-20 overflow-hidden hero-pattern">
    <div class="absolute inset-0 opacity-10">
      <div class="absolute top-10 left-10 w-32 h-32 bg-white rounded-full blur-3xl"></div>
      <div class="absolute bottom-10 right-10 w-40 h-40 bg-blue-300 rounded-full blur-3xl"></div>
    </div>
    <div class="container mx-auto px-6 relative z-10 text-center">
      <h1 class="text-5xl md:text-6xl font-bold mb-6 drop-shadow-lg">
        Explore the Wonders of Mabini
      </h1>
      <p class="text-xl text-blue-100 max-w-3xl mx-auto leading-relaxed mb-8">
        Discover the best dive spots, scenic beaches, and underwater treasures that make Mabini, Batangas a diver's paradise.
      </p>
      <div class="flex justify-center gap-4 flex-wrap">
        <span class="bg-white/20 backdrop-blur-sm px-6 py-2 rounded-full text-sm font-medium">10+ Dive Sites</span>
        <span class="bg-white/20 backdrop-blur-sm px-6 py-2 rounded-full text-sm font-medium">Crystal Clear Waters</span>
        <span class="bg-white/20 backdrop-blur-sm px-6 py-2 rounded-full text-sm font-medium">Marine Sanctuary</span>
      </div>
    </div>
  </section>

  <!-- EXPLORE GRID -->
  <main class="container mx-auto py-16 px-6">
    <div class="text-center mb-12">
      <h2 class="text-3xl md:text-4xl font-bold text-gray-800 mb-3">Popular Dive Destinations</h2>
      <p class="text-gray-600 max-w-2xl mx-auto">
        Handpicked locations for the ultimate underwater adventure
      </p>
    </div>

    <div class="grid gap-8 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3">
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

      foreach ($places as $p) {
        $title    = htmlspecialchars($p['title'],    ENT_QUOTES, 'UTF-8');
        $img      = htmlspecialchars($p['img'],      ENT_QUOTES, 'UTF-8');
        $location = htmlspecialchars($p['location'], ENT_QUOTES, 'UTF-8');
        $desc     = htmlspecialchars($p['desc'],     ENT_QUOTES, 'UTF-8');

        echo "
        <div class='card-float relative bg-white rounded-2xl overflow-hidden shadow-lg hover:shadow-2xl group'>
          <div class=\"image-zoom relative\">
            <img src=\"{$img}\" alt=\"{$title}\" class=\"w-full h-64 object-cover\">
            <div class=\"gradient-overlay absolute inset-0\"></div>
            
            <!-- Rating Badge -->
            <div class=\"absolute top-4 right-4 rating-badge rounded-full px-4 py-2 shadow-lg z-10\">
              <div class=\"flex items-center gap-1\">";
              
              for ($i = 0; $i < 5; $i++) {
                if ($i < $p['rating']) {
                  echo "<svg class=\"h-4 w-4 text-red-500 heart-icon\" fill=\"currentColor\" viewBox=\"0 0 24 24\">
                          <path d=\"M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5C2 5.42 4.42 3 7.5 3C9.24 3 10.91 3.81 12 5.09C13.09 3.81 14.76 3 16.5 3C19.58 3 22 5.42 22 8.5C22 12.28 18.6 15.36 13.45 19.98L12 21.35z\"/>
                        </svg>";
                } else {
                  echo "<svg class=\"h-4 w-4 text-gray-300\" fill=\"currentColor\" viewBox=\"0 0 24 24\">
                          <path d=\"M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5C2 5.42 4.42 3 7.5 3C9.24 3 10.91 3.81 12 5.09C13.09 3.81 14.76 3 16.5 3C19.58 3 22 5.42 22 8.5C22 12.28 18.6 15.36 13.45 19.98L12 21.35z\"/>
                        </svg>";
                }
              }

              echo "</div>
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
  </script>
</body>
</html>
