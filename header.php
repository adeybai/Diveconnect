<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>DiveConnect</title>
  <link href="./assets/css/output.css" rel="stylesheet">
  <style>
    /* Hide Google Translate banner/frame */
    .goog-te-banner-frame {
      display: none !important;
    }
    
    body {
      top: 0 !important;
    }

    /* Compact floating button - icon only */
    .translate-container {
      position: fixed;
      bottom: 20px;
      right: 20px;
      z-index: 9999;
    }

    /* Icon button */
    .translate-btn {
      width: 50px;
      height: 50px;
      background: white;
      border-radius: 50%;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.3s ease;
      font-size: 24px;
    }

    .translate-btn:hover {
      transform: scale(1.1);
      box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
    }

    /* Dropdown container */
    #google_translate_element {
      position: absolute;
      bottom: 60px;
      right: 0;
      background: white;
      padding: 12px;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
      opacity: 0;
      visibility: hidden;
      transform: translateY(10px);
      transition: all 0.3s ease;
    }

    .translate-container.active #google_translate_element {
      opacity: 1;
      visibility: visible;
      transform: translateY(0);
    }

    /* Hide Google branding */
    #google_translate_element .goog-logo-link,
    #google_translate_element .goog-te-gadget span {
      display: none !important;
    }

    #google_translate_element .goog-te-gadget {
      color: transparent !important;
      font-size: 0 !important;
    }

    /* Style dropdown */
    .goog-te-combo {
      padding: 8px 12px !important;
      border: 2px solid #e5e7eb !important;
      border-radius: 8px !important;
      background: white !important;
      color: #1e40af !important;
      font-size: 14px !important;
      font-weight: 600 !important;
      cursor: pointer !important;
      transition: all 0.3s !important;
      min-width: 150px !important;
    }

    .goog-te-combo:hover {
      background: #eff6ff !important;
      border-color: #2563eb !important;
    }

    .goog-te-combo:focus {
      outline: none !important;
      box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1) !important;
    }

    /* Mobile responsive */
    @media (max-width: 768px) {
      .translate-btn {
        width: 45px;
        height: 45px;
        font-size: 20px;
      }
      
      .goog-te-combo {
        font-size: 12px !important;
        min-width: 130px !important;
      }
    }
  </style>
</head>
<body>

<!-- Navigation here -->

<!-- Minimal icon-only translate widget -->
<div class="translate-container" id="translateContainer">
  <div class="translate-btn" onclick="toggleTranslate()">🌐</div>
  <div id="google_translate_element"></div>
</div>

<script type="text/javascript">
function googleTranslateElementInit() {
  new google.translate.TranslateElement({
    pageLanguage: 'en',
    includedLanguages: 'en,tl,de,ja,ko,zh-CN,fr,nl',
    layout: google.translate.TranslateElement.InlineLayout.SIMPLE
  }, 'google_translate_element');
}

// Toggle dropdown
function toggleTranslate() {
  document.getElementById('translateContainer').classList.toggle('active');
}

// Close on outside click
document.addEventListener('click', function(e) {
  const container = document.getElementById('translateContainer');
  if (!container.contains(e.target)) {
    container.classList.remove('active');
  }
});
</script>
<script type="text/javascript" src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>