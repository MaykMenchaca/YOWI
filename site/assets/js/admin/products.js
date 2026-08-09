/* Admin — gestión de productos */
(function () {
  "use strict";

  var tableBody = null;
  var modal = null;
  var form  = null;
  var editingId = null;
  var categories = [];
  var currentPage = 1;
  var totalProducts = 0;
  var LIMIT = 20;
  var lastFocused = null; // elemento que abrió el modal, para devolver el foco al cerrar

  // Muestra el modal y lleva el foco a su primer campo (accesibilidad).
  function showModal() {
    lastFocused = document.activeElement;
    modal.classList.remove("hidden");
    var first = form.querySelector('input, select, textarea, button');
    if (first) first.focus();
  }

  // ── helpers ──────────────────────────────────────────────────────────────────
  function esc(s) {
    return String(s || "").replace(/[&<>"']/g, function (c) {
      return { "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;" }[c];
    });
  }

  function money(n) {
    return "$" + Number(n).toLocaleString("es-MX", { minimumFractionDigits: 2 });
  }

  function showAlert(msg, type) {
    var el = document.getElementById("alert-banner");
    if (!el) return;
    el.textContent = msg;
    el.className = "mb-4 px-4 py-3 rounded text-sm font-medium " +
      (type === "success"
        ? "bg-green-900/50 text-green-300 border border-green-700"
        : "bg-red-900/50 text-red-300 border border-red-700");
    el.classList.remove("hidden");
    setTimeout(function () { el.classList.add("hidden"); }, 5000);
  }

  // ── cargar categorías para el <select> del formulario ────────────────────────
  function loadCategories() {
    return DSAdminApi.apiFetch("../api/admin/categories/list.php")
      .then(function (data) {
        categories = data || [];
        populateCategorySelect();
      });
  }

  function populateCategorySelect() {
    var sel = form ? form.querySelector('[name="category_id"]') : null;
    var selFilter = document.getElementById("filter-cat");
    if (sel) {
      sel.innerHTML = '<option value="">-- Categoría --</option>' +
        categories.map(function (c) {
          return '<option value="' + c.id + '">' + esc(c.nombre) + '</option>';
        }).join("");
    }
    if (selFilter) {
      selFilter.innerHTML = '<option value="">Todas</option>' +
        categories.map(function (c) {
          return '<option value="' + c.id + '">' + esc(c.nombre) + '</option>';
        }).join("");
    }
  }

  // ── tabla ─────────────────────────────────────────────────────────────────────
  // Se cargan TODOS los productos una vez (allProducts) y se filtra/pagina en el
  // cliente, para que la búsqueda sea difusa (tolerante a errores) como en el catálogo.
  var allProducts = null;

  function fetchAllProducts() {
    return DSAdminApi.apiFetch("../api/admin/products/list.php?limit=9999")
      .then(function (data) { allProducts = data.data || []; return allProducts; });
  }

  function loadProducts(page) {
    currentPage = page || 1;
    var run = allProducts ? Promise.resolve(allProducts) : fetchAllProducts();
    run.then(function () {
      var q   = (document.getElementById("search-q") || {}).value.trim();
      var cat = (document.getElementById("filter-cat") || {}).value || "";
      var list = allProducts;
      if (cat) list = list.filter(function (p) { return String(p.category_id) === String(cat); });
      if (q) {
        if (window.DSFuzzy) {
          list = window.DSFuzzy.filterSort(list, q, function (p) {
            return [{ text: p.nombre, weight: 1 }, { text: p.marca, weight: 0.8 }, { text: p.categoria, weight: 0.6 }, { text: p.sku, weight: 0.8 }];
          });
        } else {
          var qn = q.toLowerCase();
          list = list.filter(function (p) { return (p.nombre + " " + p.marca + " " + (p.sku || "")).toLowerCase().indexOf(qn) !== -1; });
        }
      }
      totalProducts = list.length;
      var start = (currentPage - 1) * LIMIT;
      renderTable(list.slice(start, start + LIMIT));
      renderPagination(list.length, currentPage, LIMIT);
    }).catch(function (err) { showAlert("Error al cargar productos: " + err.message, "error"); });
  }

  // Fuerza recargar desde el servidor (tras crear/editar/borrar/importar).
  function reloadProducts(page) {
    allProducts = null;
    loadProducts(page || 1);
  }

  function renderTable(products) {
    if (!tableBody) return;
    if (!products || products.length === 0) {
      tableBody.innerHTML = '<tr><td colspan="8" class="px-4 py-8 text-center text-slate-400">Sin productos</td></tr>';
      return;
    }
    tableBody.innerHTML = products.map(function (p) {
      return '<tr class="border-b border-slate-700 hover:bg-slate-700/30">' +
        '<td class="px-3 py-3"><img src="../' + esc(p.imagen) + '" alt="" class="w-12 h-12 object-contain rounded bg-slate-800"></td>' +
        '<td class="px-3 py-3"><div class="text-slate-200 font-medium line-clamp-2 max-w-xs">' + esc(p.nombre) + '</div>' +
          '<div class="text-slate-400 text-xs">' + esc(p.marca) + ' · ' + esc(p.categoria) + '</div></td>' +
        '<td class="px-3 py-3 text-slate-400 text-xs font-mono">' + (p.sku ? esc(p.sku) : '<span class="text-slate-600">—</span>') + '</td>' +
        '<td class="px-3 py-3 text-slate-300 text-right">' + money(p.precio) + '</td>' +
        '<td class="px-3 py-3 text-center">' +
          (p.stock === null
            ? '<span class="px-2 py-0.5 rounded text-xs bg-slate-700 text-slate-300">Sin control</span>'
            : '<span class="px-2 py-0.5 rounded text-xs ' + (p.stock > 0 ? 'bg-green-900/50 text-green-300' : 'bg-red-900/50 text-red-300') + '">' + (p.stock > 0 ? p.stock : 'Agotado') + '</span>') + '</td>' +
        '<td class="px-3 py-3 text-center">' + (p.destacado
          ? '<svg viewBox="0 0 24 24" class="w-4 h-4 inline-block text-yellow-400" fill="currentColor" aria-label="Destacado"><path d="M12 2l2.9 6.3 6.9.6-5.2 4.5 1.6 6.7L12 17l-6.2 3.6 1.6-6.7L2.2 8.9l6.9-.6z"/></svg>'
          : '<span class="text-slate-600">—</span>') + '</td>' +
        '<td class="px-3 py-3 text-center">' +
          '<span class="px-2 py-0.5 rounded text-xs ' + (p.activo ? 'bg-blue-900/50 text-blue-300' : 'bg-slate-700 text-slate-400') + '">' +
            (p.activo ? 'Activo' : 'Oculto') + '</span></td>' +
        '<td class="px-3 py-3 whitespace-nowrap">' +
          '<button class="btn-link-brand mr-2 edit-btn" data-id="' + p.id + '">Editar</button>' +
          '<button class="btn-link-danger delete-btn" data-id="' + p.id + '">Ocultar</button>' +
        '</td>' +
      '</tr>';
    }).join("");

    tableBody.querySelectorAll(".edit-btn").forEach(function (btn) {
      btn.addEventListener("click", function () { openModal(parseInt(btn.dataset.id, 10)); });
    });
    tableBody.querySelectorAll(".delete-btn").forEach(function (btn) {
      btn.addEventListener("click", function () { softDelete(parseInt(btn.dataset.id, 10)); });
    });
  }

  function renderPagination(total, page, limit) {
    var el = document.getElementById("pagination");
    if (!el) return;
    var pages = Math.ceil(total / limit);
    if (pages <= 1) { el.innerHTML = ""; return; }
    var html = "";
    for (var i = 1; i <= pages; i++) {
      html += '<button class="btn btn-sm ' +
        (i === page ? 'bg-brand text-white' : 'bg-slate-700 text-slate-300 hover:bg-slate-600') +
        '" data-page="' + i + '">' + i + '</button> ';
    }
    el.innerHTML = html;
    el.querySelectorAll("button").forEach(function (btn) {
      btn.addEventListener("click", function () { loadProducts(parseInt(btn.dataset.page, 10)); });
    });
  }

  // ── modal ─────────────────────────────────────────────────────────────────────
  function openModal(id) {
    editingId = id || null;
    document.getElementById("modal-title").textContent = editingId ? "Editar producto" : "Nuevo producto";
    form.reset();
    document.getElementById("image-preview").src = "";
    document.getElementById("image-preview").classList.add("hidden");

    if (!editingId) {
      // Producto nuevo: sabores y galería necesitan un product_id real, que
      // solo existe tras guardar. Se desbloquean al reabrir en modo edición.
      setFlavorsLocked(true);
      setGalleryLocked(true);
      showModal();
      return;
    }

    setFlavorsLocked(false);
    setGalleryLocked(false);

    // Cargar datos del producto a editar. Una sola llamada: se busca por id en la lista.
    // (YAGNI: no hay endpoint GET-by-id; el catálogo es pequeño.)
    DSAdminApi.apiFetch("../api/admin/products/list.php?limit=9999")
      .then(function (data) {
        var p = (data.data || []).filter(function (x) { return x.id === editingId; })[0];
        if (!p) {
          // Defensivo: con el catálogo completo cargado (limit=9999) esto ya no debería
          // pasar, pero si el producto se borró justo antes de este clic, avisar en vez
          // de dejar el modal abierto y en blanco.
          closeModal();
          showAlert("No se encontró el producto (¿se eliminó?). Actualiza la lista.", "error");
          return;
        }
        fillForm(p);
      })
      .catch(function (err) { showAlert(err.message, "error"); });

    loadFlavors(editingId);
    loadGallery(editingId);

    showModal();
  }

  function fillForm(p) {
    var f = form;
    f.querySelector('[name="nombre"]').value      = p.nombre || "";
    f.querySelector('[name="marca"]').value       = p.marca || "";
    f.querySelector('[name="sku"]').value         = p.sku || "";
    f.querySelector('[name="category_id"]').value = p.category_id || "";
    f.querySelector('[name="cantidad"]').value    = p.cantidad || "";
    f.querySelector('[name="unidad"]').value      = p.unidad || "";
    f.querySelector('[name="descripcion"]').value = p.descripcion || "";
    f.querySelector('[name="precio"]').value      = p.precio || "";
    f.querySelector('[name="precio_original"]').value = p.precio_original || "";
    f.querySelector('[name="stock"]').value       = (p.stock === null || p.stock === undefined) ? "" : p.stock;
    f.querySelector('[name="badge"]').value       = p.badge || "";
    f.querySelector('[name="destacado"]').checked = !!p.destacado;
    f.querySelector('[name="activo"]').checked    = p.activo !== false;
    document.getElementById("current-imagen").value = p.imagen || "";
    if (p.imagen) {
      var prev = document.getElementById("image-preview");
      prev.src = "../" + p.imagen;
      prev.classList.remove("hidden");
    }
  }

  function closeModal() {
    modal.classList.add("hidden");
    editingId = null;
    form.reset();
    if (flavorsRows) flavorsRows.innerHTML = "";
    if (galleryThumbs) { galleryThumbs.innerHTML = ""; galleryImages = []; }
    if (lastFocused && lastFocused.focus) lastFocused.focus(); // devolver el foco al disparador
  }

  // ── sabores (nombre + stock/precio propios; null = usa el del producto) ──────
  var flavorsRows = null;
  var flavorsLockedMsg = null;
  var flavorAddBtn = null;

  function flavorRowHtml(f) {
    f = f || {};
    var nombre = f.nombre != null ? f.nombre : "";
    var stock  = (f.stock === null || f.stock === undefined) ? "" : String(f.stock);
    var precio = (f.precio === null || f.precio === undefined) ? "" : String(f.precio);
    return (
      '<div class="flex gap-2 items-center flavor-row">' +
        '<input type="text" class="flavor-nombre flex-1 bg-slate-700 border border-slate-600 rounded-none px-3 py-1.5 text-slate-100 text-sm focus:outline-none focus:border-brand focus:ring-1 focus:ring-brand" placeholder="Nombre del sabor" value="' + esc(nombre) + '" />' +
        '<input type="number" min="0" class="flavor-stock w-28 bg-slate-700 border border-slate-600 rounded-none px-3 py-1.5 text-slate-100 text-sm focus:outline-none focus:border-brand focus:ring-1 focus:ring-brand" placeholder="Sin control" value="' + stock + '" />' +
        '<input type="number" step="0.01" min="0" class="flavor-precio w-32 bg-slate-700 border border-slate-600 rounded-none px-3 py-1.5 text-slate-100 text-sm focus:outline-none focus:border-brand focus:ring-1 focus:ring-brand" placeholder="Precio del producto" value="' + precio + '" />' +
        '<button type="button" class="flavor-remove-btn btn-link-danger text-sm px-2 shrink-0">Quitar</button>' +
      '</div>'
    );
  }

  function addFlavorRow(f) {
    flavorsRows.insertAdjacentHTML("beforeend", flavorRowHtml(f));
  }

  function renderFlavorRows(list) {
    flavorsRows.innerHTML = "";
    (list || []).forEach(function (f) { addFlavorRow(f); });
  }

  // Lee las filas actuales del DOM y arma el array a enviar a flavors.php.
  // Filas con nombre vacío se ignoran (no se mandan).
  function collectFlavorsFromDom() {
    var rows = flavorsRows.querySelectorAll(".flavor-row");
    var out = [];
    rows.forEach(function (row) {
      var nombre = row.querySelector(".flavor-nombre").value.trim();
      if (!nombre) return;
      var stockVal  = row.querySelector(".flavor-stock").value.trim();
      var precioVal = row.querySelector(".flavor-precio").value.trim();
      out.push({
        nombre: nombre,
        stock:  stockVal === "" ? null : Math.max(0, parseInt(stockVal, 10) || 0),
        precio: precioVal === "" ? null : parseFloat(precioVal),
      });
    });
    return out;
  }

  // Al crear un producto nuevo (sin id todavía) los sabores necesitan un
  // product_id real, así que la sección queda bloqueada hasta guardar.
  function setFlavorsLocked(locked) {
    flavorsLockedMsg.classList.toggle("hidden", !locked);
    flavorsRows.classList.toggle("hidden", locked);
    flavorAddBtn.classList.toggle("hidden", locked);
    if (locked) flavorsRows.innerHTML = "";
  }

  function loadFlavors(productId) {
    return DSAdminApi.apiFetch("../api/admin/products/flavors.php?product_id=" + productId)
      .then(function (data) { renderFlavorRows(data || []); })
      .catch(function (err) { showAlert("Error al cargar sabores: " + err.message, "error"); });
  }

  // Reemplaza el conjunto completo de sabores del producto con lo que haya en el DOM.
  function saveFlavors(productId) {
    return DSAdminApi.apiFetch("../api/admin/products/flavors.php", {
      method: "POST",
      body: { product_id: productId, sabores: collectFlavorsFromDom() },
    });
  }

  // ── galería de imágenes (además de la imagen principal del producto) ─────────
  var galleryThumbs = null;
  var galleryLockedMsg = null;
  var galleryUploadBtn = null;
  var galleryFileInput = null;
  var galleryImages = []; // última lista conocida, para calcular el reorder

  function galleryThumbHtml(img) {
    return (
      '<div class="relative border border-slate-600 rounded bg-slate-900 p-1 gallery-thumb" data-id="' + img.id + '">' +
        '<img src="../' + esc(img.url) + '" alt="" class="w-full h-20 object-contain" />' +
        '<button type="button" class="gallery-delete-btn btn-icon-sm absolute -top-1 -right-1 bg-red-900 text-red-200 hover:bg-red-800" title="Quitar de la galería">&times;</button>' +
        '<div class="flex justify-between items-center mt-1">' +
          '<button type="button" class="gallery-move-left-btn text-slate-300 hover:text-white text-xs px-1" title="Mover a la izquierda">&#9664;</button>' +
          '<button type="button" class="gallery-principal-btn text-slate-400 hover:text-lime text-[10px] px-1" title="Usar como imagen principal">&#9733;</button>' +
          '<button type="button" class="gallery-move-right-btn text-slate-300 hover:text-white text-xs px-1" title="Mover a la derecha">&#9654;</button>' +
        '</div>' +
      '</div>'
    );
  }

  function renderGalleryThumbs(list) {
    galleryImages = list || [];
    galleryThumbs.innerHTML = galleryImages.map(galleryThumbHtml).join("");
  }

  // Igual que los sabores: sin product_id real todavía no hay dónde guardar imágenes.
  function setGalleryLocked(locked) {
    galleryLockedMsg.classList.toggle("hidden", !locked);
    galleryThumbs.classList.toggle("hidden", locked);
    galleryUploadBtn.classList.toggle("hidden", locked);
    if (locked) { galleryImages = []; galleryThumbs.innerHTML = ""; }
  }

  function loadGallery(productId) {
    return DSAdminApi.apiFetch("../api/admin/products/images.php?product_id=" + productId)
      .then(function (data) { renderGalleryThumbs(data); })
      .catch(function (err) { showAlert("Error al cargar galería: " + err.message, "error"); });
  }

  // La galería se guarda de inmediato (no espera al submit del formulario):
  // por cada archivo se sube con upload-image.php y luego se agrega con images.php.
  function uploadGalleryFiles(files) {
    if (!editingId) return;
    var uploadOne = function (file) {
      var fd = new FormData();
      fd.append("imagen", file);
      return DSAdminApi.apiFetch("../api/admin/products/upload-image.php", { method: "POST", body: fd })
        .then(function (imgData) {
          return DSAdminApi.apiFetch("../api/admin/products/images.php", {
            method: "POST",
            body: { action: "add", product_id: editingId, url: imgData.url },
          });
        });
    };
    var chain = Promise.resolve();
    Array.prototype.forEach.call(files, function (file) {
      chain = chain.then(function () { return uploadOne(file); });
    });
    return chain
      .then(function () { return loadGallery(editingId); })
      .catch(function (err) { showAlert("Error al subir imagen: " + err.message, "error"); });
  }

  function deleteGalleryImage(id) {
    if (!confirm("¿Quitar esta imagen de la galería?")) return;
    DSAdminApi.apiFetch("../api/admin/products/images.php", { method: "POST", body: { action: "delete", id: id } })
      .then(function () { return loadGallery(editingId); })
      .catch(function (err) { showAlert(err.message, "error"); });
  }

  // Reordenamiento simple: intercambia la posición con la miniatura vecina
  // y reenvía el orden completo (no hace falta drag-and-drop real).
  function moveGalleryImage(id, dir) {
    var ids = galleryImages.map(function (g) { return g.id; });
    var idx = ids.indexOf(id);
    var swapWith = idx + dir;
    if (idx === -1 || swapWith < 0 || swapWith >= ids.length) return;
    var tmp = ids[idx]; ids[idx] = ids[swapWith]; ids[swapWith] = tmp;
    DSAdminApi.apiFetch("../api/admin/products/images.php", {
      method: "POST",
      body: { action: "reorder", product_id: editingId, ids: ids },
    })
      .then(function (data) { renderGalleryThumbs(data); })
      .catch(function (err) { showAlert(err.message, "error"); });
  }

  function setPrincipalImage(id) {
    DSAdminApi.apiFetch("../api/admin/products/images.php", { method: "POST", body: { action: "set_principal", id: id } })
      .then(function (data) {
        document.getElementById("current-imagen").value = data.imagen || "";
        var prev = document.getElementById("image-preview");
        if (data.imagen) {
          prev.src = "../" + data.imagen;
          prev.classList.remove("hidden");
        }
        showAlert("Imagen principal actualizada", "success");
      })
      .catch(function (err) { showAlert(err.message, "error"); });
  }

  function softDelete(id) {
    if (!confirm("¿Ocultar este producto del catálogo público?")) return;
    DSAdminApi.apiFetch("../api/admin/products/delete.php", {
      method: "POST",
      body: { id: id },
    })
      .then(function () { reloadProducts(currentPage); showAlert("Producto ocultado", "success"); })
      .catch(function (err) { showAlert(err.message, "error"); });
  }

  // ── submit del formulario ─────────────────────────────────────────────────────
  function submitForm(e) {
    e.preventDefault();

    // ¿Hay imagen nueva seleccionada?
    var fileInput = form.querySelector('[name="imagen_file"]');
    var imagePromise = Promise.resolve(null);

    if (fileInput && fileInput.files && fileInput.files[0]) {
      var fd = new FormData();
      fd.append("imagen", fileInput.files[0]);
      imagePromise = DSAdminApi.apiFetch("../api/admin/products/upload-image.php", {
        method: "POST",
        body: fd,
      });
    }

    imagePromise.then(function (imgData) {
      var imagenUrl = imgData ? imgData.url : (document.getElementById("current-imagen").value || "assets/img/producto-placeholder.svg");
      var body = {
        nombre:          form.querySelector('[name="nombre"]').value.trim(),
        marca:           form.querySelector('[name="marca"]').value.trim(),
        sku:             form.querySelector('[name="sku"]').value.trim() || null,
        category_id:     parseInt(form.querySelector('[name="category_id"]').value, 10),
        cantidad:        form.querySelector('[name="cantidad"]').value.trim(),
        unidad:          form.querySelector('[name="unidad"]').value.trim(),
        descripcion:     form.querySelector('[name="descripcion"]').value.trim(),
        precio:          parseFloat(form.querySelector('[name="precio"]').value) || 0,
        precio_original: parseFloat(form.querySelector('[name="precio_original"]').value) || null,
        stock:           (function () { var v = form.querySelector('[name="stock"]').value.trim();
                                         return v === '' ? null : Math.max(0, parseInt(v, 10) || 0); })(),
        imagen:          imagenUrl,
        badge:           form.querySelector('[name="badge"]').value.trim() || null,
        destacado:       form.querySelector('[name="destacado"]').checked ? 1 : 0,
        activo:          form.querySelector('[name="activo"]').checked ? 1 : 0,
      };
      if (editingId) body.id = editingId;

      var path = editingId
        ? "../api/admin/products/update.php"
        : "../api/admin/products/create.php";

      return DSAdminApi.apiFetch(path, { method: "POST", body: body });
    })
      .then(function () {
        var wasEditing = editingId;
        // Los sabores se guardan junto con el producto (un solo botón "Guardar").
        // No aplica al crear: la sección está bloqueada hasta que exista un id.
        if (wasEditing) {
          return saveFlavors(wasEditing)
            .then(function () {
              closeModal();
              reloadProducts(currentPage);
              showAlert("Producto actualizado", "success");
            })
            .catch(function (err) {
              // El producto SÍ se guardó; el error es solo de los sabores.
              closeModal();
              reloadProducts(currentPage);
              showAlert("Producto guardado, pero hubo un error al guardar los sabores: " + err.message, "error");
            });
        }
        closeModal();
        reloadProducts(currentPage);
        showAlert("Producto creado", "success");
      })
      .catch(function (err) { showAlert(err.message, "error"); });
  }

  // ── preview de imagen local ───────────────────────────────────────────────────
  function setupImagePreview() {
    var fileInput = form.querySelector('[name="imagen_file"]');
    if (!fileInput) return;
    fileInput.addEventListener("change", function () {
      if (!fileInput.files || !fileInput.files[0]) return;
      var reader = new FileReader();
      reader.onload = function (e) {
        var prev = document.getElementById("image-preview");
        prev.src = e.target.result;
        prev.classList.remove("hidden");
      };
      reader.readAsDataURL(fileInput.files[0]);
    });
  }

  // ── importar CSV (F2.4: Analizar → tabla con colores → Aplicar cambios) ────────
  // Tras "Analizar" (preview=1, no escribe nada) queda habilitado "Aplicar cambios",
  // que reenvía el mismo archivo con preview=0. Cambiar el archivo o el checkbox de
  // "reemplazar todo" invalida el análisis: hay que analizar de nuevo antes de aplicar.
  function setApplyEnabled(enabled) {
    var btn = document.getElementById("import-submit");
    btn.disabled = !enabled;
  }

  function openImportModal() {
    var m = document.getElementById("import-modal");
    document.getElementById("import-file").value = "";
    var replaceEl = document.getElementById("import-replace");
    if (replaceEl) replaceEl.checked = false;
    var res = document.getElementById("import-results");
    res.classList.add("hidden");
    res.innerHTML = "";
    setApplyEnabled(false);
    m.classList.remove("hidden");
  }

  function closeImportModal() {
    document.getElementById("import-modal").classList.add("hidden");
  }

  // Vaciar catálogo: borra TODOS los productos (acción destructiva, doble confirmación).
  function wipeCatalog() {
    var typed = prompt("Esto BORRA todos los productos del catálogo y no se puede deshacer.\n\nEscribe BORRAR (en mayúsculas) para confirmar:");
    if (typed !== "BORRAR") {
      if (typed !== null) showAlert("Cancelado: no escribiste BORRAR.", "error");
      return;
    }
    DSAdminApi.apiFetch("../api/admin/products/delete-all.php", { method: "POST", body: { confirm: "BORRAR" } })
      .then(function (data) {
        reloadProducts(1);
        showAlert((data.borrados || 0) + " producto(s) borrado(s). El catálogo está vacío.", "success");
      })
      .catch(function (err) { showAlert("Error al vaciar: " + err.message, "error"); });
  }

  function downloadTemplate() {
    var rows = [
      "sku,nombre,marca,categoria,cantidad,unidad,descripcion,precio,precio_original,stock,imagen,imagenes,badge,sabores,destacado,activo",
      ',Proteína Whey Gold Standard,Optimum Nutrition,Proteínas,2,lb,Proteína de suero aislada,899.00,1099.00,15,,,MÁS VENDIDO,"Chocolate:10:899|Vainilla:5:949|Fresa",1,1',
      ",Creatina Monohidratada,Universal,Creatina,300,g,Creatina micronizada,349.00,,30,,,,,0,1",
    ].join("\r\n");
    // BOM para que Excel abra los acentos correctamente.
    var blob = new Blob(["﻿" + rows], { type: "text/csv;charset=utf-8;" });
    var url = URL.createObjectURL(blob);
    var a = document.createElement("a");
    a.href = url;
    a.download = "plantilla-productos.csv";
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }

  var ACCION_INFO = {
    creado:      { label: "Nuevo",        row: "bg-green-900/20",  badge: "bg-green-800 text-green-200" },
    actualizado: { label: "Cambia",       row: "bg-blue-900/20",   badge: "bg-blue-800 text-blue-200" },
    sin_cambios: { label: "Sin cambios",  row: "bg-transparent",   badge: "bg-slate-700 text-slate-400" },
    omitido:     { label: "Omitido",      row: "bg-red-900/20",    badge: "bg-red-800 text-red-200" },
  };

  // Arma la tabla fila-por-fila (creado/actualizado/sin_cambios/omitido) que devuelve
  // import.php en `filas`. Mismo render para la vista previa y para el resultado aplicado.
  function renderImportFilas(filas) {
    if (!filas || !filas.length) return "";
    var rows = filas.map(function (f) {
      var info = ACCION_INFO[f.accion] || ACCION_INFO.sin_cambios;
      var detalle = f.accion === "actualizado" && f.campos && f.campos.length
        ? f.campos.join(", ")
        : (f.accion === "omitido" ? esc(f.motivo || "") : "—");
      return (
        '<tr class="' + info.row + ' border-b border-slate-700/60">' +
        '<td class="px-2 py-1.5 text-slate-500">' + f.fila + "</td>" +
        '<td class="px-2 py-1.5"><span class="inline-block px-1.5 py-0.5 rounded text-xs font-bold uppercase ' + info.badge + '">' + info.label + "</span></td>" +
        '<td class="px-2 py-1.5 text-slate-300">' + esc(f.sku || "—") + "</td>" +
        '<td class="px-2 py-1.5 text-slate-200">' + esc(f.nombre || "—") + "</td>" +
        '<td class="px-2 py-1.5 text-slate-400">' + detalle + "</td>" +
        "</tr>"
      );
    }).join("");
    return (
      '<div class="max-h-80 overflow-auto border border-slate-700 rounded mt-3">' +
      '<table class="w-full text-xs">' +
      '<thead class="sticky top-0 bg-slate-800"><tr class="text-left text-slate-500 uppercase tracking-wide">' +
      '<th class="px-2 py-1.5">Fila</th><th class="px-2 py-1.5">Estado</th><th class="px-2 py-1.5">SKU</th>' +
      '<th class="px-2 py-1.5">Nombre</th><th class="px-2 py-1.5">Campos / motivo</th>' +
      "</tr></thead><tbody>" + rows + "</tbody></table></div>"
    );
  }

  function renderImportContadores(data) {
    var chips = [
      { n: data.creados, label: "nuevos", cls: "bg-green-900/40 text-green-300 border-green-700" },
      { n: data.actualizados, label: "con cambios", cls: "bg-blue-900/40 text-blue-300 border-blue-700" },
      { n: data.sin_cambios, label: "sin cambios", cls: "bg-slate-700/60 text-slate-400 border-slate-600" },
      { n: (data.omitidos || []).length, label: "omitidos", cls: "bg-red-900/40 text-red-300 border-red-700" },
    ];
    if (data.desactivados) {
      chips.push({ n: data.desactivados, label: "desactivados", cls: "bg-amber-900/40 text-amber-300 border-amber-700" });
    }
    return '<div class="flex flex-wrap gap-2">' + chips.map(function (c) {
      return '<span class="border rounded px-2 py-1 text-xs font-bold ' + c.cls + '">' + c.n + " " + c.label + "</span>";
    }).join("") + "</div>";
  }

  // Ejecuta import.php con preview=1 (analizar, no escribe) o preview=0 (aplicar).
  function runImport(preview) {
    var input = document.getElementById("import-file");
    if (!input.files || !input.files[0]) {
      showAlert("Elige un archivo CSV primero", "error");
      return;
    }
    var replaceEl = document.getElementById("import-replace");
    var replaceAll = replaceEl && replaceEl.checked;

    var fd = new FormData();
    fd.append("csv", input.files[0]);
    fd.append("preview", preview ? "1" : "0");
    if (replaceAll) fd.append("replace_all", "1");

    var analyzeBtn = document.getElementById("import-analyze");
    var applyBtn = document.getElementById("import-submit");
    analyzeBtn.disabled = true;
    applyBtn.disabled = true;
    (preview ? analyzeBtn : applyBtn).textContent = preview ? "Analizando…" : "Aplicando…";

    return DSAdminApi.apiFetch("../api/admin/products/import.php", { method: "POST", body: fd })
      .then(function (data) {
        var res = document.getElementById("import-results");
        var titulo = preview
          ? '<p class="text-slate-300 font-semibold mb-2">Vista previa — no se guardó nada todavía:</p>'
          : '<p class="text-green-300 font-semibold mb-2">✓ Cambios aplicados.</p>';
        res.innerHTML =
          '<div class="bg-slate-900/60 border border-slate-600 rounded p-3">' + titulo +
          renderImportContadores(data) + renderImportFilas(data.filas) + "</div>";
        res.classList.remove("hidden");

        if (preview) {
          setApplyEnabled(true);
        } else {
          setApplyEnabled(false);
          // Refrescar categorías (pudieron crearse nuevas) y la tabla (recarga forzada).
          loadCategories().then(function () { reloadProducts(1); });
        }
        return data;
      })
      .catch(function (err) {
        showAlert("Error al " + (preview ? "analizar" : "aplicar") + ": " + err.message, "error");
        setApplyEnabled(false);
      })
      .finally(function () {
        analyzeBtn.disabled = false;
        analyzeBtn.textContent = "Analizar";
        applyBtn.textContent = "Aplicar cambios";
      });
  }

  function analyzeImport() {
    runImport(true);
  }

  function applyImport() {
    var replaceEl = document.getElementById("import-replace");
    var replaceAll = replaceEl && replaceEl.checked;
    if (replaceAll && !confirm('Vas a desactivar todos los productos que NO estén en este archivo (no se borran, solo dejan de verse en la tienda). ¿Continuar?')) {
      return;
    }
    runImport(false);
  }

  // Cambiar el archivo o el checkbox invalida el análisis previo: hay que analizar de nuevo.
  function invalidateImportAnalysis() {
    setApplyEnabled(false);
    var res = document.getElementById("import-results");
    res.classList.add("hidden");
    res.innerHTML = "";
  }

  // ── init ──────────────────────────────────────────────────────────────────────
  document.addEventListener("DOMContentLoaded", function () {
    tableBody = document.getElementById("products-tbody");
    modal     = document.getElementById("product-modal");
    form      = document.getElementById("product-form");

    // Sabores
    flavorsRows      = document.getElementById("flavors-rows");
    flavorsLockedMsg = document.getElementById("flavors-locked-msg");
    flavorAddBtn     = document.getElementById("flavor-add-row-btn");
    flavorAddBtn.addEventListener("click", function () { addFlavorRow(); });
    flavorsRows.addEventListener("click", function (e) {
      var btn = e.target.closest(".flavor-remove-btn");
      if (btn) btn.closest(".flavor-row").remove();
    });

    // Galería de imágenes
    galleryThumbs     = document.getElementById("gallery-thumbs");
    galleryLockedMsg  = document.getElementById("gallery-locked-msg");
    galleryUploadBtn  = document.getElementById("gallery-upload-btn");
    galleryFileInput  = document.getElementById("gallery-file-input");
    galleryUploadBtn.addEventListener("click", function () { galleryFileInput.click(); });
    galleryFileInput.addEventListener("change", function () {
      if (galleryFileInput.files && galleryFileInput.files.length) {
        uploadGalleryFiles(galleryFileInput.files);
      }
      galleryFileInput.value = "";
    });
    galleryThumbs.addEventListener("click", function (e) {
      var thumb = e.target.closest(".gallery-thumb");
      if (!thumb) return;
      var id = parseInt(thumb.dataset.id, 10);
      if (e.target.closest(".gallery-delete-btn")) deleteGalleryImage(id);
      else if (e.target.closest(".gallery-move-left-btn")) moveGalleryImage(id, -1);
      else if (e.target.closest(".gallery-move-right-btn")) moveGalleryImage(id, 1);
      else if (e.target.closest(".gallery-principal-btn")) setPrincipalImage(id);
    });

    document.getElementById("new-product-btn").addEventListener("click", function () { openModal(null); });
    document.getElementById("modal-cancel").addEventListener("click", closeModal);
    // Botón "Cancelar" del pie del modal (antes era un <script> inline en productos.html).
    var cancelBtn = document.getElementById("modal-cancel-btn");
    if (cancelBtn) cancelBtn.addEventListener("click", closeModal);
    form.addEventListener("submit", submitForm);

    // Importación CSV (F2.4: Analizar → tabla con colores → Aplicar cambios).
    document.getElementById("wipe-btn").addEventListener("click", wipeCatalog);
    document.getElementById("import-btn").addEventListener("click", openImportModal);
    document.getElementById("import-close").addEventListener("click", closeImportModal);
    document.getElementById("import-cancel").addEventListener("click", closeImportModal);
    document.getElementById("download-template").addEventListener("click", downloadTemplate);
    document.getElementById("import-analyze").addEventListener("click", analyzeImport);
    document.getElementById("import-submit").addEventListener("click", applyImport);
    document.getElementById("import-file").addEventListener("change", invalidateImportAnalysis);
    document.getElementById("import-replace").addEventListener("change", invalidateImportAnalysis);

    // Cerrar con Escape cuando el modal está abierto.
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && !modal.classList.contains("hidden")) closeModal();
    });

    document.getElementById("search-q").addEventListener("input", function () { loadProducts(1); });
    document.getElementById("filter-cat").addEventListener("change", function () { loadProducts(1); });

    setupImagePreview();

    loadCategories().then(function () { loadProducts(1); });
  });
})();
