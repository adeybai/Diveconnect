// assets/js/i18n-config.js
(function () {
  // Supported languages (for now: EN + PH + DE + JA + KO + ZH)
  const SUPPORTED_LANGS = ["en", "ph", "de", "ja", "ko", "zh"];

  function normalizeLang(lang) {
    if (!lang) return "en";
    lang = lang.toLowerCase();

    // map Filipino codes
    if (["fil", "tl"].includes(lang)) return "ph";

    if (!SUPPORTED_LANGS.includes(lang)) {
      if (lang.startsWith("en")) return "en";
      if (lang.startsWith("ph")) return "ph";
      return "en";
    }
    return lang;
  }

  let currentLang = null;

  function applyTranslations() {
    if (!window.i18next || !i18next.isInitialized) return;

    document.querySelectorAll("[data-i18n]").forEach((el) => {
      const key = el.getAttribute("data-i18n");
      let translated = i18next.t(key);

      if (
        typeof translated === "string" &&
        (translated.includes("<br") ||
          translated.includes("<a ") ||
          translated.includes("<strong"))
      ) {
        el.innerHTML = translated;
      } else {
        el.textContent = translated;
      }
    });
  }
  window.applyTranslations = applyTranslations;

  function loadLanguage(lang) {
    lang = normalizeLang(lang);

    if (currentLang === lang && window.i18next && i18next.isInitialized) {
      applyTranslations();
      return;
    }

    // ✅ relative path, works for index.php & about.php sa root
    const url = "assets/lang/" + lang + ".json";

    fetch(url, { cache: "no-cache" })
      .then((res) => {
        if (!res.ok) throw new Error("Lang file not found: " + url);
        return res.json();
      })
      .then((data) => {
        currentLang = lang;
        localStorage.setItem("selectedLanguage", lang);

        if (!window.i18next || !i18next.init) {
          console.error("i18next not loaded yet");
          return;
        }

        if (!i18next.isInitialized) {
          i18next
            .init({
              lng: lang,
              fallbackLng: "en",
              debug: false,
              resources: {
                [lang]: { translation: data }
              }
            })
            .then(() => {
              applyTranslations();
              console.log("i18next initialized →", lang);
            });
        } else {
          i18next.addResourceBundle(lang, "translation", data, true, true);
          i18next.changeLanguage(lang).then(() => {
            applyTranslations();
            console.log("language changed →", lang);
          });
        }
      })
      .catch((err) => {
        console.error(err);
        if (lang !== "en") {
          loadLanguage("en");
        }
      });
  }

  // Dropdown: changeLanguage('ph'), etc.
  window.changeLanguage = function (lang) {
    loadLanguage(lang);
  };

  document.addEventListener("DOMContentLoaded", function () {
    let savedLang = localStorage.getItem("selectedLanguage");
    if (!savedLang) {
      const browser = (navigator.language || "en").toLowerCase();
      savedLang = normalizeLang(browser);
      localStorage.setItem("selectedLanguage", savedLang);
    }
    loadLanguage(savedLang);
  });
})();
