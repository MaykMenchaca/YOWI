/* Animaciones de scroll de la portada (GSAP + ScrollTrigger, guardados en
   assets/vendor/gsap/). Cada elemento entra una sola vez, solo con opacity/transform.
   El estado oculto lo pone este script: si GSAP no carga o el visitante pidió reducir el
   movimiento, no se oculta nada. enhance.js y experience.js no animan esta página
   (<html data-motion="gsap">) para no animar dos veces lo mismo. */
(function (global) {
  var gsap = global.gsap;
  var ScrollTrigger = global.ScrollTrigger;
  if (!gsap || !ScrollTrigger) return;
  gsap.registerPlugin(ScrollTrigger);

  var EASE = "power3.out";
  // transition:none mientras anima: las clases "transition" de Tailwind (tarjetas de
  // categoría) interpolarían cada cuadro de GSAP y la entrada se sentiría trabada.
  var HIDDEN = { opacity: 0, transition: "none" };
  var DONE = "opacity,transform,transition";

  function hide(targets, y) {
    gsap.set(targets, Object.assign({ y: y }, HIDDEN));
  }

  // Entran en cascada conforme llegan a la pantalla (en celular, fila por fila).
  function batchReveal(targets, y) {
    if (!targets.length) return;
    hide(targets, y);
    ScrollTrigger.batch(targets, {
      start: "top 90%",
      once: true,
      onEnter: function (batch) {
        gsap.to(batch, { opacity: 1, y: 0, duration: 0.5, ease: EASE, stagger: 0.05, clearProps: DONE });
      },
    });
  }

  gsap.matchMedia().add("(prefers-reduced-motion: no-preference)", function () {
    // Franja azul de confianza
    document.querySelectorAll("[data-motion-stagger]").forEach(function (group) {
      var items = group.children;
      hide(items, 12);
      gsap.to(items, {
        opacity: 1, y: 0, duration: 0.45, ease: EASE, stagger: 0.06, clearProps: DONE,
        scrollTrigger: { trigger: group, start: "top 90%", once: true },
      });
    });

    // Títulos de sección: el título sube y la barrita verde crece desde el centro.
    document.querySelectorAll("[data-motion-title]").forEach(function (wrap) {
      var title = wrap.querySelector("h2");
      var bar = wrap.querySelector(".bg-lime");
      var tl = gsap.timeline({ scrollTrigger: { trigger: wrap, start: "top 88%", once: true } });
      if (title) {
        hide(title, 16);
        tl.to(title, { opacity: 1, y: 0, duration: 0.5, ease: EASE, clearProps: DONE });
      }
      if (bar) {
        gsap.set(bar, { opacity: 0, scaleX: 0.3 });
        tl.to(bar, { opacity: 1, scaleX: 1, duration: 0.45, ease: EASE, clearProps: "opacity,transform" }, "-=0.3");
      }
    });

    // Tarjetas de categorías
    batchReveal(Array.prototype.slice.call(document.querySelectorAll("[data-motion-cards] > *")), 16);

    // "Más vendidos" llega después desde la API: se anima en cuanto catalog-engine.js
    // inserta las tarjetas, y se recalculan las posiciones (la página creció).
    var featured = document.getElementById("featured-products");
    if (featured) {
      var mo = new MutationObserver(function () {
        var cards = Array.prototype.slice.call(featured.children);
        if (!cards.length) return;
        mo.disconnect();
        batchReveal(cards, 16);
        ScrollTrigger.refresh();
      });
      mo.observe(featured, { childList: true });
      if (featured.children.length) {
        mo.disconnect();
        batchReveal(Array.prototype.slice.call(featured.children), 16);
      }
      return function () { mo.disconnect(); };
    }
  });
})(window);
