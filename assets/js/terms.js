document.addEventListener("DOMContentLoaded", () => {
  const termsCheckbox = document.getElementById("termsCheckbox");
  const openModalLink = document.getElementById("openModal");
  const termsModal = document.getElementById("termsModal");
  const acceptBtn = document.getElementById("acceptTermsBtn");
  const submitBtn = document.getElementById("submitBtn");

  // ✅ Only the "Terms and Conditions" link opens the modal
  openModalLink.addEventListener("click", (e) => {
    e.preventDefault();
    termsModal.classList.remove("hidden");
    termsModal.classList.add("flex");
  });

  // ✅ When user accepts
  acceptBtn.addEventListener("click", () => {
    termsModal.classList.add("hidden");
    termsModal.classList.remove("flex");

    // Check checkbox (but not clickable by user)
    termsCheckbox.checked = true;

    // Enable Register button
    submitBtn.disabled = false;
    submitBtn.classList.remove("bg-blue-400", "cursor-not-allowed");
    submitBtn.classList.add("bg-blue-600", "hover:bg-blue-700", "cursor-pointer");
  });

  // ✅ If user disagrees (optional use)
  window.disagreeTerms = function () {
    termsModal.classList.add("hidden");
    termsModal.classList.remove("flex");
    termsCheckbox.checked = false;
    submitBtn.disabled = true;
    submitBtn.classList.remove("bg-blue-600", "hover:bg-blue-700");
    submitBtn.classList.add("bg-blue-400", "cursor-not-allowed");
  };
});
