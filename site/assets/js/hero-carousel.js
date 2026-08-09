/* Carrusel de promociones del hero.
   Si hay banners activos (api/banners/list.php), reemplaza el contenido del hero
   por un carrusel de imágenes que rota solo. Si no hay banners o falla el fetch,
   deja el hero por defecto (nunca se ve vacío). */
(function (global) {
  function apiUrl(p) { return global.DS_API_URL ? global.DS_API_URL(p) : p; }

  var escAttr = window.DSSec.escAttr; // definición única en security-utils.js
  var safeHref = window.DSSec.safeHref;

  function build(hero, banners) {
    var reduce = global.matchMedia && global.matchMedia("(prefers-reduced-motion: reduce)").matches;
    var n = banners.length;

    hero.className = "relative bg-ink overflow-hidden min-h-[320px] md:min-h-[520px]";

    var slides = banners.map(function (b, i) {
      var alt = b.titulo ? escAttr(b.titulo) : "Promoción";
      var img = '<img src="' + escAttr(apiUrl(b.imagen)) + '" alt="' + alt +
        '" class="w-full h-full object-cover" loading="' + (i === 0 ? "eager" : "lazy") + '"/>';
      var href = safeHref(b.enlace);
      var inner = href ? '<a href="' + escAttr(href) + '" class="block w-full h-full">' + img + '</a>' : img;
      return '<div class="ds-slide absolute inset-0 transition-opacity duration-500 ' +
        (i === 0 ? "opacity-100" : "opacity-0 pointer-events-none") + '">' + inner + '</div>';
    }).join("");

    var arrows = n > 1
      ? '<button type="button" class="ds-prev absolute left-3 top-1/2 -translate-y-1/2 z-20 w-11 h-11 flex items-center justify-center bg-black/40 hover:bg-black/60 text-white rounded-full transition-colors" aria-label="Anterior"><span class="material-symbols-outlined">chevron_left</span></button>' +
        '<button type="button" class="ds-next absolute right-3 top-1/2 -translate-y-1/2 z-20 w-11 h-11 flex items-center justify-center bg-black/40 hover:bg-black/60 text-white rounded-full transition-colors" aria-label="Siguiente"><span class="material-symbols-outlined">chevron_right</span></button>'
      : "";

    var dots = n > 1
      ? '<div class="absolute bottom-4 left-0 right-0 flex justify-center gap-2 z-20">' +
        banners.map(function (b, i) {
          return '<button type="button" class="ds-dot w-2.5 h-2.5 rounded-full transition-colors ' +
            (i === 0 ? "bg-lime" : "bg-white/50") + '" data-idx="' + i + '" aria-label="Promoción ' + (i + 1) + '"></button>';
        }).join("") + '</div>'
      : "";

    hero.innerHTML = '<div class="relative w-full h-full min-h-[320px] md:min-h-[520px]">' + slides + arrows + dots + '</div>';

    var idx = 0, timer = null;
    var slideEls = hero.querySelectorAll(".ds-slide");
    var dotEls = hero.querySelectorAll(".ds-dot");

    function show(i) {
      idx = (i + n) % n;
      slideEls.forEach(function (el, k) {
        var on = k === idx;
        el.classList.toggle("opacity-100", on);
        el.classList.toggle("opacity-0", !on);
        el.classList.toggle("pointer-events-none", !on);
      });
      dotEls.forEach(function (el, k) {
        el.classList.toggle("bg-lime", k === idx);
        el.classList.toggle("bg-white/50", k !== idx);
      });
    }
    function next() { show(idx + 1); }
    function start() { if (reduce || n < 2) return; stop(); timer = setInterval(next, 5000); }
    function stop() { if (timer) { clearInterval(timer); timer = null; } }

    var b1 = hero.querySelector(".ds-next"); if (b1) b1.addEventListener("click", function () { next(); start(); });
    var b2 = hero.querySelector(".ds-prev"); if (b2) b2.addEventListener("click", function () { show(idx - 1); start(); });
    dotEls.forEach(function (el) {
      el.addEventListener("click", function () { show(parseInt(el.getAttribute("data-idx"), 10)); start(); });
    });
    hero.addEventListener("mouseenter", stop);
    hero.addEventListener("mouseleave", start);
    start();
  }

  document.addEventListener("DOMContentLoaded", function () {
    var hero = document.getElementById("hero");
    if (!hero) return;
    fetch(apiUrl("api/banners/list.php"), { credentials: "include" })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        var list = j && j.data ? j.data : (Array.isArray(j) ? j : []);
        if (list && list.length) build(hero, list);
      })
      .catch(function () { /* sin backend/banners: se queda el hero por defecto */ });
  });
})(window);
