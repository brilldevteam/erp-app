import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';

const customBackend = {
  type: 'backend',
  init: function(services, backendOptions) {
    this.services = services;
    this.options = backendOptions;
  },
  read: function(language, namespace, callback) {
    const loadPath = window.route ? window.route('languages.translations', language) : `/translations/${language}`;
    
    fetch(loadPath)
      .then(response => {
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
      })
      .then(data => {
        if (data.layoutDirection) {
          document.documentElement.dir = data.layoutDirection;
        }
        callback(null, data.translations);
      })
      .catch(error => callback(error, null));
  }
};

const userLang = window.auth?.user?.lang || window.auth?.lang || 'en';
i18n
    .use(customBackend)
    .use(initReactI18next)
    .init({
        lng: userLang,
        fallbackLng: userLang,
        interpolation: {
            escapeValue: false,
        }
    });

export default i18n;