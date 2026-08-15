(function () {
  document.addEventListener("DOMContentLoaded", function () {
    // El enlace/botón de WhatsApp ya no se detecta por texto ("cualquier cosa que diga
    // WhatsApp"): eso competía con el href real del elemento y, en la ficha de producto,
    // secuestraba el botón "Comprar por WhatsApp" ANTES de que catalog-engine.js le
    // pusiera el mensaje con el nombre/sabor/precio reales. Ahora cada botón de WhatsApp
    // se marca explícitamente con data-wa-link y settings-inject.js le arma el href con
    // el número vigente — este archivo ya no necesita tocarlo.

    // --- Reveal con stagger (nunca oculta contenido si algo falla) ---
    var reduce = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    var cards = document.querySelectorAll(".product-card, .brand-card");
    if (reduce || !("IntersectionObserver" in window) || !cards.length) return;

    var st = document.createElement("style");
    st.textContent =
      ".reveal-init{opacity:0;transform:translateY(12px);transition:opacity .45s cubic-bezier(.23,1,.32,1),transform .45s cubic-bezier(.23,1,.32,1);}" +
      ".reveal-in{opacity:1!important;transform:none!important;}";
    document.head.appendChild(st);

    cards.forEach(function (c) { c.classList.add("reveal-init"); });

    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) {
          var el = e.target;
          var sibs = Array.prototype.slice.call(el.parentElement.children);
          var idx = sibs.indexOf(el);
          el.style.transitionDelay = Math.min(idx, 6) * 55 + "ms";
          el.classList.add("reveal-in");
          io.unobserve(el);
        }
      });
    }, { threshold: 0.08, rootMargin: "0px 0px -40px 0px" });

    cards.forEach(function (c) { io.observe(c); });

    // Salvaguarda: si algo impide el observer, revela todo tras 1.5s
    setTimeout(function () {
      cards.forEach(function (c) { c.classList.add("reveal-in"); });
    }, 1500);
  });
})();
