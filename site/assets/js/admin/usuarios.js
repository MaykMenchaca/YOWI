/* Admin — gestión de usuarios del panel (cuentas de empleados con rol). Solo dueño. */
(function () {
  "use strict";

  var tableBody = null;
  var modal      = null;
  var form       = null;
  var modalTitle = null;
  var editingId  = null;
  var lastFocused = null;

  var resetModal = null;
  var resetForm  = null;
  var resetTargetId = null;
  var resetLastFocused = null;

  var currentUsers = []; // caché en memoria de la última carga, para no reparsear el DOM

  var ROL_LABEL = { dueno: "Dueño", operador: "Operador", lectura: "Solo lectura" };

  function loadUsers() {
    DSAdminApi.apiFetch("../api/admin/admins/list.php")
      .then(function (data) { currentUsers = data || []; renderTable(currentUsers); })
      .catch(function (err) { showAlert("Error al cargar usuarios: " + err.message, "error"); });
  }

  function findUser(id) {
    id = parseInt(id, 10);
    for (var i = 0; i < currentUsers.length; i++) {
      if (currentUsers[i].id === id) return currentUsers[i];
    }
    return null;
  }

  function renderTable(users) {
    if (!tableBody) return;
    if (!users || users.length === 0) {
      tableBody.innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Sin usuarios aún</td></tr>';
      return;
    }
    var miId = window.DSAdmin ? window.DSAdmin.id : null;
    tableBody.innerHTML = users.map(function (u) {
      var esMio = miId !== null && u.id === miId;
      var estadoBadge = u.activo
        ? '<span class="text-xs px-2 py-0.5 rounded bg-green-900/50 text-green-300 border border-green-700">Activa</span>'
        : '<span class="text-xs px-2 py-0.5 rounded bg-slate-700 text-slate-400 border border-slate-600">Desactivada</span>';
      var totpBadge = u.totp_enabled
        ? '<span class="text-xs px-2 py-0.5 rounded bg-green-900/50 text-green-300 border border-green-700">Sí</span>'
        : '<span class="text-xs px-2 py-0.5 rounded bg-amber-900/50 text-amber-300 border border-amber-700">No</span>';

      var acciones = '<button type="button" class="btn-link-brand mr-3 edit-btn" data-id="' + u.id + '"><span class="material-symbols-outlined text-sm">edit</span>Editar</button>' +
        '<button type="button" class="btn-link-brand mr-3 reset-btn" data-id="' + u.id + '"><span class="material-symbols-outlined text-sm">key</span>Contraseña</button>';
      if (!esMio) {
        acciones += u.activo
          ? '<button type="button" class="btn-link-brand mr-3 toggle-btn" data-id="' + u.id + '"><span class="material-symbols-outlined text-sm">block</span>Desactivar</button>'
          : '<button type="button" class="btn-link-brand mr-3 toggle-btn" data-id="' + u.id + '"><span class="material-symbols-outlined text-sm">check_circle</span>Reactivar</button>';
        acciones += '<button type="button" class="btn-link-danger delete-btn" data-id="' + u.id + '"><span class="material-symbols-outlined text-sm">delete</span>Eliminar</button>';
      }

      return '<tr class="border-b border-slate-700 hover:bg-slate-700/30">' +
        '<td class="px-4 py-3 text-slate-200">' + esc(u.nombre) + (esMio ? ' <span class="text-slate-500 text-xs">(tú)</span>' : '') + '</td>' +
        '<td class="px-4 py-3 text-slate-400">' + esc(u.email) + '</td>' +
        '<td class="px-4 py-3 text-slate-300">' + esc(ROL_LABEL[u.rol] || u.rol) + '</td>' +
        '<td class="px-4 py-3 text-center">' + totpBadge + '</td>' +
        '<td class="px-4 py-3 text-center">' + estadoBadge + '</td>' +
        '<td class="px-4 py-3 whitespace-nowrap">' + acciones + '</td>' +
      '</tr>';
    }).join("");

    tableBody.querySelectorAll(".edit-btn").forEach(function (btn) {
      btn.addEventListener("click", function () { openModal(findUser(btn.dataset.id)); });
    });
    tableBody.querySelectorAll(".reset-btn").forEach(function (btn) {
      btn.addEventListener("click", function () { openResetModal(findUser(btn.dataset.id)); });
    });
    tableBody.querySelectorAll(".toggle-btn").forEach(function (btn) {
      btn.addEventListener("click", function () { toggleActivo(findUser(btn.dataset.id)); });
    });
    tableBody.querySelectorAll(".delete-btn").forEach(function (btn) {
      btn.addEventListener("click", function () { deleteUser(findUser(btn.dataset.id)); });
    });
  }

  function openModal(u) {
    editingId = u ? u.id : null;
    var esMio = !!(u && window.DSAdmin && u.id === window.DSAdmin.id);

    modalTitle.textContent = editingId ? "Editar usuario" : "Nuevo usuario";
    form.querySelector('[name="nombre"]').value = u ? u.nombre : "";
    form.querySelector('[name="email"]').value  = u ? u.email : "";

    var rolSelect = form.querySelector('[name="rol"]');
    rolSelect.value = u ? u.rol : "lectura";
    // El servidor rechaza que un usuario cambie su propio rol (ds_require_rol lo
    // exigiría de todas formas) — deshabilitarlo aquí evita un 403 confuso al guardar.
    rolSelect.disabled = esMio;

    // La contraseña inicial solo se pide al CREAR; para cambiarla en una cuenta
    // existente está el modal de "Restablecer contraseña" (acción de fila aparte).
    var pwField = document.getElementById("password-field");
    pwField.classList.toggle("hidden", !!editingId);
    form.querySelector('[name="password"]').required = !editingId;

    // "activo" solo aplica al editar (una cuenta nueva siempre nace activa), y nunca a
    // la propia cuenta (el servidor tampoco te deja desactivarte a ti mismo).
    var activoField = document.getElementById("activo-field");
    activoField.classList.toggle("hidden", !editingId || esMio);
    document.getElementById("activo-input").checked = u ? !!u.activo : true;

    lastFocused = document.activeElement;
    modal.classList.remove("hidden");
    var first = form.querySelector('input:not([type=hidden]):not(.hidden), select');
    if (first) first.focus();
  }

  function closeModal() {
    modal.classList.add("hidden");
    editingId = null;
    form.reset();
    form.querySelector('[name="rol"]').disabled = false;
    if (lastFocused && lastFocused.focus) lastFocused.focus();
  }

  function openResetModal(u) {
    if (!u) return;
    resetTargetId = u.id;
    document.getElementById("reset-pw-target").textContent = "Cuenta: " + u.nombre + " (" + u.email + ")";
    resetLastFocused = document.activeElement;
    resetModal.classList.remove("hidden");
    var first = resetForm.querySelector('input');
    if (first) first.focus();
  }

  function closeResetModal() {
    resetModal.classList.add("hidden");
    resetTargetId = null;
    resetForm.reset();
    if (resetLastFocused && resetLastFocused.focus) resetLastFocused.focus();
  }

  function toggleActivo(u) {
    if (!u) return;
    var accion = u.activo ? "desactivar" : "reactivar";
    if (!confirm('¿Seguro que quieres ' + accion + ' la cuenta de "' + u.nombre + '"?')) return;
    DSAdminApi.apiFetch("../api/admin/admins/update.php", {
      method: "POST",
      body: { id: u.id, nombre: u.nombre, email: u.email, rol: u.rol, activo: !u.activo },
    })
      .then(function () {
        loadUsers();
        showAlert(u.activo ? "Cuenta desactivada" : "Cuenta reactivada", "success");
      })
      .catch(function (err) { showAlert(err.message, "error"); });
  }

  function deleteUser(u) {
    if (!u) return;
    if (!confirm('¿Eliminar la cuenta de "' + u.nombre + '"? Esto no se puede deshacer (para una baja normal, usa "Desactivar" en vez de esto).')) return;
    DSAdminApi.apiFetch("../api/admin/admins/delete.php", { method: "POST", body: { id: u.id } })
      .then(function () { loadUsers(); showAlert("Cuenta eliminada", "success"); })
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

  var esc = window.DSSec.esc; // definición única en security-utils.js

  document.addEventListener("DOMContentLoaded", function () {
    tableBody  = document.getElementById("users-tbody");
    modal      = document.getElementById("user-modal");
    form       = document.getElementById("user-form");
    modalTitle = document.getElementById("modal-title");
    resetModal = document.getElementById("reset-pw-modal");
    resetForm  = document.getElementById("reset-pw-form");

    document.getElementById("new-user-btn").addEventListener("click", function () { openModal(null); });
    document.getElementById("modal-cancel").addEventListener("click", closeModal);
    var cancelBtn = document.getElementById("modal-cancel-btn");
    if (cancelBtn) cancelBtn.addEventListener("click", closeModal);

    document.getElementById("reset-pw-cancel").addEventListener("click", closeResetModal);
    document.getElementById("reset-pw-cancel-btn").addEventListener("click", closeResetModal);

    document.addEventListener("keydown", function (e) {
      if (e.key !== "Escape") return;
      if (!modal.classList.contains("hidden")) closeModal();
      if (!resetModal.classList.contains("hidden")) closeResetModal();
    });

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var nombre = form.querySelector('[name="nombre"]').value.trim();
      var email  = form.querySelector('[name="email"]').value.trim();
      var rol    = form.querySelector('[name="rol"]').value;

      if (editingId) {
        var activo = document.getElementById("activo-input").checked;
        DSAdminApi.apiFetch("../api/admin/admins/update.php", {
          method: "POST",
          body: { id: editingId, nombre: nombre, email: email, rol: rol, activo: activo },
        })
          .then(function () { closeModal(); loadUsers(); showAlert("Usuario actualizado", "success"); })
          .catch(function (err) { showAlert(err.message, "error"); });
      } else {
        var password = form.querySelector('[name="password"]').value;
        DSAdminApi.apiFetch("../api/admin/admins/create.php", {
          method: "POST",
          body: { nombre: nombre, email: email, password: password, rol: rol },
        })
          .then(function () { closeModal(); loadUsers(); showAlert("Usuario creado", "success"); })
          .catch(function (err) { showAlert(err.message, "error"); });
      }
    });

    resetForm.addEventListener("submit", function (e) {
      e.preventDefault();
      var password = resetForm.querySelector('[name="password"]').value;
      DSAdminApi.apiFetch("../api/admin/admins/reset-password.php", {
        method: "POST",
        body: { id: resetTargetId, password: password },
      })
        .then(function () { closeResetModal(); showAlert("Contraseña restablecida", "success"); })
        .catch(function (err) { showAlert(err.message, "error"); });
    });

    loadUsers();
  });
})();
