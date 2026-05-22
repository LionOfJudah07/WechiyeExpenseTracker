/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ["./*.php", "./inc/**/*.php", "./assets/js/**/*.js"],
  theme: {
    extend: {
      colors: {
        primary: "#0d631b",
        secondary: "#795900",
        tertiary: "#ab1118",
        surface: "#f9f9f9",
        "surface-container-lowest": "#ffffff",
        "surface-container-low": "#f0f0f0",
        "surface-container-highest": "#e4e4e4",
        "on-surface": "#1a1c1c",
        "outline-variant": "rgba(26, 28, 28, 0.15)",
        "primary-fixed": "#2e7d32",
      },
      fontFamily: {
        display: ['"Plus Jakarta Sans"', 'sans-serif'],
        body: ['Inter', 'sans-serif'],
      },
      boxShadow: {
        ambient: '0 4px 12px rgba(0, 0, 0, 0.04), 0 8px 24px rgba(0, 0, 0, 0.04)',
      },
      backgroundImage: {
        'cta-gradient': 'linear-gradient(135deg, var(--primary), var(--primary-fixed))',
      }
    },
  },
  plugins: [],
}
