/* DS Experience Layer — barra de progreso, parallax de hero, reveals de seccion.
   No toca el visor 3D (product3d.js). Seguro ante prefers-reduced-motion. */
(function () {
  var reduce = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  document.addEventListener("DOMContentLoaded", function () {
    // 1. Barra de progreso de scroll
    var bar = document.createElement("div");
    bar.className = "ds-progress";
    document.body.appendChild(bar);
    var ticking = false;
    function updateProgress() {
      var h = document.documentElement;
      var max = h.scrollHeight - h.clientHeight;
      var top = h.scrollTop || window.pageYOffset || 0;
      bar.style.width = (max > 0 ? (top / max) * 100 : 0) + "%";
      ticking = false;
    }
    window.addEventListener("scroll", function () {
      if (!ticking) { window.requestAnimationFrame(updateProgress); ticking = true; }
    }, { passive: true });
    updateProgress();

    // 2. Parallax sutil del hero (solo desktop, no reduced-motion)
    var layers = document.querySelectorAll(".ds-parallax");
    var desktop = window.matchMedia("(min-width: 769px)").matches;
    if (!reduce && desktop && layers.length) {
      var pTick = false;
      function updateParallax() {
        var y = window.pageYOffset;
        layers.forEach(function (el) {
          var speed = parseFloat(el.getAttribute("data-speed")) || 0.25;
          el.style.transform = "translate3d(0," + (y * speed) + "px,0)";
        });
        pTick = false;
      }
      window.addEventListener("scroll", function () {
        if (!pTick) { window.requestAnimationFrame(updateParallax); pTick = true; }
      }, { passive: true });
    }

    // 3. Reveal de secciones (titulos h2). Las cards ya las anima enhance.js.
    var targets = document.querySelectorAll("[data-reveal], main section > h2, main section > div > h2");
    if (reduce || !("IntersectionObserver" in window) || !targets.length) return;
    targets.forEach(function (t) { t.classList.add("ds-reveal"); });
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) { e.target.classList.add("ds-reveal-in"); io.unobserve(e.target); }
      });
    }, { threshold: 0.1, rootMargin: "0px 0px -40px 0px" });
    targets.forEach(function (t) { io.observe(t); });
    // Salvaguarda: revela todo tras 1.5s si algo falla
    setTimeout(function () {
      targets.forEach(function (t) { t.classList.add("ds-reveal-in"); });
    }, 1500);
  });
})();
