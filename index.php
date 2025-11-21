<?php
session_start();
include 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>DiveConnect</title>
  <link href="./assets/css/output.css" rel="stylesheet">
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

    /* Enhanced mobile dropdown styling */
    .mobile-dropdown {
      max-height: 0;
      overflow: hidden;
      transition: max-height 0.3s ease-in-out;
    }
    
    .mobile-dropdown.active {
      max-height: 500px;
    }

    .mobile-dropdown-item {
      padding: 12px 24px;
      border-left: 3px solid transparent;
      transition: all 0.2s ease;
    }

    .mobile-dropdown-item:hover {
      background-color: rgba(59, 130, 246, 0.1);
      border-left-color: #3b82f6;
    }

    /* Smooth animations */
    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .animate-fadeIn {
      animation: fadeIn 0.3s ease-out;
    }

    @keyframes scaleIn {
      from {
        opacity: 0;
        transform: scale(0.95);
      }
      to {
        opacity: 1;
        transform: scale(1);
      }
    }

    .animate-scaleIn {
      animation: scaleIn 0.3s ease-out;
    }

    /* Enhanced mobile menu */
    #mobileMenu {
      max-height: 0;
      overflow: hidden;
      transition: max-height 0.3s ease-in-out;
    }

    #mobileMenu.active {
      max-height: 1000px;
    }
  </style>
</head>

