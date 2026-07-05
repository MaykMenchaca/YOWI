/* Nav móvil: genera un botón hamburguesa + drawer a partir de la nav de categorías
   de escritorio ya presente en el header. Una sola fuente de verdad para las 8 páginas.
   Idempotente: si la página ya trae su propio #mobile-menu (index.html), no hace nada. */
(function () {
  "use strict";

  document.addEventListener("DOMContentLoaded", function () {
    if (document.getElementById("mobile-menu")) return; // ya existe (hardcodeado)

    var nav = document.querySelector("header nav");
    if (!nav) return;
    var links = nav.querySelectorAll("a");
    if (!links.length) return;

    // Grupo de iconos del header (carrito/cuenta) donde se inserta la hamburguesa.
    var iconGroup = document.querySelector(
      'header .flex.items-center.gap-4, header .flex.items-center.gap-2'
    );
    if (!iconGroup) return;

    var btn = document.createElement("button");
    btn.type = "button";
    btn.className =
      "md:hidden flex items-center justify-center min-w-[44px] min-h-[44px] text-primary";
    btn.setAttribute("aria-label", "Abrir menú");
    btn.setAttribute("aria-expanded", "false");
    btn.setAttribute("aria-controls", "mobile-menu");
    btn.innerHTML = '<span class="material-symbols-outlined">menu</span>';

    var drawer = document.createElement("div");
    drawer.id = "mobile-menu";
    drawer.className = "hidden md:hidden border-t border-outline-variant/30 bg-white";
    var ul = document.createElement("ul");
    ul.className = "flex flex-col px-4 py-2 max-w-[1280px] mx-auto";

    Array.prototype.forEach.call(links, function (a) {
      var li = document.createElement("li");
      var na = document.createElement("a");
      na.href = a.getAttribute("href");
      na.textContent = (a.textContent || "").trim();
      var esOferta = /ofertas/i.test(na.textContent);
      na.className =
        "flex items-center min-h-[44px] font-semibold text-sm uppercase tracking-wide " +
        (esOferta ? "text-[#ba1a1a] hover:opacity-80" : "text-on-surface-variant hover:text-primary");
      li.appendChild(na);
      ul.appendChild(li);
    });
    drawer.appendChild(ul);

    btn.addEventListener("click", function () {
      var hidden = drawer.classList.toggle("hidden");
      btn.setAttribute("aria-expanded", String(!hidden));
    });

    iconGroup.appendChild(btn);
    nav.parentNode.insertBefore(drawer, nav.nextSibling);
  });
})();
