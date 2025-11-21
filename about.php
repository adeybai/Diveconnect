<?php
// Optional: include header/navigation here
include 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>About - DIVECONNECT</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    /* Make sure background fits every screen */
    .hero-bg {
      background-image: url('assets/images/dive background.jpg');
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
      min-height: 100vh;
      width: 100%;
    }
    @media (max-width: 768px) {
      .hero-bg {
        min-height: 100vh;
      }
    }
  </style>
</head>
<body class="bg-gray-100 font-sans">

  <!-- Header -->
  <header class="bg-blue-600 text-white p-4 shadow-md">
    <div class="container mx-auto flex items-center justify-between">
      <!-- Logo -->
      <a href="index.php">
        <img src="assets/images/GROUP_4_-_DIVE_CONNECT-removebg-preview.png" class="h-14" alt="Logo">
      </a>

      <!-- Desktop Right Section -->
      <div class="flex items-center gap-2 ml-4">
        <!-- Explore -->
        <!-- <a href="explore.php" class="px-4 py-2 hover:bg-blue-700 rounded">Explore</a> -->

        <!-- Tidechart -->
        <!-- <a href="https://www.tideschart.com/Philippines/Calabarzon/Province-of-Batangas/Anilao/" class="px-4 py-2 hover:bg-blue-700 rounded">Tidechart</a> -->

        <!-- Help -->
        <div class="relative">
          <button onclick="toggleDropdown('helpDropdown')" class="px-4 py-2 hover:bg-blue-700 rounded">❓ Help</button>
          <div id="helpDropdown" class="absolute right-0 mt-2 w-40 bg-white text-black rounded shadow-md hidden z-50">
            <button onclick="openFAQModal()" class="block w-full text-left px-4 py-2 hover:bg-gray-200">FAQ</button>
            <button onclick="showSection('support')" class="block w-full text-left px-4 py-2 hover:bg-gray-200">Support</button>
          </div>
        </div>
      </div>
    </div>
  </header>

  <!-- Hero Section -->
  <section class="relative hero-bg flex flex-col items-center justify-center text-center text-white px-6">
    <h1 class="text-4xl font-bold mb-4 drop-shadow-lg">Welcome to DIVECONNECT</h1>
    <p class="text-lg text-gray-700 max-w-2xl mx-auto bg-white/100 px-4 py-2 rounded-lg">
      Connecting divers and tourists for safe, exciting, and unforgettable diving adventures in Mabini, Batangas.
      <strong>DiveConnect</strong> is a modern booking and scheduling platform for diving activities. 
      Our goal is to make it easier for tourists and diving enthusiasts to connect with 
      <strong>licensed professional divers</strong> in Mabini, Batangas.  
      <br><br>
      Through this system, you can book divers, manage appointments, and ensure 
      safe and enjoyable underwater experiences. Whether you're a beginner or an experienced diver, 
      DiveConnect is your trusted partner for ocean adventures. 🌊
    </p>
  </section>

  <!-- FAQ Modal -->
  <div id="faqModal" class="fixed inset-0 bg-black bg-opacity-60 hidden flex items-center justify-center p-4 z-50">
    <div class="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-2xl relative">
      <button onclick="closeFAQModal()" class="absolute top-4 right-4 text-gray-400 hover:text-red-600 text-2xl">&times;</button>
      
      <h2 class="text-3xl font-bold text-blue-700 mb-6 text-center">❓ Frequently Asked Questions</h2>
      
      <div class="space-y-4 text-left max-h-[70vh] overflow-y-auto pr-2">
        <details class="border rounded-lg p-4 bg-gray-50">
          <summary class="cursor-pointer font-semibold text-blue-600">How can I contact support?</summary>
          <p class="mt-2 text-gray-600">You can reach our support team by filling out the Support form found under the "Help" menu.</p>
        </details>

        <details class="border rounded-lg p-4 bg-gray-50">
          <summary class="cursor-pointer font-semibold text-blue-600">How do I reset my password?</summary>
          <p class="mt-2 text-gray-600">Click "Forgot Password" on the login page, and follow the steps sent to your email.</p>
        </details>

        <details class="border rounded-lg p-4 bg-gray-50">
          <summary class="cursor-pointer font-semibold text-blue-600">Can I update my profile information?</summary>
          <p class="mt-2 text-gray-600">Yes, go to your profile page and click "Edit Profile" to update your details.</p>
        </details>

        <details class="border rounded-lg p-4 bg-gray-50">
          <summary class="cursor-pointer font-semibold text-blue-600">Is my information secure?</summary>
          <p class="mt-2 text-gray-600">Yes, we use secure encryption and access control to protect your data at all times.</p>
        </details>
      </div>

      <div class="mt-6 text-center">
        <button onclick="closeFAQModal()" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">Close</button>
      </div>
    </div>
  </div>

  <!-- Support Section -->
  <section id="support" class="hidden bg-gray-50 py-20 px-6">
    <div class="max-w-3xl mx-auto text-center mb-12">
      <h2 class="text-4xl font-bold text-blue-700 mb-4">Need Help?</h2>
      <p class="text-gray-600 text-lg">Our support team is here to assist you. Fill out the form below or check our FAQ for quick answers.</p>
    </div>

    <div class="max-w-3xl mx-auto bg-white p-10 rounded-xl shadow-lg">
      <form action="send_support.php" method="POST" class="space-y-6">
        <div>
          <label class="block text-left text-gray-700 font-medium mb-2">Your Name</label>
          <input type="text" name="name" placeholder="Enter your full name" required
            class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-blue-400 focus:outline-none">
        </div>

        <div>
          <label class="block text-left text-gray-700 font-medium mb-2">Email Address</label>
          <input type="email" name="email" placeholder="Enter your email" required
            class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-blue-400 focus:outline-none">
        </div>

        <div>
          <label class="block text-left text-gray-700 font-medium mb-2">Message</label>
          <textarea name="message" rows="6" placeholder="Describe your issue or question..." required
            class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-blue-400 focus:outline-none resize-none"></textarea>
        </div>

        <button type="submit" class="w-full bg-blue-600 text-white font-semibold py-3 rounded-lg hover:bg-blue-700">
          📩 Send Message
        </button>
      </form>

      <div class="text-center mt-8">
        <a href="about.php" class="text-blue-600 hover:underline font-medium">← Back to About</a>
      </div>
    </div>
  </section>

  <script>
    // Dropdown toggle
    function toggleDropdown(id) {
      document.getElementById(id).classList.toggle("hidden");
    }

    // FAQ Modal
    function openFAQModal() {
      document.getElementById('faqModal').classList.remove('hidden');
      document.getElementById('faqModal').classList.add('flex');
      document.body.style.overflow = 'hidden';
    }

    function closeFAQModal() {
      document.getElementById('faqModal').classList.add('hidden');
      document.getElementById('faqModal').classList.remove('flex');
      document.body.style.overflow = '';
    }

    // Show Support section
    function showSection(sectionId) {
      document.querySelector('.hero-bg').classList.add('hidden');
      document.getElementById(sectionId).classList.remove('hidden');
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  </script>

</body>
</html>