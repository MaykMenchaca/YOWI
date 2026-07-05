/* Admin — gestión de categorías */
(function () {
  "use strict";

  var tableBody = null;
  var modal     = null;
  var form      = null;
  var modalTitle = null;
  var editingId  = null;
  var lastFocused = null; // elemento que abrió el modal, para devolver el foco al cerrar

  function loadCategories() {
    DSAdminApi.apiFetch("../api/admin/categories/list.php")
      .then(function (data) { renderTable(data); })
      .catch(function (err) { showAlert("Error al cargar categorías: " + err.message, "error"); });
  }

  function renderTable(cats) {
    if (!tableBody) return;
    if (!cats || cats.length === 0) {
      tableBody.innerHTML = '<tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">Sin categorías aún</td></tr>';
      return;
    }
    tableBody.innerHTML = cats.map(function (c) {
      return '<tr class="border-b border-slate-700 hover:bg-slate-700/30">' +
        '<td class="px-4 py-3 text-slate-200">' + esc(c.nombre) + '</td>' +
        '<td class="px-4 py-3 text-slate-400 font-mono text-sm">' + esc(c.slug) + '</td>' +
        '<td class="px-4 py-3 text-slate-400 text-center">' + c.orden + '</td>' +
        '<td class="px-4 py-3 text-slate-400 text-center">' + c.productos_count + '</td>' +
        '<td class="px-4 py-3">' +
          '<button class="text-blue-400 hover:text-blue-300 mr-3 edit-btn" data-id="' + c.id + '" data-nombre="' + esc(c.nombre) + '" data-orden="' + c.orden + '">Editar</button>' +
          '<button class="text-red-400 hover:text-red-300 delete-btn" data-id="' + c.id + '" data-count="' + c.productos_count + '">Eliminar</button>' +
        '</td>' +
      '</tr>';
    }).join("");

    tableBody.querySelectorAll(".edit-btn").forEach(function (btn) {
      btn.addEventListener("click", function () {
        openModal(btn.dataset.id, btn.dataset.nombre, btn.dataset.orden);
      });
    });
    tableBody.querySelectorAll(".delete-btn").forEach(function (btn) {
      btn.addEventListener("click", function () { deleteCategory(btn.dataset.id, btn.dataset.count); });
    });
  }

  function openModal(id, nombre, orden) {
    editingId = id ? parseInt(id, 10) : null;
    modalTitle.textContent = editingId ? "Editar categoría" : "Nueva categoría";
    form.querySelector('[name="nombre"]').value = nombre || "";
    form.querySelector('[name="orden"]').value  = orden !== undefined ? orden : "0";
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

  function deleteCategory(id, count) {
    if (parseInt(count) > 0) {
      showAlert("No se puede eliminar: la categoría tiene productos activos.", "error");
      return;
    }
    if (!confirm("¿Eliminar esta categoría?")) return;
    DSAdminApi.apiFetch("../api/admin/categories/delete.php", {
      method: "POST",
      body: { id: parseInt(id, 10) },
    })
      .then(function () { loadCategories(); showAlert("Categoría eliminada", "success"); })
      .catch(function (err) { showAlert(err.message, "error"); });
  }

  function showAlert(msg, type) {
    var el = document.getElementById("alert-banner");
    if (!el) return;
    el.textContent = msg;
    el.className = "mb-4 px-4 py-3 rounded text-sm font-medium " +
      (type === "success" ? "bg-green-900/50 text-green-300 border border-green-700" : "bg-red-900/50 text-red-300 border border-red-700");
    el.classList.remove("hidden");
    setTimeout(function () { el.classList.add("hidden"); }, 4000);
  }

  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;" }[c];
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    tableBody  = document.getElementById("categories-tbody");
    modal      = document.getElementById("cat-modal");
    form       = document.getElementById("cat-form");
    modalTitle = document.getElementById("modal-title");

    document.getElementById("new-category-btn").addEventListener("click", function () {
      openModal(null, "", 0);
    });
    document.getElementById("modal-cancel").addEventListener("click", closeModal);

    // Cerrar con Escape cuando el modal está abierto.
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && !modal.classList.contains("hidden")) closeModal();
    });

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var nombre = form.querySelector('[name="nombre"]').value.trim();
      var orden  = parseInt(form.querySelector('[name="orden"]').value, 10) || 0;
      var path   = editingId
        ? "../api/admin/categories/update.php"
        : "../api/admin/categories/create.php";
      var body   = editingId
        ? { id: editingId, nombre: nombre, orden: orden }
        : { nombre: nombre, orden: orden };

      DSAdminApi.apiFetch(path, { method: "POST", body: body })
        .then(function () {
          closeModal();
          loadCategories();
          showAlert(editingId ? "Categoría actualizada" : "Categoría creada", "success");
        })
        .catch(function (err) { showAlert(err.message, "error"); });
    });

    loadCategories();
  });
})();
