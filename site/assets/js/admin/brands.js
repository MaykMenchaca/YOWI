/* Admin — gestión de marcas (brands) */
(function () {
  "use strict";

  var tableBody = null;
  var modal = null;
  var form  = null;
  var editingId = null;
  var lastFocused = null;
  var current = [];

  var esc = window.DSSec.esc; // definición única en security-utils.js

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

  function showModal() {
    lastFocused = document.activeElement;
    modal.classList.remove("hidden");
    var first = form.querySelector('input, select, textarea, button');
    if (first) first.focus();
  }

  function closeModal() {
    modal.classList.add("hidden");
    editingId = null;
    form.reset();
    if (lastFocused && lastFocused.focus) lastFocused.focus();
  }

  // ── tabla ─────────────────────────────────────────────────────────────────────
  function loadBrands() {
    return DSAdminApi.apiFetch("../api/admin/brands/list.php")
      .then(function (data) { current = data || []; renderTable(current); })
      .catch(function (err) { showAlert("Error al cargar: " + err.message, "error"); });
  }

  function renderTable(brands) {
    if (!tableBody) return;
    if (!brands || brands.length === 0) {
      tableBody.innerHTML = '<tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">Aún no hay marcas. Crea la primera con "+ Nueva marca" o importa un CSV.</td></tr>';
      return;
    }
    tableBody.innerHTML = brands.map(function (b) {
      var logo = b.imagen
        ? '<img src="../' + esc(b.imagen) + '" alt="" class="w-14 h-14 object-contain rounded bg-slate-900 p-1">'
        : '<div class="w-14 h-14 rounded bg-slate-900 flex items-center justify-center text-slate-600 text-xs">sin logo</div>';
      return '<tr class="border-b border-slate-700 hover:bg-slate-700/30">' +
        '<td class="px-3 py-3">' + logo + '</td>' +
        '<td class="px-3 py-3"><div class="text-slate-200 font-medium">' + esc(b.nombre) + '</div>' +
          (b.enlace ? '<div class="text-slate-400 text-xs truncate max-w-xs">' + esc(b.enlace) + '</div>' : '') + '</td>' +
        '<td class="px-3 py-3 text-center text-slate-300">' + b.orden + '</td>' +
        '<td class="px-3 py-3 text-center">' +
          '<span class="px-2 py-0.5 rounded text-xs ' + (b.activo ? 'bg-blue-900/50 text-blue-300' : 'bg-slate-700 text-slate-400') + '">' +
            (b.activo ? 'Visible' : 'Oculto') + '</span></td>' +
        '<td class="px-3 py-3 whitespace-nowrap">' +
          '<button class="btn-link-brand mr-2 edit-btn" data-id="' + b.id + '"><span class="material-symbols-outlined text-sm">edit</span>Editar</button>' +
          '<button class="btn-link-danger delete-btn" data-id="' + b.id + '"><span class="material-symbols-outlined text-sm">delete</span>Eliminar</button>' +
        '</td>' +
      '</tr>';
    }).join("");

    tableBody.querySelectorAll(".edit-btn").forEach(function (btn) {
      btn.addEventListener("click", function () { openModal(parseInt(btn.dataset.id, 10)); });
    });
    tableBody.querySelectorAll(".delete-btn").forEach(function (btn) {
      btn.addEventListener("click", function () { removeBrand(parseInt(btn.dataset.id, 10)); });
    });
  }

  // ── modal ─────────────────────────────────────────────────────────────────────
  function openModal(id) {
    editingId = id || null;
    document.getElementById("modal-title").textContent = editingId ? "Editar marca" : "Nueva marca";
    form.reset();
    document.getElementById("current-imagen").value = "";
    var prev = document.getElementById("image-preview");
    prev.src = ""; prev.classList.add("hidden");

    if (editingId) {
      var b = current.filter(function (x) { return x.id === editingId; })[0];
      if (b) fillForm(b);
    }
    showModal();
  }

  function fillForm(b) {
    form.querySelector('[name="nombre"]').value = b.nombre || "";
    form.querySelector('[name="enlace"]').value = b.enlace || "";
    form.querySelector('[name="orden"]').value  = b.orden != null ? b.orden : 0;
    form.querySelector('[name="activo"]').checked = b.activo !== false;
    document.getElementById("current-imagen").value = b.imagen || "";
    if (b.imagen) {
      var prev = document.getElementById("image-preview");
      prev.src = "../" + b.imagen;
      prev.classList.remove("hidden");
    }
  }

  function removeBrand(id) {
    if (!confirm("¿Eliminar esta marca? Ya no se mostrará en la página de marcas.")) return;
    DSAdminApi.apiFetch("../api/admin/brands/delete.php", { method: "POST", body: { id: id } })
      .then(function () { loadBrands(); showAlert("Marca eliminada", "success"); })
      .catch(function (err) { showAlert(err.message, "error"); });
  }

  // ── submit ────────────────────────────────────────────────────────────────────
  function submitForm(e) {
    e.preventDefault();

    var nombre = form.querySelector('[name="nombre"]').value.trim();
    if (!nombre) { showAlert("El nombre de la marca es obligatorio.", "error"); return; }

    var fileInput = form.querySelector('[name="imagen_file"]');
    var currentImg = document.getElementById("current-imagen").value;
    var imagePromise = Promise.resolve(null);

    if (fileInput && fileInput.files && fileInput.files[0]) {
      var fd = new FormData();
      fd.append("imagen", fileInput.files[0]);
      imagePromise = DSAdminApi.apiFetch("../api/admin/brands/upload-image.php", { method: "POST", body: fd });
    }

    imagePromise.then(function (imgData) {
      var body = {
        nombre: nombre,
        imagen: imgData ? imgData.url : (currentImg || ""),
        enlace: form.querySelector('[name="enlace"]').value.trim() || null,
        orden:  parseInt(form.querySelector('[name="orden"]').value, 10) || 0,
        activo: form.querySelector('[name="activo"]').checked ? 1 : 0,
      };
      if (editingId) body.id = editingId;
      var path = editingId ? "../api/admin/brands/update.php" : "../api/admin/brands/create.php";
      return DSAdminApi.apiFetch(path, { method: "POST", body: body });
    })
      .then(function () {
        closeModal();
        loadBrands();
        showAlert(editingId ? "Marca actualizada" : "Marca creada", "success");
      })
      .catch(function (err) { showAlert(err.message, "error"); });
  }

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
    var res = document.getElementById("import-results");
    res.classList.add("hidden");
    res.innerHTML = "";
    m.classList.remove("hidden");
  }

  function closeImportModal() {
    document.getElementById("import-modal").classList.add("hidden");
  }

  // Vaciar marcas: borra TODAS (acción destructiva, confirmación por escrito).
  function wipeBrands() {
    var typed = prompt("Esto BORRA todas las marcas y no se puede deshacer.\n\nEscribe BORRAR (en mayúsculas) para confirmar:");
    if (typed !== "BORRAR") {
      if (typed !== null) showAlert("Cancelado: no escribiste BORRAR.", "error");
      return;
    }
    DSAdminApi.apiFetch("../api/admin/brands/delete-all.php", { method: "POST", body: { confirm: "BORRAR" } })
      .then(function (data) {
        loadBrands();
        showAlert((data.borrados || 0) + " marca(s) borrada(s).", "success");
      })
      .catch(function (err) { showAlert("Error al vaciar: " + err.message, "error"); });
  }

  function downloadTemplate() {
    var rows = [
      "nombre,imagen,enlace,orden,activo",
      "Optimum Nutrition,,https://www.optimumnutrition.com,1,1",
      "GAT Sport,,,2,1",
    ].join("\r\n");
    var blob = new Blob(["﻿" + rows], { type: "text/csv;charset=utf-8;" });
    var url = URL.createObjectURL(blob);
    var a = document.createElement("a");
    a.href = url;
    a.download = "plantilla-marcas.csv";
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
    var btn = document.getElementById("import-submit");
    btn.disabled = true;
    btn.textContent = "Importando…";

    var fd = new FormData();
    fd.append("csv", input.files[0]);

    DSAdminApi.apiFetch("../api/admin/brands/import.php", { method: "POST", body: fd })
      .then(function (data) {
        var res = document.getElementById("import-results");
        var html =
          '<div class="bg-slate-900/60 border border-slate-600 rounded p-3">' +
          '<p class="text-green-300 font-semibold">✓ ' + data.creados + ' creadas · ' +
          data.actualizados + ' actualizadas</p>';
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
        loadBrands();
      })
      .catch(function (err) { showAlert("Error al importar: " + err.message, "error"); })
      .finally(function () {
        btn.disabled = false;
        btn.textContent = "Importar";
      });
  }

  document.addEventListener("DOMContentLoaded", function () {
    tableBody = document.getElementById("brands-tbody");
    modal     = document.getElementById("brand-modal");
    form      = document.getElementById("brand-form");

    document.getElementById("new-brand-btn").addEventListener("click", function () { openModal(null); });
    document.getElementById("modal-cancel").addEventListener("click", closeModal);
    // Botón "Cancelar" del pie del modal (antes era un <script> inline en marcas.html).
    var cancelBtn = document.getElementById("modal-cancel-btn");
    if (cancelBtn) cancelBtn.addEventListener("click", closeModal);
    form.addEventListener("submit", submitForm);

    document.getElementById("wipe-brands-btn").addEventListener("click", wipeBrands);
    document.getElementById("import-btn").addEventListener("click", openImportModal);
    document.getElementById("import-close").addEventListener("click", closeImportModal);
    document.getElementById("import-cancel").addEventListener("click", closeImportModal);
    document.getElementById("download-template").addEventListener("click", downloadTemplate);
    document.getElementById("import-submit").addEventListener("click", submitImport);

    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && !modal.classList.contains("hidden")) closeModal();
    });

    setupImagePreview();
    loadBrands();
  });
})();
