/* Carrusel de promociones del hero.
   index.html deja el hero como espacio reservado del tamaño del carrusel. Si hay banners
   activos (api/banners/list.php) se arma el carrusel ahí; si no hay o falla el fetch, se
   restaura el hero de texto por defecto (nunca se ve vacío). La lista se guarda en
   localStorage para armar el carrusel al instante en la siguiente visita. */
(function (global) {
  function apiUrl(p) { return global.DS_API_URL ? global.DS_API_URL(p) : p; }

  var escAttr = window.DSSec.escAttr; // definición única en security-utils.js
  var safeHref = window.DSSec.safeHref;
  var CACHE_KEY = "ds_banners_v1";
  var current = null; // { start, stop } del carrusel armado; el hero conserva sus listeners

  function readCache() {
    try {
      var list = JSON.parse(localStorage.getItem(CACHE_KEY) || "null");
      return Array.isArray(list) ? list : null;
    } catch (e) { return null; }
  }
  function writeCache(list) {
    try {
      if (list.length) localStorage.setItem(CACHE_KEY, JSON.stringify(list));
      else localStorage.removeItem(CACHE_KEY);
    } catch (e) { /* sin almacenamiento: solo se pierde la carga instantánea */ }
  }

  function build(hero, banners) {
    if (current) current.stop();
    var reduce = global.matchMedia && global.matchMedia("(prefers-reduced-motion: reduce)").matches;
    var n = banners.length;

    // pb-5 = los 20px que la franja azul (margin-top:-20px en index.html) se monta sobre el hero.
    hero.className = "relative bg-ink overflow-hidden pb-5";

    var slides = banners.map(function (b, i) {
      var alt = b.titulo ? escAttr(b.titulo) : "Promoción";
      var img = '<img src="' + escAttr(apiUrl(b.imagen)) + '" alt="' + alt +
        '" class="w-full h-full object-contain" ' +
        (i === 0 ? 'loading="eager" fetchpriority="high"' : 'loading="lazy"') + '/>';
      var href = safeHref(b.enlace);
      var inner = href ? '<a href="' + escAttr(href) + '" class="block w-full h-full">' + img + '</a>' : img;
      return '<div class="ds-slide absolute inset-0 transition-opacity duration-500 ' +
        (i === 0 ? "opacity-100" : "opacity-0 pointer-events-none") + '">' + inner + '</div>';
    }).join("");

    var arrows = n > 1
      ? '<button type="button" class="ds-prev absolute left-3 top-1/2 -translate-y-1/2 z-20 w-11 h-11 flex items-center justify-center bg-black/40 hover:bg-black/60 text-white rounded-full transition-colors" aria-label="Anterior"><span class="material-symbols-outlined">chevron_left</span></button>' +
        '<button type="button" class="ds-next absolute right-3 top-1/2 -translate-y-1/2 z-20 w-11 h-11 flex items-center justify-center bg-black/40 hover:bg-black/60 text-white rounded-full transition-colors" aria-label="Siguiente"><span class="material-symbols-outlined">chevron_right</span></button>'
      : "";

    // La fila de puntitos mide siempre 22px (igual que el espacio reservado de index.html),
    // aunque haya un solo banner: así el alto no cambia al aparecer el carrusel.
    var dots = '<div class="flex justify-center gap-2 pt-3 h-[22px]">' +
      (n > 1 ? banners.map(function (b, i) {
        return '<button type="button" class="ds-dot w-2.5 h-2.5 rounded-full transition-colors ' +
          (i === 0 ? "bg-lime" : "bg-white/50") + '" data-idx="' + i + '" aria-label="Promoción ' + (i + 1) + '"></button>';
      }).join("") : "") + '</div>';

    // Misma proporción que los banners (8:3) para mostrarlos completos en cualquier pantalla.
    // Los puntitos van debajo de la imagen para no tapar el texto del banner.
    hero.innerHTML = '<div class="relative w-full aspect-[8/3]">' + slides + arrows + '</div>' + dots;

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
    current = { start: start, stop: stop };
    start();
  }

  var hero = document.getElementById("hero");
  if (!hero) return;
  var defaultHTML = hero.innerHTML;
  var defaultClass = hero.getAttribute("data-default-class") || hero.className;

  function restoreDefault() {
    if (current) { current.stop(); current = null; }
    hero.className = defaultClass;
    hero.innerHTML = defaultHTML;
    hero.querySelectorAll("[data-hero-placeholder]").forEach(function (el) { el.remove(); });
    var content = hero.querySelector("[data-hero-content]");
    if (content) content.classList.remove("hidden");
  }

  hero.addEventListener("mouseenter", function () { if (current) current.stop(); });
  hero.addEventListener("mouseleave", function () { if (current) current.start(); });

  // Visita repetida: armar ya con la lista guardada (las imágenes siguen en la caché del
  // navegador) y actualizar en segundo plano solo si la lista cambió.
  var cached = readCache();
  var shown = null;
  if (cached && cached.length) {
    build(hero, cached);
    shown = JSON.stringify(cached);
  }

  // Con defer el HTML ya está listo: se pide la lista sin esperar a DOMContentLoaded.
  fetch(apiUrl("api/banners/list.php"), { credentials: "include" })
    .then(function (r) { return r.json(); })
    .then(function (j) {
      if (!j || j.ok === false) throw new Error("banners no disponibles");
      var list = Array.isArray(j.data) ? j.data : (Array.isArray(j) ? j : []);
      writeCache(list);
      if (!list.length) { restoreDefault(); return; }
      if (JSON.stringify(list) !== shown) build(hero, list);
    })
    .catch(function () {
      // Sin backend: si ya se armó con la lista guardada se deja así; si no, texto por defecto.
      if (shown === null) restoreDefault();
    });
})(window);
