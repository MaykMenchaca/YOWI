/* Admin — historial de auditoría (admin_audit_log), solo lectura. Solo dueño. */
(function () {
  "use strict";

  var tableBody = null;
  var esc = window.DSSec.esc; // definición única en security-utils.js

  function fechaCorta(iso) {
    if (!iso) return "—";
    return String(iso).slice(0, 16).replace("T", " ");
  }

  function buildQuery() {
    var params = [];
    var adminId = (document.getElementById("filter-admin") || {}).value || "";
    var accion = (document.getElementById("filter-accion") || {}).value || "";
    var desde = (document.getElementById("filter-desde") || {}).value || "";
    var hasta = (document.getElementById("filter-hasta") || {}).value || "";
    if (adminId) params.push("admin_id=" + encodeURIComponent(adminId));
    if (accion) params.push("accion=" + encodeURIComponent(accion));
    if (desde) params.push("desde=" + encodeURIComponent(desde));
    if (hasta) params.push("hasta=" + encodeURIComponent(hasta));
    return params.length ? "?" + params.join("&") : "";
  }

  function populateSelect(sel, items, valueKey, labelFn, currentValue) {
    if (!sel) return;
    var html = sel.options[0].outerHTML; // conserva "Todos/Todas los..."
    items.forEach(function (item) {
      var value = valueKey ? item[valueKey] : item;
      var label = labelFn ? labelFn(item) : item;
      html += '<option value="' + esc(String(value)) + '">' + esc(label) + "</option>";
    });
    sel.innerHTML = html;
    if (currentValue) sel.value = currentValue;
  }

  function load() {
    var adminSel = document.getElementById("filter-admin");
    var accionSel = document.getElementById("filter-accion");
    var keepAdmin = adminSel ? adminSel.value : "";
    var keepAccion = accionSel ? accionSel.value : "";

    DSAdminApi.apiFetch("../api/admin/audit/list.php" + buildQuery())
      .then(function (data) {
        populateSelect(adminSel, data.admins || [], "id", function (a) {
          return a.nombre + " (" + a.email + ")";
        }, keepAdmin);
        populateSelect(accionSel, data.acciones || [], null, null, keepAccion);
        renderTable(data.rows || []);
      })
      .catch(function (err) { showAlert("Error al cargar auditoría: " + err.message, "error"); });
  }

  function renderTable(rows) {
    if (!tableBody) return;
    if (!rows || rows.length === 0) {
      tableBody.innerHTML = '<tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">Sin registros para estos filtros</td></tr>';
      return;
    }
    tableBody.innerHTML = rows.map(function (r) {
      var admin = r.admin_id
        ? esc(r.admin_nombre || "") + '<br><span class="text-slate-500 text-xs">' + esc(r.admin_email || "") + "</span>"
        : '<span class="text-slate-500">— (cuenta eliminada)</span>';
      return '<tr class="border-b border-slate-700 hover:bg-slate-700/30">' +
        '<td class="px-4 py-3 text-slate-400 whitespace-nowrap">' + esc(fechaCorta(r.created_at)) + '</td>' +
        '<td class="px-4 py-3 text-slate-200">' + admin + '</td>' +
        '<td class="px-4 py-3 text-slate-300 font-mono text-xs">' + esc(r.accion) + '</td>' +
        '<td class="px-4 py-3 text-slate-400">' + esc(r.detalle || "—") + '</td>' +
        '<td class="px-4 py-3 text-slate-400 font-mono text-xs">' + esc(r.ip || "—") + '</td>' +
      '</tr>';
    }).join("");
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
    tableBody = document.getElementById("auditoria-tbody");
    ["filter-admin", "filter-accion", "filter-desde", "filter-hasta"].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.addEventListener("change", load);
    });
    load();
  });
})();
