 <?php
// Optional: include header/navigation here
include '../header.php';
session_start();

// If user is logged in, clicking the logo should go to dashboard instead of landing page
$homeUrl = isset($_SESSION['user_id']) ? 'user/user_dashboard.php' : 'index.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>About - DIVECONNECT</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans">

  <!-- Header -->
  <header class="bg-blue-600 text-white p-4 shadow-md">
    <div class="container mx-auto flex items-center justify-between">
      <!-- Logo -->
      <a href="<?= htmlspecialchars($homeUrl, ENT_QUOTES) ?>">
        <img src="assets/images/GROUP_4_-_DIVE_CONNECT-removebg-preview.png" class="h-14" alt="Logo">
      </a>

      <!-- Desktop Right Section -->
      <div class="flex items-center gap-2 ml-4">
        <!-- Language -->
        <!-- <div class="relative">
          <button onclick="toggleDropdown('langDropdown')" class="px-4 hover:bg-blue-700 rounded">🌐 Language</button>
          <div id="langDropdown" class="absolute right-0 mt-2 w-32 bg-white text-black rounded shadow-md hidden">
            <a href="?lang=en" class="block px-4 py-2 hover:bg-gray-200">English</a>
            <a href="?lang=ph" class="block px-4 py-2 hover:bg-gray-200">Filipino</a>
          </div>
        </div> -->

        <!-- Help -->
        <!-- <div class="relative">
          <button onclick="toggleDropdown('helpDropdown')" class="px-4 py-2 hover:bg-blue-700 rounded">❓ Help</button>
          <div id="helpDropdown" class="absolute right-0 mt-2 w-40 bg-white text-black rounded shadow-md hidden">
            <a href="faq.php" class="block px-4 py-2 hover:bg-gray-200">FAQ</a>
            <a href="support.php" class="block px-4 py-2 hover:bg-gray-200">Support</a>
            <a href="contact.php" class="block px-4 py-2 hover:bg-gray-200">Contact</a>
          </div>
        </div> -->
      </div>
    </div>
  </header>

  <!-- Hero Section -->
  <section class="relative h-[650px] flex flex-col items-center justify-center text-center text-white px-6"
           style="background-image: url('assets/images/dive background.jpg'); background-size: cover; background-position: center;">
    <h1 class="text-4xl font-bold mb-4 drop-shadow-lg">Welcome to DIVECONNECT</h1>
    <p class="text-lg text-gray-700 max-w-2xl mx-auto bg-white/100 px-4 py-2 rounded-lg">
      Connecting divers and tourists for safe, exciting, and unforgettable diving adventures in Mabini, Batangas.
      <strong>DiveConnect</strong> is a modern booking and scheduling platform for diving activities. 
      Our goal is to make it easier for tourists and diving enthusiasts to connect with 
      <strong>licensed professional divers</strong> in Mabini, Batangas.  
      <br><br>
      Through this system, you can book divers, manage appointments, and ensure 
      safe and enjoyable underwater experiences. Whether you’re a beginner or an experienced diver, 
      DiveConnect is your trusted partner for ocean adventures. 🌊
    </p>
  </section>

  <script>
    // Dropdown toggle
    function toggleDropdown(id) {
      document.getElementById(id).classList.toggle("hidden");
    }
  </script>

</body>
</html>
