/* Filtro por letra en la página de marcas (estado visual "active").
   Externalizado para cumplir CSP estricta (sin <script> inline). */
(function () {
  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".letter-filter-btn").forEach(function (btn) {
      btn.addEventListener("click", function () {
        if (!this.classList.contains("cursor-not-allowed")) {
          document.querySelectorAll(".letter-filter-btn").forEach(function (b) {
            b.classList.remove("active");
          });
          this.classList.add("active");
        }
      });
    });
  });
})();
