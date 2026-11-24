/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ['./*.php', './*/*.php', './assets/app.js'],
  theme: {
    extend: {
      colors: {
        brand: { 50: '#f0fdfa', 100: '#ccfbf1', 500: '#14b8a6', 600: '#0d9488', 700: '#0f766e', 900: '#134e4a' },
      },
    },
  },
};