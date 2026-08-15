/* Admin — lista de clientes y borrado/anonimización de cuentas. Solo dueño (vista
   cargada de datos personales, mismo criterio que los respaldos). */
(function () {
  "use strict";

  var tableBody = null;
  var currentClientes = []; // caché en memoria de la última carga

  var esc = window.DSSec.esc; // definición única en security-utils.js

  function loadClientes() {
    DSAdminApi.apiFetch("../api/admin/clientes/list.php")
      .then(function (data) { currentClientes = data || []; renderTable(currentClientes); })
      .catch(function (err) { showAlert("Error al cargar clientes: " + err.message, "error"); });
  }

  function findCliente(id) {
    id = parseInt(id, 10);
    for (var i = 0; i < currentClientes.length; i++) {
      if (currentClientes[i].id === id) return currentClientes[i];
    }
    return null;
  }

  function fechaCorta(iso) {
    if (!iso) return "—";
    return String(iso).slice(0, 10);
  }

  function renderTable(clientes) {
    if (!tableBody) return;
    if (!clientes || clientes.length === 0) {
      tableBody.innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Sin clientes registrados aún</td></tr>';
      return;
    }
    tableBody.innerHTML = clientes.map(function (c) {
      return '<tr class="border-b border-slate-700 hover:bg-slate-700/30">' +
        '<td class="px-4 py-3 text-slate-200">' + esc(c.nombre) + '</td>' +
        '<td class="px-4 py-3 text-slate-400">' + esc(c.email) + '</td>' +
        '<td class="px-4 py-3 text-slate-400">' + esc(c.telefono || "—") + '</td>' +
        '<td class="px-4 py-3 text-center text-slate-300">' + c.pedidos_count + '</td>' +
        '<td class="px-4 py-3 text-slate-400">' + esc(fechaCorta(c.created_at)) + '</td>' +
        '<td class="px-4 py-3 whitespace-nowrap">' +
          '<button type="button" class="btn-link-danger delete-btn" data-id="' + c.id + '">' +
            '<span class="material-symbols-outlined text-sm">delete</span><span class="hidden md:inline">Eliminar</span>' +
          '</button>' +
        '</td>' +
      '</tr>';
    }).join("");

    tableBody.querySelectorAll(".delete-btn").forEach(function (btn) {
      btn.addEventListener("click", function () { deleteCliente(findCliente(btn.dataset.id)); });
    });
  }

  function deleteCliente(c) {
    if (!c) return;
    var msg = '¿Eliminar la cuenta de "' + c.nombre + '"? Se borran su perfil, direcciones y ' +
      'favoritos.' + (c.pedidos_count > 0
        ? ' Tiene ' + c.pedidos_count + ' pedido(s): se conservan, pero sin su nombre, teléfono ni domicilio.'
        : '') +
      ' Esta acción no se puede deshacer.';
    if (!confirm(msg)) return;
    DSAdminApi.apiFetch("../api/admin/clientes/delete.php", { method: "POST", body: { id: c.id } })
      .then(function () { loadClientes(); showAlert("Cliente eliminado", "success"); })
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

  document.addEventListener("DOMContentLoaded", function () {
    tableBody = document.getElementById("clientes-tbody");
    loadClientes();
  });
})();
