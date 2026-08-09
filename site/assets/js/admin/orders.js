/* Admin — gestión de pedidos */
(function () {
  "use strict";

  var tableBody  = null;
  var currentPage = 1;
  var LIMIT = 20;

  var ESTADOS = ["pendiente", "confirmado", "enviado", "entregado", "cancelado"];
  var ESTADO_COLORS = {
    pendiente:   "bg-yellow-900/50 text-yellow-300 border-yellow-700",
    confirmado:  "bg-blue-900/50 text-blue-300 border-blue-700",
    enviado:     "bg-purple-900/50 text-purple-300 border-purple-700",
    entregado:   "bg-green-900/50 text-green-300 border-green-700",
    cancelado:   "bg-red-900/50 text-red-300 border-red-700",
  };

  var esc = window.DSSec.esc; // definición única en security-utils.js

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
    setTimeout(function () { el.classList.add("hidden"); }, 4000);
  }

  function loadOrders(page) {
    currentPage = page || 1;
    var estado = (document.getElementById("filter-estado") || {}).value || "";
    var url = "../api/admin/orders/list.php?page=" + currentPage + "&limit=" + LIMIT;
    if (estado) url += "&estado=" + encodeURIComponent(estado);

    DSAdminApi.apiFetch(url)
      .then(function (data) {
        renderTable(data.data);
        renderPagination(data.total, data.page, data.limit);
      })
      .catch(function (err) { showAlert("Error: " + err.message, "error"); });
  }

  function renderTable(orders) {
    if (!tableBody) return;
    if (!orders || orders.length === 0) {
      tableBody.innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Sin pedidos</td></tr>';
      return;
    }
    tableBody.innerHTML = orders.map(function (o) {
      var colorClass = ESTADO_COLORS[o.estado] || "bg-slate-700 text-slate-300";
      var itemsHtml = (o.items || []).map(function (i) {
        return '<li class="text-xs text-slate-400">' + esc(i.nombre) + ' × ' + i.cantidad + ' = ' + money(i.subtotal) + '</li>';
      }).join("");

      var statusOptions = ESTADOS.map(function (s) {
        return '<option value="' + s + '"' + (s === o.estado ? ' selected' : '') + '>' + s + '</option>';
      }).join("");

      return '<tr class="border-b border-slate-700">' +
        '<td class="px-3 py-3 text-slate-300 font-mono">#' + o.id + '</td>' +
        '<td class="px-3 py-3">' +
          '<div class="text-slate-200">' + esc(o.nombre_cliente) + '</div>' +
          '<div class="text-slate-400 text-xs">' + esc(o.telefono || '') + (o.ciudad ? ' · ' + esc(o.ciudad) : '') + '</div>' +
          (o.direccion_envio ? '<div class="text-slate-400 text-xs mt-0.5">📦 ' + esc(o.direccion_envio) + '</div>' : '') +
          (o.user_email ? '<div class="text-slate-500 text-xs">' + esc(o.user_email) + '</div>' : '') +
        '</td>' +
        '<td class="px-3 py-3">' +
          '<ul class="space-y-0.5">' + itemsHtml + '</ul>' +
        '</td>' +
        '<td class="px-3 py-3 text-slate-200 font-medium">' + money(o.total) + '</td>' +
        '<td class="px-3 py-3">' +
          '<span class="px-2 py-0.5 rounded border text-xs ' + colorClass + '">' + esc(o.estado) + '</span>' +
        '</td>' +
        '<td class="px-3 py-3">' +
          '<select class="status-select bg-slate-800 border border-slate-600 text-slate-300 text-xs rounded px-2 py-1" data-id="' + o.id + '">' + statusOptions + '</select>' +
        '</td>' +
      '</tr>';
    }).join("");

    tableBody.querySelectorAll(".status-select").forEach(function (sel) {
      sel.addEventListener("change", function () {
        updateStatus(parseInt(sel.dataset.id, 10), sel.value);
      });
    });
  }

  function updateStatus(id, estado) {
    DSAdminApi.apiFetch("../api/admin/orders/update-status.php", {
      method: "POST",
      body: { id: id, estado: estado },
    })
      .then(function () { showAlert("Estado actualizado", "success"); })
      .catch(function (err) { showAlert(err.message, "error"); loadOrders(currentPage); });
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
      btn.addEventListener("click", function () { loadOrders(parseInt(btn.dataset.page, 10)); });
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    tableBody = document.getElementById("orders-tbody");

    var filterEstado = document.getElementById("filter-estado");
    if (filterEstado) filterEstado.addEventListener("change", function () { loadOrders(1); });

    loadOrders(1);
  });
})();
