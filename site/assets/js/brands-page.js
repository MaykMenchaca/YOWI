/* Página pública de Marcas — llena el grid desde api/brands/list.php y conecta
   el buscador + el filtro por letra. Si falla o no hay marcas, muestra un aviso. */
(function (global) {
  function apiUrl(p) { return global.DS_API_URL ? global.DS_API_URL(p) : p; }

  function esc(s) {
    return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }

  // Ignora acentos/mayúsculas para comparar.
  function norm(s) {
    return String(s || "").normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();
  }

  // Solo http/https o rutas relativas; bloquea javascript:, data:, etc.
  function safeHref(s) {
    var v = String(s == null ? "" : s).trim();
    if (!v) return "";
    var m = /^([a-z][a-z0-9+.\-]*)\s*:/i.exec(v);
    if (m && m[1].toLowerCase() !== "http" && m[1].toLowerCase() !== "https") return "";
    return v;
  }

  var grid = null;
  var searchInput = null;
  var letterBtns = [];
  var allBrands = [];
  var activeLetter = "TODO";

  function cardHTML(b) {
    var src = b.imagen ? esc(apiUrl(b.imagen)) : "assets/img/marca-placeholder.svg";
    var count = b.productos === 1 ? "1 producto" : (b.productos + " productos");
    var href = safeHref(b.enlace);
    var open = href ? '<a href="' + esc(href) + '" target="_blank" rel="noopener noreferrer"' : "<div";
    var close = href ? "</a>" : "</div>";
    return open + ' class="brand-card bg-white rounded-lg border border-gray-200 p-6 flex flex-col items-center justify-center text-center aspect-square relative overflow-hidden group">' +
      '<div class="w-20 h-20 mb-4 bg-gray-100 rounded-full flex items-center justify-center overflow-hidden p-2">' +
        '<img class="w-full h-full object-contain" loading="lazy" src="' + src + '" alt="' + esc(b.nombre) + '"/>' +
      '</div>' +
      '<h3 class="font-display font-extrabold uppercase tracking-wide text-xl text-ink mb-1 truncate w-full">' + esc(b.nombre) + '</h3>' +
      '<p class="font-body text-sm text-gray-500">' + count + '</p>' +
      '<div class="absolute inset-0 bg-primary/0 group-hover:bg-primary/5 transition-colors pointer-events-none"></div>' +
    close;
  }

  function render() {
    if (!grid) return;
    var q = norm(searchInput ? searchInput.value : "");
    var list = allBrands.filter(function (b) {
      var n = norm(b.nombre);
      if (activeLetter !== "TODO" && n.charAt(0).toUpperCase() !== activeLetter) return false;
      if (q && n.indexOf(q) === -1) return false;
      return true;
    });
    if (!list.length) {
      grid.innerHTML = '<p style="grid-column:1/-1" class="text-center text-gray-500 py-10">No se encontraron marcas.</p>';
      return;
    }
    grid.innerHTML = list.map(cardHTML).join("");
  }

  // Activa solo las letras que tienen marcas; desactiva el resto.
  function syncLetters() {
    var available = {};
    allBrands.forEach(function (b) { available[norm(b.nombre).charAt(0).toUpperCase()] = true; });
    letterBtns.forEach(function (btn) {
      var t = btn.textContent.trim().toUpperCase();
      if (t === "TODO") return;
      if (available[t]) {
        btn.classList.remove("opacity-50", "cursor-not-allowed");
      } else {
        btn.classList.add("opacity-50", "cursor-not-allowed");
      }
    });
  }

  function bindControls() {
    if (searchInput) {
      searchInput.addEventListener("input", render);
    }
    letterBtns.forEach(function (btn) {
      btn.addEventListener("click", function () {
        if (btn.classList.contains("cursor-not-allowed")) return;
        letterBtns.forEach(function (b) { b.classList.remove("active"); });
        btn.classList.add("active");
        activeLetter = btn.textContent.trim().toUpperCase();
        render();
      });
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    grid = document.getElementById("brands-grid");
    if (!grid) return;
    searchInput = document.getElementById("brand-search");
    letterBtns = Array.prototype.slice.call(document.querySelectorAll(".letter-filter-btn"));
    bindControls();

    fetch(apiUrl("api/brands/list.php"), { credentials: "include" })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        allBrands = (j && j.ok && Array.isArray(j.data)) ? j.data : [];
        if (!allBrands.length) {
          grid.innerHTML = '<p style="grid-column:1/-1" class="text-center text-gray-500 py-10">Aún no hay marcas cargadas.</p>';
          return;
        }
        syncLetters();
        render();
      })
      .catch(function () {
        grid.innerHTML = '<p style="grid-column:1/-1" class="text-center text-gray-500 py-10">No se pudieron cargar las marcas. Recarga la página para reintentar.</p>';
      });
  });
})(window);
