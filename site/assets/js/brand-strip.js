/* Franja "Marcas que manejamos" de la portada: nombres de las marcas con productos
   activos, cada uno enlazado al catálogo filtrado por esa marca. Si la lista falla o
   viene vacía, la sección se queda oculta (empieza con el atributo hidden). */
(function (global) {
  var MAX = 8;
  var esc = global.DSSec.esc;
  var escAttr = global.DSSec.escAttr;

  document.addEventListener("DOMContentLoaded", function () {
    var section = document.querySelector("[data-brand-section]");
    var box = document.getElementById("brand-strip");
    if (!section || !box) return;
    var url = global.DS_API_URL ? global.DS_API_URL("api/brands/list.php") : "api/brands/list.php";
    fetch(url, { credentials: "include" })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (j) {
        var list = (j && Array.isArray(j.data) ? j.data : [])
          .filter(function (b) { return b && b.nombre && Number(b.productos) > 0; })
          .slice(0, MAX);
        if (!list.length) return;
        box.innerHTML = list.map(function (b) {
          return '<a href="catalogo.html?marca=' + escAttr(encodeURIComponent(b.nombre)) + '" ' +
            'class="font-display font-extrabold uppercase text-xl sm:text-2xl tracking-wide text-gray-600 hover:text-brand transition-colors min-h-[44px] inline-flex items-center">' +
            esc(b.nombre) + '</a>';
        }).join("");
        section.hidden = false;
      })
      .catch(function () { /* sin marcas: la franja sigue oculta */ });
  });
})(window);
