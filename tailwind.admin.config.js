/* Config de build del PANEL ADMIN (self-hosted, sin CDN).
   Replica la config inline que antes vivía en cada página admin (paleta ink/brand/lime/
   paper/cobalt + fuentes Barlow) y escanea el HTML y el JS del admin para incluir solo
   las clases realmente usadas (incluye la paleta por defecto de Tailwind: slate, green,
   red, blue, yellow, cyan, con opacidades). Salida: site/assets/css/admin.css */
module.exports = {
  content: [
    "./site/panel-4x9qz/*.html",
    "./site/assets/js/admin/*.js",
    "./site/assets/js/fuzzy.js",
  ],
  theme: {
    extend: {
      fontFamily: {
        display: ['"Barlow Condensed"', "sans-serif"],
        body: ['"Barlow"', "sans-serif"],
      },
      colors: {
        ink: "#0B0F1A",
        brand: "#1F5FD9",
        lime: "#8FD11F",
        paper: "#F7F8FA",
        cobalt: { 500: "#378ADD", 600: "#185FA5", 700: "#0D4A80" },
      },
    },
  },
  plugins: [],
};
