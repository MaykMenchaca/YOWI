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
  function loadProducts(page) {
    currentPage = page || 1;
    var q   = (document.getElementById("search-q") || {}).value || "";
    var cat = (document.getElementById("filter-cat") || {}).value || "";
    var url = "../api/admin/products/list.php?page=" + currentPage + "&limit=" + LIMIT;
    if (q)   url += "&q="   + encodeURIComponent(q);
    if (cat) url += "&cat=" + encodeURIComponent(cat);

    DSAdminApi.apiFetch(url)
      .then(function (data) {
        totalProducts = data.total;
        renderTable(data.data);
        renderPagination(data.total, data.page, data.limit);
      })
      .catch(function (err) { showAlert("Error al cargar productos: " + err.message, "error"); });
  }

  function renderTable(products) {
    if (!tableBody) return;
    if (!products || products.length === 0) {
      tableBody.innerHTML = '<tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">Sin productos</td></tr>';
      return;
    }
    tableBody.innerHTML = products.map(function (p) {
      return '<tr class="border-b border-slate-700 hover:bg-slate-700/30">' +
        '<td class="px-3 py-3"><img src="../' + esc(p.imagen) + '" alt="" class="w-12 h-12 object-contain rounded bg-slate-800"></td>' +
        '<td class="px-3 py-3"><div class="text-slate-200 font-medium line-clamp-2 max-w-xs">' + esc(p.nombre) + '</div>' +
          '<div class="text-slate-400 text-xs">' + esc(p.marca) + ' · ' + esc(p.categoria) + '</div></td>' +
        '<td class="px-3 py-3 text-slate-300 text-right">' + money(p.precio) + '</td>' +
        '<td class="px-3 py-3 text-center">' +
          '<span class="px-2 py-0.5 rounded text-xs ' + (p.stock > 0 ? 'bg-green-900/50 text-green-300' : 'bg-red-900/50 text-red-300') + '">' + p.stock + '</span></td>' +
        '<td class="px-3 py-3 text-center">' + (p.destacado
          ? '<svg viewBox="0 0 24 24" class="w-4 h-4 inline-block text-yellow-400" fill="currentColor" aria-label="Destacado"><path d="M12 2l2.9 6.3 6.9.6-5.2 4.5 1.6 6.7L12 17l-6.2 3.6 1.6-6.7L2.2 8.9l6.9-.6z"/></svg>'
          : '<span class="text-slate-600">—</span>') + '</td>' +
        '<td class="px-3 py-3 text-center">' +
          '<span class="px-2 py-0.5 rounded text-xs ' + (p.activo ? 'bg-blue-900/50 text-blue-300' : 'bg-slate-700 text-slate-400') + '">' +
            (p.activo ? 'Activo' : 'Oculto') + '</span></td>' +
        '<td class="px-3 py-3 whitespace-nowrap">' +
          '<button class="text-blue-400 hover:text-blue-300 mr-2 edit-btn" data-id="' + p.id + '">Editar</button>' +
          '<button class="text-red-400 hover:text-red-300 delete-btn" data-id="' + p.id + '">Ocultar</button>' +
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
      html += '<button class="px-3 py-1 rounded text-sm ' +
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
      showModal();
      return;
    }

    // Cargar datos del producto a editar. Una sola llamada: se busca por id en la lista.
    // (YAGNI: no hay endpoint GET-by-id; el catálogo es pequeño.)
    DSAdminApi.apiFetch("../api/admin/products/list.php?limit=9999")
      .then(function (data) {
        var p = (data.data || []).filter(function (x) { return x.id === editingId; })[0];
        if (!p) return;
        fillForm(p);
      })
      .catch(function (err) { showAlert(err.message, "error"); });

    showModal();
  }

  function fillForm(p) {
    var f = form;
    f.querySelector('[name="nombre"]').value      = p.nombre || "";
    f.querySelector('[name="marca"]').value       = p.marca || "";
    f.querySelector('[name="category_id"]').value = p.category_id || "";
    f.querySelector('[name="cantidad"]').value    = p.cantidad || "";
    f.querySelector('[name="unidad"]').value      = p.unidad || "";
    f.querySelector('[name="descripcion"]').value = p.descripcion || "";
    f.querySelector('[name="precio"]').value      = p.precio || "";
    f.querySelector('[name="precio_original"]').value = p.precio_original || "";
    f.querySelector('[name="stock"]').value       = p.stock !== undefined ? p.stock : "";
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
    if (lastFocused && lastFocused.focus) lastFocused.focus(); // devolver el foco al disparador
  }

  function softDelete(id) {
    if (!confirm("¿Ocultar este producto del catálogo público?")) return;
    DSAdminApi.apiFetch("../api/admin/products/delete.php", {
      method: "POST",
      body: { id: id },
    })
      .then(function () { loadProducts(currentPage); showAlert("Producto ocultado", "success"); })
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
        category_id:     parseInt(form.querySelector('[name="category_id"]').value, 10),
        cantidad:        form.querySelector('[name="cantidad"]').value.trim(),
        unidad:          form.querySelector('[name="unidad"]').value.trim(),
        descripcion:     form.querySelector('[name="descripcion"]').value.trim(),
        precio:          parseFloat(form.querySelector('[name="precio"]').value) || 0,
        precio_original: parseFloat(form.querySelector('[name="precio_original"]').value) || null,
        stock:           parseInt(form.querySelector('[name="stock"]').value, 10) || 0,
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
        closeModal();
        loadProducts(currentPage);
        showAlert(editingId ? "Producto actualizado" : "Producto creado", "success");
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

  // ── importar CSV ──────────────────────────────────────────────────────────────
  function openImportModal() {
    var m = document.getElementById("import-modal");
    document.getElementById("import-file").value = "";
    var replaceEl = document.getElementById("import-replace");
    if (replaceEl) replaceEl.checked = false;
    var res = document.getElementById("import-results");
    res.classList.add("hidden");
    res.innerHTML = "";
    m.classList.remove("hidden");
  }

  function closeImportModal() {
    document.getElementById("import-modal").classList.add("hidden");
  }

  function downloadTemplate() {
    var rows = [
      "nombre,marca,categoria,cantidad,unidad,descripcion,precio,precio_original,stock,imagen,badge,destacado,activo",
      "Proteína Whey Gold Standard,Optimum Nutrition,Proteínas,2,lb,Proteína de suero aislada,899.00,1099.00,15,,MÁS VENDIDO,1,1",
      "Creatina Monohidratada,Universal,Creatina,300,g,Creatina micronizada,349.00,,30,,,0,1",
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

  function submitImport() {
    var input = document.getElementById("import-file");
    if (!input.files || !input.files[0]) {
      showAlert("Elige un archivo CSV primero", "error");
      return;
    }
    var replaceEl = document.getElementById("import-replace");
    var replaceAll = replaceEl && replaceEl.checked;
    if (replaceAll && !confirm("Vas a BORRAR todos los productos actuales y reemplazarlos por los del archivo. Esta acción no se puede deshacer. ¿Continuar?")) {
      return;
    }
    var btn = document.getElementById("import-submit");
    btn.disabled = true;
    btn.textContent = "Importando…";

    var fd = new FormData();
    fd.append("csv", input.files[0]);
    if (replaceAll) fd.append("replace_all", "1");

    DSAdminApi.apiFetch("../api/admin/products/import.php", { method: "POST", body: fd })
      .then(function (data) {
        var res = document.getElementById("import-results");
        var borrados = data.borrados
          ? '<p class="text-red-300 font-medium">🗑️ ' + data.borrados + ' producto(s) anterior(es) borrado(s)</p>'
          : '';
        var html =
          '<div class="bg-slate-900/60 border border-slate-600 rounded p-3">' + borrados +
          '<p class="text-green-300 font-semibold">✓ ' + data.creados + ' creados · ' +
          data.actualizados + ' actualizados · ' + data.categorias_creadas + ' categorías nuevas</p>';
        if (data.omitidos && data.omitidos.length) {
          html += '<p class="text-amber-300 mt-2 font-medium">' + data.omitidos.length + ' fila(s) omitida(s):</p>' +
            '<ul class="text-slate-400 text-xs mt-1 list-disc pl-5 space-y-0.5 max-h-40 overflow-auto">' +
            data.omitidos.map(function (o) {
              return "<li>Fila " + o.fila + ": " + esc(o.motivo) + "</li>";
            }).join("") + "</ul>";
        }
        html += "</div>";
        res.innerHTML = html;
        res.classList.remove("hidden");
        // Refrescar categorías (pudieron crearse nuevas) y la tabla.
        loadCategories().then(function () { loadProducts(1); });
      })
      .catch(function (err) { showAlert("Error al importar: " + err.message, "error"); })
      .finally(function () {
        btn.disabled = false;
        btn.textContent = "Importar";
      });
  }

  // ── init ──────────────────────────────────────────────────────────────────────
  document.addEventListener("DOMContentLoaded", function () {
    tableBody = document.getElementById("products-tbody");
    modal     = document.getElementById("product-modal");
    form      = document.getElementById("product-form");

    document.getElementById("new-product-btn").addEventListener("click", function () { openModal(null); });
    document.getElementById("modal-cancel").addEventListener("click", closeModal);
    form.addEventListener("submit", submitForm);

    // Importación CSV.
    document.getElementById("import-btn").addEventListener("click", openImportModal);
    document.getElementById("import-close").addEventListener("click", closeImportModal);
    document.getElementById("import-cancel").addEventListener("click", closeImportModal);
    document.getElementById("download-template").addEventListener("click", downloadTemplate);
    document.getElementById("import-submit").addEventListener("click", submitImport);

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
