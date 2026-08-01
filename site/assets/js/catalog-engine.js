/* DS Catalog Engine — fetch + render + filtros + búsqueda del catálogo.
   Lee desde api/products/list.php (MySQL). Si el backend PHP no responde
   (p. ej. desplegado en Vercel sin API), cae a un catálogo demo estático
   (assets/data/productos-demo.json) y muestra un banner de "vista previa". */
(function (global) {
  var cache = null;
  var DEMO_URL = "assets/data/productos-demo.json";

  function loadDemo() {
    return fetch(DEMO_URL)
      .then(function (res) { return res.json(); })
      .then(function (list) {
        cache = Array.isArray(list) ? list : [];
        showDemoBanner();
        return cache;
      })
      .catch(function () { cache = []; return cache; });
  }

  function fetchProductos() {
    if (cache) return Promise.resolve(cache);
    var url = global.DS_API_URL ? global.DS_API_URL("api/products/list.php") : "api/products/list.php";
    return fetch(url, { credentials: "include" })
      .then(function (res) {
        if (!res.ok) throw new Error("HTTP " + res.status);
        return res.json();
      })
      .then(function (data) {
        // Soporta respuesta de la API {ok,data:[...]} o array directo (fallback)
        var list = Array.isArray(data) ? data : (data.data || []);
        if (!list.length) throw new Error("empty"); // sin backend/productos → demo
        cache = list;
        return list;
      })
      .catch(function () { return loadDemo(); });
  }

  // Banner discreto cuando se está usando el catálogo demo (sin backend real).
  function showDemoBanner() {
    if (document.getElementById("ds-demo-banner")) return;
    var bar = document.createElement("div");
    bar.id = "ds-demo-banner";
    bar.setAttribute("role", "status");
    bar.textContent = "Vista previa · catálogo de demostración con precios de ejemplo — aún no conectado al inventario real.";
    bar.style.cssText = "background:#8FD11F;color:#0B0F1A;font:600 13px/1.4 Barlow,system-ui,sans-serif;text-align:center;padding:8px 16px;";
    if (document.body) document.body.insertBefore(bar, document.body.firstChild);
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

  // Presentación: combina cantidad + unidad ("300" + "g" -> "300 g").
  function qtyLabel(p) {
    var c = String(p.cantidad == null ? "" : p.cantidad).trim();
    var u = String(p.unidad == null ? "" : p.unidad).trim();
    return u ? (c + " " + u).trim() : c;
  }

  function productCardHTML(p) {
    var detailUrl = "producto.html?id=" + encodeURIComponent(p.id);
    return (
      '<div class="bg-white border border-gray-200 p-4 border-b-[3px] border-b-transparent hover:border-b-lime transition flex flex-col h-full" data-product-id="' + esc(p.id) + '">' +
        '<div class="relative mb-3">' +
          '<a href="' + detailUrl + '" class="block">' +
            '<div class="h-40 bg-gray-100 flex items-center justify-center overflow-hidden p-2">' +
              '<img class="max-h-full object-contain hover:scale-105 transition-transform duration-300" loading="lazy" src="' + esc(p.imagen) + '" alt="' + esc(p.nombre) + '" data-fallback="assets/img/producto-placeholder.svg"/>' +
            '</div>' +
          '</a>' +
          '<button type="button" class="favorite-btn absolute top-2 right-2 flex items-center justify-center h-11 w-11 rounded-full bg-white/90 shadow hover:bg-white transition text-gray-500" data-product-id="' + esc(p.id) + '" aria-label="Agregar a favoritos" aria-pressed="false">' +
            '<span class="material-symbols-outlined" style="font-variation-settings:\'FILL\' 0;">favorite</span>' +
          '</button>' +
        '</div>' +
        '<div class="text-[11px] uppercase font-extrabold text-gray-500 tracking-wide mb-1">' + esc(p.marca) + '</div>' +
        '<h3 class="font-bold text-base leading-tight mb-1"><a href="' + detailUrl + '" class="hover:text-brand">' + esc(p.nombre) + '</a></h3>' +
        '<div class="font-body text-sm text-gray-400 mb-4">' + esc(qtyLabel(p)) + '</div>' +
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
    if (global.DSFavorites) global.DSFavorites.decorate(container);
  }

  function applyFilters(productos, filtros) {
    filtros = filtros || {};
    return productos.filter(function (p) {
      if (filtros.categoria && filtros.categoria.length && filtros.categoria.indexOf(p.categoria) === -1) return false;
      if (filtros.marca && filtros.marca.length && filtros.marca.indexOf(p.marca) === -1) return false;
      if (filtros.precioMin != null && p.precio < filtros.precioMin) return false;
      if (filtros.precioMax != null && p.precio > filtros.precioMax) return false;
      if (filtros.soloDisponibles && p.stock <= 0) return false;
      return true;
    });
  }

  // Normaliza texto para buscar: minusculas, sin acentos, trim.
  function normalizeText(s) {
    return String(s == null ? "" : s)
      .toLowerCase()
      .normalize("NFD").replace(/[\u0300-\u036f]/g, "")
      .trim();
  }

  // Distancia de edicion (Levenshtein) entre dos palabras cortas.
  function levenshtein(a, b) {
    if (a === b) return 0;
    var la = a.length, lb = b.length;
    if (!la) return lb;
    if (!lb) return la;
    var prev = [], i, j;
    for (j = 0; j <= lb; j++) prev[j] = j;
    for (i = 1; i <= la; i++) {
      var cur = [i], ca = a.charCodeAt(i - 1);
      for (j = 1; j <= lb; j++) {
        var cost = ca === b.charCodeAt(j - 1) ? 0 : 1;
        cur[j] = Math.min(prev[j] + 1, cur[j - 1] + 1, prev[j - 1] + cost);
      }
      prev = cur;
    }
    return prev[lb];
  }

  // Errores de dedo permitidos segun el largo de la palabra buscada.
  function fuzzyTolerance(len) {
    if (len <= 2) return 0;
    if (len <= 6) return 1;
    return 2;
  }

  // Puntua un token de busqueda contra las palabras de un campo.
  // 0 = no casa; mayor = mejor (exacto > empieza-con > contiene > difuso).
  function tokenScore(token, words) {
    var best = 0, tol = fuzzyTolerance(token.length), i, w, d;
    for (i = 0; i < words.length; i++) {
      w = words[i];
      if (!w) continue;
      if (w === token) return 100;
      if (w.indexOf(token) === 0) { best = Math.max(best, 90); continue; }
      if (w.indexOf(token) > -1) { best = Math.max(best, 70); continue; }
      if (tol > 0) {
        d = levenshtein(token, w);
        if (d <= tol) best = Math.max(best, 60 - d * 10);
      }
    }
    return best;
  }

  // Busqueda difusa: tolera acentos y errores de dedo. Devuelve los
  // productos que casan, ordenados por relevancia (mejor primero).
  function applySearch(productos, query) {
    // Usa el módulo difuso compartido (mismo comportamiento en todos los buscadores).
    if (global.DSFuzzy) {
      return global.DSFuzzy.filterSort(productos, query, function (p) {
        return [
          { text: p.nombre, weight: 1 },
          { text: p.marca, weight: 0.8 },
          { text: p.categoria, weight: 0.6 },
        ];
      });
    }
    // Fallback (por si fuzzy.js no cargó).
    var qn = normalizeText(query);
    if (!qn) return productos;
    var tokens = qn.split(/\s+/).filter(Boolean);
    if (!tokens.length) return productos;

    var scored = [];
    productos.forEach(function (p) {
      var nombreW = normalizeText(p.nombre).split(/\s+/).filter(Boolean);
      var marcaW  = normalizeText(p.marca).split(/\s+/).filter(Boolean);
      var catW    = normalizeText(p.categoria).split(/\s+/).filter(Boolean);

      var total = 0, allMatch = true;
      for (var t = 0; t < tokens.length; t++) {
        var tok = tokens[t];
        // El nombre pesa mas que la marca, y la marca mas que la categoria.
        var s = tokenScore(tok, nombreW);
        s = Math.max(s, tokenScore(tok, marcaW) * 0.8);
        s = Math.max(s, tokenScore(tok, catW) * 0.6);
        if (s <= 0) { allMatch = false; break; }
        total += s;
      }
      if (allMatch) scored.push({ p: p, score: total });
    });

    scored.sort(function (a, b) { return b.score - a.score; });
    return scored.map(function (x) { return x.p; });
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
        else if (field === "cantidad") el.textContent = qtyLabel(p);
        else if (p[field] !== undefined) el.textContent = p[field];
      });
      // Fija el id del producto en los botones de la ficha (carrito + favorito).
      // Esto además arregla "Agregar al carrito", que en la ficha quedaba sin id.
      container.querySelectorAll(".add-to-cart-btn, .favorite-btn").forEach(function (btn) {
        btn.setAttribute("data-product-id", p.id);
      });
      if (global.DSFavorites) global.DSFavorites.decorate(container);
      return p;
    }).catch(function () {
      container.innerHTML = '<p class="text-gray-600">No se pudo cargar el producto. Revisa tu conexión e <button type="button" class="js-reload text-brand underline">reintenta</button>.</p>';
      return null;
    });
  }

  function readFilters(root) {
    var categoria = Array.prototype.map.call(
      root.querySelectorAll('input[name="categoria"]:checked'), function (el) { return el.value; }
    );
    var marcaSel = root.querySelector('select[name="marca"]');
    var marca = marcaSel && marcaSel.value ? [marcaSel.value] : [];
    var minInput = root.querySelector('input[name="precio_min"]');
    var maxInput = root.querySelector('input[name="precio_max"]');
    var dispoInput = root.querySelector('input[name="solo_disponibles"]');
    return {
      categoria: categoria,
      marca: marca,
      precioMin: minInput && minInput.value !== "" ? Number(minInput.value) : null,
      precioMax: maxInput && maxInput.value !== "" ? Number(maxInput.value) : null,
      soloDisponibles: !!(dispoInput && dispoInput.checked),
    };
  }

  // Construye los filtros dinámicos (categorías, marcas, rango de precio) a partir
  // de los productos reales cargados. Se llama una sola vez.
  function populateFilters(root, productos) {
    // Categorías (checkboxes)
    var catBox = document.getElementById("cat-filters");
    if (catBox) {
      var cats = {};
      productos.forEach(function (p) { if (p.categoria) cats[p.categoria] = true; });
      var catList = Object.keys(cats).sort(function (a, b) { return a.localeCompare(b, "es"); });
      catBox.innerHTML = catList.length
        ? catList.map(function (c) {
            return '<label class="flex items-center gap-3 cursor-pointer font-medium text-sm hover:text-brand transition">' +
              '<input type="checkbox" name="categoria" value="' + esc(c) + '" class="accent-brand w-5 h-5 shrink-0"/> ' + esc(c) + '</label>';
          }).join("")
        : '<p class="text-gray-400 text-sm">Sin categorías</p>';
    }
    // Marcas (desplegable)
    var marcaSel = document.getElementById("marca-filter");
    if (marcaSel) {
      var marcas = {};
      productos.forEach(function (p) { if (p.marca) marcas[p.marca] = true; });
      var marcaList = Object.keys(marcas).sort(function (a, b) { return a.localeCompare(b, "es"); });
      marcaSel.innerHTML = '<option value="">Todas las marcas</option>' +
        marcaList.map(function (m) { return '<option value="' + esc(m) + '">' + esc(m) + '</option>'; }).join("");
    }
    // Rango de precio (placeholders = mín/máx reales)
    var precios = productos.map(function (p) { return Number(p.precio) || 0; });
    if (precios.length) {
      var lo = Math.floor(Math.min.apply(null, precios));
      var hi = Math.ceil(Math.max.apply(null, precios));
      var minI = root.querySelector('input[name="precio_min"]');
      var maxI = root.querySelector('input[name="precio_max"]');
      if (minI) { minI.setAttribute("placeholder", "Desde $" + lo); minI.setAttribute("min", lo); minI.setAttribute("max", hi); }
      if (maxI) { maxI.setAttribute("placeholder", "Hasta $" + hi); maxI.setAttribute("min", lo); maxI.setAttribute("max", hi); }
    }
  }

  function bootstrapCatalogo() {
    var grid = document.getElementById("product-grid");
    if (!grid) return;
    var countEl = document.getElementById("product-count");
    var searchInput = document.getElementById("search-input");
    var filtersRoot = document.querySelector("aside") || document;
    var filtersBuilt = false;

    function refresh() {
      fetchProductos().then(function (productos) {
        if (!filtersBuilt) { populateFilters(filtersRoot, productos); filtersBuilt = true; }
        var result = applyFilters(productos, readFilters(filtersRoot));
        result = applySearch(result, searchInput ? searchInput.value : "");
        renderGrid(result, grid);
        if (!result.length) {
          grid.innerHTML = '<p class="p-6 text-center text-gray-500 col-span-full font-bold">No encontramos productos con esa búsqueda. Prueba con otra palabra.</p>';
        }
        if (countEl) countEl.textContent = result.length + " producto" + (result.length === 1 ? "" : "s");
      }).catch(function () {
        grid.innerHTML = '<p class="p-6 text-center text-gray-600 col-span-full">No se pudo cargar el catálogo. Revisa tu conexión e <button type="button" class="js-reload text-brand underline">reintenta</button>.</p>';
        if (countEl) countEl.textContent = "";
      });
    }

    // Delegación de eventos: cubre también los filtros creados dinámicamente.
    var FILTER_NAMES = ["categoria", "marca", "precio_min", "precio_max", "solo_disponibles"];
    filtersRoot.addEventListener("change", function (e) {
      if (e.target && FILTER_NAMES.indexOf(e.target.name) !== -1) refresh();
    });
    filtersRoot.addEventListener("input", function (e) {
      if (e.target && (e.target.name === "precio_min" || e.target.name === "precio_max")) refresh();
    });
    if (searchInput) searchInput.addEventListener("input", refresh);

    var clearBtn = document.getElementById("clear-filters-btn");
    if (clearBtn) {
      clearBtn.addEventListener("click", function () {
        filtersRoot.querySelectorAll('input[name="categoria"], input[name="solo_disponibles"]').forEach(function (el) { el.checked = false; });
        filtersRoot.querySelectorAll('input[name="precio_min"], input[name="precio_max"]').forEach(function (el) { el.value = ""; });
        var ms = filtersRoot.querySelector('select[name="marca"]'); if (ms) ms.value = "";
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
