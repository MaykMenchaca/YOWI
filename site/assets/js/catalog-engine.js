/* DS Catalog Engine — fetch + render + filtros + búsqueda del catálogo.
   Lee desde api/products/list.php (MySQL). El JSON demo ya no se usa. */
(function (global) {
  var cache = null;

  function fetchProductos() {
    if (cache) return Promise.resolve(cache);
    var url = global.DS_API_URL ? global.DS_API_URL("api/products/list.php") : "api/products/list.php";
    return fetch(url, { credentials: "include" })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        // Soporta respuesta de la API {ok,data:[...]} o array directo (fallback)
        var list = Array.isArray(data) ? data : (data.data || []);
        cache = list;
        return list;
      });
  }

  function money(n) {
    return "$" + Number(n).toLocaleString("es-MX", { minimumFractionDigits: 2 }) + " MXN";
  }

  // Escapa HTML para prevenir XSS: el catálogo viene de MySQL editable desde el admin.
  function esc(s) {
    return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }

  function productCardHTML(p) {
    var detailUrl = "producto.html?id=" + encodeURIComponent(p.id);
    return (
      '<div class="bg-white border border-gray-200 p-4 border-b-[3px] border-b-transparent hover:border-b-lime transition flex flex-col h-full" data-product-id="' + esc(p.id) + '">' +
        '<a href="' + detailUrl + '" class="block">' +
          '<div class="h-40 bg-gray-100 mb-3 flex items-center justify-center overflow-hidden p-2">' +
            '<img class="max-h-full object-contain hover:scale-105 transition-transform duration-300" loading="lazy" src="' + esc(p.imagen) + '" alt="' + esc(p.nombre) + '" onerror="this.onerror=null;this.src=\'assets/img/producto-placeholder.svg\'"/>' +
          '</div>' +
        '</a>' +
        '<div class="text-[11px] uppercase font-extrabold text-gray-500 tracking-wide mb-1">' + esc(p.marca) + '</div>' +
        '<h3 class="font-bold text-base leading-tight mb-1"><a href="' + detailUrl + '" class="hover:text-brand">' + esc(p.nombre) + '</a></h3>' +
        '<div class="font-body text-sm text-gray-400 mb-4">' + esc(p.cantidad) + '</div>' +
        '<div class="mt-auto flex flex-col gap-3">' +
          '<div class="text-brand font-extrabold text-lg">' + money(p.precio) + '</div>' +
          '<button type="button" class="add-to-cart-btn bg-lime text-ink font-extrabold uppercase tracking-wide px-4 py-3 min-h-[44px] w-full text-sm hover:opacity-90 transition-opacity" data-product-id="' + esc(p.id) + '">Agregar al carrito</button>' +
        '</div>' +
      '</div>'
    );
  }

  function renderGrid(productos, container) {
    if (!container) return;
    container.innerHTML = productos.map(productCardHTML).join("");
  }

  function applyFilters(productos, filtros) {
    filtros = filtros || {};
    return productos.filter(function (p) {
      if (filtros.categoria && filtros.categoria.length && filtros.categoria.indexOf(p.categoria) === -1) return false;
      if (filtros.marca && filtros.marca.length && filtros.marca.indexOf(p.marca) === -1) return false;
      if (filtros.precioMax != null && p.precio > filtros.precioMax) return false;
      if (filtros.soloDisponibles && p.stock <= 0) return false;
      return true;
    });
  }

  function applySearch(productos, query) {
    if (!query) return productos;
    var q = query.trim().toLowerCase();
    if (!q) return productos;
    return productos.filter(function (p) {
      return p.nombre.toLowerCase().indexOf(q) > -1 || p.marca.toLowerCase().indexOf(q) > -1;
    });
  }

  function renderFeatured(productos, container, limit) {
    if (!container) return;
    var featured = productos.filter(function (p) { return p.destacado; });
    renderGrid((featured.length ? featured : productos).slice(0, limit || 4), container);
  }

  function renderProductDetail(id, container) {
    if (!container) return Promise.resolve(null);
    var idNum = parseInt(id, 10);
    return fetchProductos().then(function (productos) {
      var p = productos.filter(function (item) { return item.id === idNum || String(item.id) === String(id); })[0];
      if (!p) {
        container.innerHTML = '<p class="text-gray-600">Producto no encontrado.</p>';
        return null;
      }
      document.querySelectorAll("[data-field]").forEach(function (el) {
        var field = el.getAttribute("data-field");
        if (field === "precio") el.textContent = money(p.precio);
        else if (field === "imagen") el.setAttribute("src", p.imagen);
        else if (p[field] !== undefined) el.textContent = p[field];
      });
      return p;
    }).catch(function () {
      container.innerHTML = '<p class="text-gray-600">No se pudo cargar el producto. Revisa tu conexión e <button type="button" onclick="location.reload()" class="text-brand underline">reintenta</button>.</p>';
      return null;
    });
  }

  function readFilters(root) {
    var categoria = Array.prototype.map.call(
      root.querySelectorAll('input[name="categoria"]:checked'), function (el) { return el.value; }
    );
    var priceInput = root.querySelector('input[name="precio_max"]');
    var dispoInput = root.querySelector('input[name="solo_disponibles"]');
    return {
      categoria: categoria,
      precioMax: priceInput && priceInput.value ? Number(priceInput.value) : null,
      soloDisponibles: !!(dispoInput && dispoInput.checked),
    };
  }

  function bootstrapCatalogo() {
    var grid = document.getElementById("product-grid");
    if (!grid) return;
    var countEl = document.getElementById("product-count");
    var searchInput = document.getElementById("search-input");
    var filtersRoot = document.querySelector("aside") || document;

    function refresh() {
      fetchProductos().then(function (productos) {
        var result = applyFilters(productos, readFilters(filtersRoot));
        result = applySearch(result, searchInput ? searchInput.value : "");
        renderGrid(result, grid);
        if (countEl) countEl.textContent = result.length + " producto" + (result.length === 1 ? "" : "s");
      }).catch(function () {
        grid.innerHTML = '<p class="p-6 text-center text-gray-600 col-span-full">No se pudo cargar el catálogo. Revisa tu conexión e <button type="button" onclick="location.reload()" class="text-brand underline">reintenta</button>.</p>';
        if (countEl) countEl.textContent = "";
      });
    }

    filtersRoot.querySelectorAll('input[name="categoria"], input[name="precio_max"], input[name="solo_disponibles"]')
      .forEach(function (el) { el.addEventListener("change", refresh); el.addEventListener("input", refresh); });
    if (searchInput) searchInput.addEventListener("input", refresh);

    var clearBtn = document.getElementById("clear-filters-btn");
    if (clearBtn) {
      clearBtn.addEventListener("click", function () {
        filtersRoot.querySelectorAll('input[name="categoria"], input[name="solo_disponibles"]').forEach(function (el) { el.checked = false; });
        filtersRoot.querySelectorAll('input[name="precio_max"]').forEach(function (el) { el.value = el.max || ""; });
        if (searchInput) searchInput.value = "";
        refresh();
      });
    }

    refresh();
  }

  function bootstrapFeatured() {
    var container = document.getElementById("featured-products");
    if (!container) return;
    fetchProductos().then(function (productos) {
      renderFeatured(productos, container, Number(container.getAttribute("data-limit")) || 4);
    }).catch(function () {
      container.innerHTML = '<p class="p-6 text-center text-gray-600 col-span-full">No se pudieron cargar los productos destacados.</p>';
    });
  }

  function bootstrapProductDetail() {
    var container = document.getElementById("product-detail");
    if (!container) return;
    var params = new URLSearchParams(window.location.search);
    var id = params.get("id");
    if (id) renderProductDetail(id, container);
  }

  document.addEventListener("DOMContentLoaded", function () {
    bootstrapCatalogo();
    bootstrapFeatured();
    bootstrapProductDetail();
  });

  global.DSCatalog = {
    fetchProductos: fetchProductos,
    renderGrid: renderGrid,
    applyFilters: applyFilters,
    applySearch: applySearch,
    renderFeatured: renderFeatured,
    renderProductDetail: renderProductDetail,
    money: money,
  };
})(window);
