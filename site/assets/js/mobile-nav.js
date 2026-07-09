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
      "md:hidden flex items-center justify-center min-w-[44px] min-h-[44px] text-white hover:text-lime transition-colors";
    btn.setAttribute("aria-label", "Abrir menú");
    btn.setAttribute("aria-expanded", "false");
    btn.setAttribute("aria-controls", "mobile-menu");
    btn.innerHTML = '<span class="material-symbols-outlined">menu</span>';

    var drawer = document.createElement("div");
    drawer.id = "mobile-menu";
    // Panel full-width debajo del header, paleta gimnasio (ink + lima).
    drawer.className = "hidden md:hidden bg-ink border-b-2 border-lime";
    var ul = document.createElement("ul");
    ul.className = "flex flex-col px-6 py-2 max-w-[1280px] mx-auto";

    Array.prototype.forEach.call(links, function (a) {
      var li = document.createElement("li");
      var na = document.createElement("a");
      na.href = a.getAttribute("href");
      na.textContent = (a.textContent || "").trim();
      var esOferta = /ofertas/i.test(na.textContent);
      na.className =
        "flex items-center min-h-[44px] font-display font-extrabold text-base uppercase tracking-wide " +
        (esOferta ? "text-lime hover:opacity-80" : "text-white hover:text-lime");
      li.appendChild(na);
      ul.appendChild(li);
    });
    drawer.appendChild(ul);

    btn.addEventListener("click", function () {
      var hidden = drawer.classList.toggle("hidden");
      btn.setAttribute("aria-expanded", String(!hidden));
    });

    iconGroup.appendChild(btn);
    // Insertar el drawer como bloque full-width DESPUÉS del header (no dentro del flex row).
    var header = nav.closest("header") || nav.parentNode;
    header.parentNode.insertBefore(drawer, header.nextSibling);
  });
})();
