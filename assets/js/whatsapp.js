// Select inputs
const inputs = [
  { el: document.querySelector("#phone"), errorMsg: "Phone number is required" },
  { el: document.querySelector("#whatsapp"), errorMsg: "WhatsApp number is required" }
];

inputs.forEach(inputObj => {
  const input = inputObj.el;
  const iti = window.intlTelInput(input, {
    initialCountry: "ph",
    separateDialCode: true,
    preferredCountries: ["ph", "us", "gb", "au"],
    utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js"
  });

  // Optional: remove leading 0s automatically
  input.addEventListener("input", () => {
    if (input.value.startsWith("0")) {
      input.value = input.value.replace(/^0+/, "");
    }
  });

  // Handle form submission
  const form = input.closest("form");
  form.addEventListener("submit", function (event) {
    const dialCode = "+" + iti.getSelectedCountryData().dialCode;
    const localNumber = input.value.trim();

    // Combine selected dial code + input
    input.value = dialCode + localNumber;

    
    if (localNumber === "") {
      event.preventDefault();
      Swal.fire({
        icon: 'error',
        title: 'Oops...',
        text: inputObj.errorMsg + ". This field cannot be empty.",
        confirmButtonColor: '#3085d6'
      });
      return false;
    }

   
  });
});
