document.addEventListener("DOMContentLoaded", () => {
  // ===== Cookie Banner =====
  const banner = document.getElementById("cookie-banner");
  const agreeBtn = document.getElementById("agree-btn");
  if (banner && agreeBtn) {
    if (!localStorage.getItem("cookieAccepted")) {
      banner.classList.remove("opacity-0");
      banner.classList.add("opacity-100");
    }

    agreeBtn.addEventListener("click", () => {
      localStorage.setItem("cookieAccepted", "true");
      banner.classList.add("opacity-0");
      banner.classList.remove("opacity-100");
    });
  }

  // ===== Mobile Menu =====
  const mobileBtn = document.getElementById('mobileMenuBtn');
  const mobileMenu = document.getElementById('mobileMenu');
  if (mobileBtn && mobileMenu) {
    mobileBtn.addEventListener('click', () => mobileMenu.classList.toggle('hidden'));
  }

  // ===== Dropdowns =====
  function toggleDropdown(id) {
    document.querySelectorAll('.absolute').forEach(drop => {
      if (drop.id !== id) drop.classList.add('hidden'); // close others
    });
    const el = document.getElementById(id);
    if (el) el.classList.toggle('hidden');
  }
  window.toggleDropdown = toggleDropdown; // para magamit sa HTML onclick

  // Close dropdown when clicking outside
  document.addEventListener('click', function (e) {
    if (!e.target.closest('.relative')) {
      document.querySelectorAll('.absolute').forEach(drop => drop.classList.add('hidden'));
    }
  });
});
