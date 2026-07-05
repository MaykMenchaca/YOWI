(function () {
  var WA = "https://wa.me/5218330000000?text=Hola%20DS%2C%20me%20interesa%20un%20producto%20de%20su%20cat%C3%A1logo.";

  document.addEventListener("DOMContentLoaded", function () {
    // --- WhatsApp: cualquier botón/enlace con texto "WhatsApp" abre el chat ---
    document.querySelectorAll("button, a").forEach(function (el) {
      var t = (el.textContent || "").toLowerCase();
      if (t.indexOf("whatsapp") > -1) {
        el.style.cursor = "pointer";
        el.addEventListener("click", function (e) {
          e.preventDefault();
          window.open(WA, "_blank");
        });
      }
    });

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