<body class="bg-gray-100">

  <header class="bg-blue-600 text-white p-4 shadow-md relative z-40">
    <div class="container mx-auto flex items-center justify-between">
      <a href="index.php">
        <img src="assets/images/GROUP_4_-_DIVE_CONNECT-removebg-preview.png" class="h-14" alt="Logo">
      </a>

      <nav class="hidden md:flex items-center gap-2">
        <div class="relative">
          <button onclick="toggleDropdown('loginDropdown')" class="px-4 py-2 hover:bg-blue-700 rounded">LOGIN</button>
    
          <div id="loginDropdown" class="absolute right-0 mt-2 w-40 bg-white text-black rounded shadow-md hidden z-50">
            <a href="admin/login_admin.php" class="block px-4 py-2 hover:bg-gray-200">Admin</a>
            <a href="diver/login_diver.php" class="block px-4 py-2 hover:bg-gray-200">Dive Master</a>
            <a href="user/user_login.php" class="block px-4 py-2 hover:bg-gray-200">Diver</a>
          </div>
        </div>

        <div class="relative">
          <button onclick="toggleDropdown('RegisterDropdown')" class="px-4 py-2 hover:bg-blue-700 rounded">REGISTER</button>
          <div id="RegisterDropdown" class="absolute right-0 mt-2 w-40 bg-white text-black rounded shadow-md hidden z-50">
            <a href="admin/register_diver.php" class="block px-4 py-2 hover:bg-gray-200">Dive Master</a>
            <a href="user/register_user.php" class="block px-4 py-2 hover:bg-gray-200">Diver</a>
          </div>
        </div>

        <a href="https://www.tideschart.com/Philippines/Calabarzon/Province-of-Batangas/Anilao/" class="px-4 py-2 hover:bg-blue-700 rounded">TIDECHART</a>
        <a href="explore.php" class="px-4 py-2 hover:bg-blue-700 rounded">EXPLORE</a>
        <a href="user/user_login.php" class="px-4 py-2 hover:bg-blue-700 rounded">BOOK</a>
        <a href="about.php" class="px-4 py-2 hover:bg-blue-700 rounded">ABOUT</a>

        <div class="relative group">
          <button 
            onclick="toggleDropdown('helpDropdown')" 
            class="flex items-center gap-2 px-4 py-2 text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-all duration-300 shadow-md hover:shadow-lg"
          >
            ❓ <span>Help</span>
          </button>

          <div 
            id="helpDropdown" 
            class="absolute right-0 mt-2 w-44 bg-white text-gray-800 rounded-xl shadow-2xl border border-gray-200 hidden z-50 animate-fadeIn"
          >
            <button 
              onclick="openFAQModal()" 
              class="block w-full text-left px-5 py-3 hover:bg-blue-50 hover:text-blue-700 transition-all rounded-t-xl"
            >
              FAQ
            </button>
            <button 
              onclick="showSection('support')" 
              class="block w-full text-left px-5 py-3 hover:bg-blue-50 hover:text-blue-700 transition-all rounded-b-xl"
            >
              Support
            </button>
          </div>
        </div>
      </nav>

      <button id="mobileMenuBtn" class="md:hidden flex items-center px-3 py-2 border rounded text-white border-white hover:bg-blue-700 transition">
        <svg class="fill-current h-5 w-5" viewBox="0 0 20 20">
          <path d="M0 3h20v2H0zM0 9h20v2H0zM0 15h20v2H0z" />
        </svg>
      </button>
    </div>

    <div id="mobileMenu" class="md:hidden mt-2 px-4 space-y-2">
      <div>
        <button onclick="toggleMobileDropdown('loginDropdownMobile')" class="w-full text-left px-4 py-3 bg-blue-700 rounded-lg hover:bg-blue-800 transition">
          LOGIN
        </button>
        <div id="loginDropdownMobile" class="mobile-dropdown bg-blue-600 rounded-b-lg">
          <a href="admin/login_admin.php" class="mobile-dropdown-item block text-white">Admin</a>
          <a href="diver/login_diver.php" class="mobile-dropdown-item block text-white">Dive Master</a>
          <a href="user/user_login.php" class="mobile-dropdown-item block text-white">Diver</a>
        </div>
      </div>

      <div>
        <button onclick="toggleMobileDropdown('RegisterDropdownMobile')" class="w-full text-left px-4 py-3 bg-blue-700 rounded-lg hover:bg-blue-800 transition">
          REGISTER
        </button>
        <div id="RegisterDropdownMobile" class="mobile-dropdown bg-blue-600 rounded-b-lg">
          <a href="admin/register_diver.php" class="mobile-dropdown-item block text-white">Dive Master</a>
          <a href="user/register_user.php" class="mobile-dropdown-item block text-white">Diver</a>
        </div>
      </div>

      <a href="https://www.tideschart.com/Philippines/Calabarzon/Province-of-Batangas/Anilao/" class="block px-4 py-3 bg-blue-700 rounded-lg hover:bg-blue-800 transition">TIDECHART</a>
      <a href="explore.php" class="block bg-blue-700 px-4 py-3 rounded-lg hover:bg-blue-800 transition">EXPLORE</a>
      <a href="user/user_login.php" class="block bg-blue-700 px-4 py-3 rounded-lg hover:bg-blue-800 transition">BOOK</a>
      <a href="about.php" class="block bg-blue-700 px-4 py-3 rounded-lg hover:bg-blue-800 transition">ABOUT</a>

      <div>
        <button onclick="toggleMobileDropdown('helpDropdownMobile')" class="w-full text-left px-4 py-3 bg-blue-700 rounded-lg hover:bg-blue-800 transition">
          ❓ Help
        </button>
        <div id="helpDropdownMobile" class="mobile-dropdown bg-blue-600 rounded-b-lg">
          <button onclick="openFAQModal(); toggleMobileDropdown('helpDropdownMobile');" class="mobile-dropdown-item block w-full text-left text-white">FAQ</button>
          <button onclick="showSection('support'); toggleMobileDropdown('helpDropdownMobile'); toggleMobileMenu();" class="mobile-dropdown-item block w-full text-left text-white">Support</button>
        </div>
      </div>
    </div>
  </header>

  <section class="relative hero-bg flex flex-col items-center justify-center text-center text-white">
    <div class="absolute inset-0 bg-black/30"></div>
    <div class="relative z-10">
      <h2 class="text-6xl sm:text-5xl font-bold mb-6 mt-10">MABINI</h2>
      <a href="search_divers.php" class="bg-white text-blue-700 px-6 py-3 rounded-full shadow hover:bg-gray-200 font-semibold">
        Search for Dive Masters
      </a>
    </div>
  </section>

  <div id="termsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white w-[90%] md:w-[700px] max-h-[80vh] rounded-2xl shadow-2xl p-6 overflow-y-auto">
      <h2 class="text-2xl font-bold text-gray-800 mb-4">
        DIVE CONNECT — Terms and Conditions
      </h2>

      <div class="text-gray-600 text-sm leading-relaxed space-y-3">

        <p><strong>1. Acceptance of Terms</strong><br>
          By using DiveConnect, you must agree to these Terms & Conditions.
        </p>

        <p><strong>2. User Eligibility</strong><br>
          Users must be at least 18 years old to book or offer dive sessions. Minors aged 14–17 may book dives. The diver must bring a hard copy of the parental/guardian consent form, signed by the guardian, and hand it over to the assigned Dive Master prior to the dive session.
        </p>

        <p><strong>3. Diver Certification Requirement</strong><br>
          This website is exclusively for certified divers. Divers must possess and present valid certification IDs (e.g., PADI, SSI) to the Dive Master upon request. Divers without certification cannot use this website for booking.
        </p>

        <p><strong>4. Health & Safety Requirements</strong><br>
          All divers and Dive Masters must be in good health and physical condition and are responsible for honestly declaring their status. Users with pre-existing conditions must provide current medical clearance from a physician.
        </p>

        <p><strong>5. Booking & Cancellation Policies</strong><br>
          Bookings are subject to Dive Master availability. Cancellations must be made at least 24 hours in advance of the scheduled dive time. No-shows may incur non-refundable fees.
        </p>

        <p><strong>6. Payment, Subscription, & Mode of Payment</strong><br>
          - Dive Masters are required to pay a non-refundable ₱400.00 monthly subscription fee to maintain an active profile and be eligible for bookings on the DiveConnect platform.<br>
          - The only accepted forms of payment for dive session bookings are GCash and in-person (cash on hand).<br>
          - Dive Masters must attach their verified GCash QR code to their profile for electronic payment processing.
        </p>

        <p><strong>7. Liability & Risk Acknowledgement</strong><br>
          Divers acknowledge that fun diving involves inherent risks. Any liability arising from accidents or negative incidents involving the Dive Master or Diver during a booked session will be the responsibility of and answered for by the company.
        </p>

        <p><strong>8. Platform Usage & Conduct</strong><br>
          Users must provide accurate information. Abusive, fraudulent, or disruptive behavior may result in account suspension or termination.
        </p>

        <p><strong>9. Equipment & Rental Loss</strong><br>
          - The Dive Master is responsible for providing equipment rental terms, rates, and conditions upon booking confirmation.<br>
          - The Diver is fully responsible for any damage to or loss of rental equipment provided by the Dive Master during the dive session and may be required to pay for repair or replacement costs as determined by the Dive Master.
        </p>

        <p><strong>10. Data Privacy & Confidentiality</strong><br>
          User data will be handled according to the Privacy Policy. Personal information is shared only with the assigned Dive Master for booking purposes.
        </p>

        <p><strong>11. Amendments & Updates</strong><br>
          DiveConnect reserves the right to update these Terms and Conditions at any time. Users will be notified via email or system notification.
        </p>

      </div>

      <div class="mt-6 flex items-center gap-3">
        <input type="checkbox" id="agreeTerms" class="w-5 h-5">
        <label for="agreeTerms" class="text-gray-700 text-sm">
          I agree to the Terms and Conditions
        </label>
      </div>

      <div class="mt-6 flex justify-end gap-3">
        <button id="acceptTermsBtn" disabled
          class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition disabled:opacity-50">
          I Agree
        </button>
      </div>

    </div>
  </div>


  <div id="faqModal" class="hidden fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center p-4 z-50 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-2xl relative animate-scaleIn border border-gray-200">
      <button onclick="closeFAQModal()" class="absolute top-4 right-4 text-gray-400 hover:text-red-600 text-2xl transition-colors">&times;</button>
      
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
        <button onclick="closeFAQModal()" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition">
          Close
        </button>
      </div>
    </div>
  </div>

  <section id="support" class="hidden bg-gray-50 py-20 px-6">
    <div class="max-w-3xl mx-auto text-center mb-12">
      <h2 class="text-4xl font-bold text-blue-700 mb-4">Need Help?</h2>
      <p class="text-gray-600 text-lg">
        Our support team is here to assist you. Fill out the form below or check our FAQ for quick answers.
      </p>
    </div>

    <div class="max-w-3xl mx-auto bg-white p-10 rounded-xl shadow-lg">
      <form action="send_support.php" method="POST" class="space-y-6">
        <div>
          <label class="block text-left text-gray-700 font-medium mb-2">Your Name</label>
          <input type="text" name="name" placeholder="Enter your full name" required
            class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-blue-400 focus:outline-none transition">
        </div>

        <div>
          <label class="block text-left text-gray-700 font-medium mb-2">Email Address</label>
          <input type="email" name="email" placeholder="Enter your email" required
            class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-blue-400 focus:outline-none transition">
        </div>

        <div>
          <label class="block text-left text-gray-700 font-medium mb-2">Message</label>
          <textarea name="message" rows="6" placeholder="Describe your issue or question..." required
            class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-blue-400 focus:outline-none transition resize-none"></textarea>
        </div>

        <button type="submit"
          class="w-full bg-blue-600 text-white font-semibold py-3 rounded-lg hover:bg-blue-700 transition">
          📩 Send Message
        </button>
      </form>

      <div class="text-center mt-8">
        <a href="index.php" class="text-blue-600 hover:underline font-medium">
          ← Back to Home
        </a>
      </div>
    </div>
  </section>

  <script>
    // Mobile Menu Toggle
    document.getElementById('mobileMenuBtn').addEventListener('click', function() {
      toggleMobileMenu();
    });

    function toggleMobileMenu() {
      const mobileMenu = document.getElementById('mobileMenu');
      mobileMenu.classList.toggle('active');
    }

    // Enhanced Mobile Dropdown Toggle
    function toggleMobileDropdown(id) {
      const dropdown = document.getElementById(id);
      
      // Close other mobile dropdowns
      const allMobileDropdowns = ['loginDropdownMobile', 'RegisterDropdownMobile', 'helpDropdownMobile'];
      allMobileDropdowns.forEach(dropdownId => {
        if (dropdownId !== id) {
          const otherDropdown = document.getElementById(dropdownId);
          if (otherDropdown && otherDropdown.classList.contains('active')) {
            otherDropdown.classList.remove('active');
          }
        }
      });

      // Toggle current dropdown
      dropdown.classList.toggle('active');
    }

    // Desktop Dropdown Toggle
    function toggleDropdown(id) {
      const allDropdowns = ['loginDropdown', 'RegisterDropdown', 'helpDropdown'];
      
      allDropdowns.forEach(dropdownId => {
        if (dropdownId !== id) {
          const dropdown = document.getElementById(dropdownId);
          if (dropdown && !dropdown.classList.contains('hidden')) {
            dropdown.classList.add('hidden');
          }
        }
      });

      document.getElementById(id).classList.toggle("hidden");
    }

    // Terms and Conditions Modal
    document.addEventListener("DOMContentLoaded", function() {
      const modal = document.getElementById("termsModal");
      const checkbox = document.getElementById("agreeTerms");
      const agreeBtn = document.getElementById("acceptTermsBtn");

      if (!sessionStorage.getItem("termsAccepted")) {
        modal.classList.remove("hidden");
        modal.classList.add("flex");
      }

      if (checkbox && agreeBtn) {
        checkbox.addEventListener("change", () => {
          agreeBtn.disabled = !checkbox.checked;
        });
      }

      if (agreeBtn) {
        agreeBtn.addEventListener("click", () => {
          modal.classList.add("hidden");
          sessionStorage.setItem("termsAccepted", "true");
        });
      }
    });

    function showSection(sectionId) {
      document.querySelectorAll("main, section").forEach(sec => {
        if (!['faq', 'support'].includes(sec.id)) sec.classList.add("hidden");
      });

      document.getElementById(sectionId).classList.remove("hidden");
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function openFAQModal() {
      const helpDropdown = document.getElementById('helpDropdown');
      if (helpDropdown) {
        helpDropdown.classList.add('hidden');
      }
      const modal = document.getElementById('faqModal');
      modal.classList.remove('hidden');
      modal.classList.add('flex');
      document.body.style.overflow = 'hidden';
    }

    function closeFAQModal() {
      const modal = document.getElementById('faqModal');
      modal.classList.add('hidden');
      modal.classList.remove('flex');
      document.body.style.overflow = '';
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(event) {
      const isDropdownButton = event.target.closest('button[onclick*="toggleDropdown"]');
      if (!isDropdownButton) {
        const allDropdowns = ['loginDropdown', 'RegisterDropdown', 'helpDropdown'];
        allDropdowns.forEach(dropdownId => {
          const dropdown = document.getElementById(dropdownId);
          if (dropdown && !dropdown.classList.contains('hidden')) {
            dropdown.classList.add('hidden');
          }
        });
      }
    });
  </script>

</body>
</html>