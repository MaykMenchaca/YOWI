/* Fallback de imágenes SIN handlers inline (compatible con CSP estricta).
   Sustituye el antiguo onerror="..." de cada <img>. Usa un listener en fase de
   captura a nivel de documento porque el evento 'error' de <img> no burbujea.
   Cualquier imagen con data-fallback que falle al cargar se cambia a esa ruta. */
(function () {
  document.addEventListener(
    "error",
    function (e) {
      var el = e.target;
      if (!el || el.tagName !== "IMG") return;
      var fb = el.getAttribute("data-fallback");
      if (!fb) return;
      // Evita bucle si el propio fallback falla.
      if (el.src.indexOf(fb) !== -1) return;
      el.removeAttribute("data-fallback");
      el.src = fb;
    },
    true // captura
  );

  // Botones "reintenta" de los mensajes de error (sin onclick inline).
  document.addEventListener("click", function (e) {
    var btn = e.target.closest ? e.target.closest(".js-reload") : null;
    if (btn) location.reload();
  });
})();
