// Lógica del dashboard admin (index.html). Externalizada del <script> inline para
// permitir CSP script-src 'self'. Carga métricas + tabla de pedidos recientes.
document.addEventListener("DOMContentLoaded", function () {
  // Métricas simples: productos, pedidos pendientes
  Promise.all([
    DSAdminApi.apiFetch("../api/admin/products/list.php?limit=1"),
    DSAdminApi.apiFetch("../api/admin/orders/list.php?limit=5"),
    DSAdminApi.apiFetch("../api/admin/orders/list.php?limit=1&estado=pendiente"),
    DSAdminApi.apiFetch("../api/admin/categories/list.php"),
  ]).then(function (results) {
    var prods   = results[0];
    var orders  = results[1];
    var pending = results[2];
    var cats    = results[3];

    var statsEl = document.getElementById("stats-grid");
    // Número en blanco (Barlow Condensed) + barra de acento izquierda por métrica (paleta gimnasio).
    statsEl.innerHTML = [
      stat("Productos", prods.total, "#1F5FD9"),
      stat("Categorías", cats.length, "#8FD11F"),
      stat("Pedidos totales", orders.total, "#22D3EE"),
      stat("Pendientes", pending.total, "#F5B544"),
    ].join("");

    // Últimos pedidos
    var recentEl = document.getElementById("recent-orders");
    if (!orders.data || orders.data.length === 0) {
      recentEl.textContent = "Sin pedidos aún.";
      return;
    }
    recentEl.innerHTML = '<table class="w-full text-sm">' +
      '<thead><tr class="text-left text-slate-500 border-b border-slate-700">' +
        '<th class="pb-2">ID</th><th class="pb-2">Cliente</th><th class="pb-2">Total</th><th class="pb-2">Estado</th><th class="pb-2">Fecha</th>' +
      '</tr></thead><tbody>' +
      orders.data.map(function (o) {
        return '<tr class="border-b border-slate-700/50 hover:bg-slate-700/20">' +
          '<td class="py-2 font-mono text-slate-400">#' + o.id + '</td>' +
          '<td class="py-2 text-slate-200">' + esc(o.nombre_cliente) + '</td>' +
          '<td class="py-2 text-slate-300">$' + Number(o.total).toLocaleString("es-MX", {minimumFractionDigits:2}) + '</td>' +
          '<td class="py-2"><span class="text-xs px-2 py-0.5 rounded bg-slate-700 text-slate-300">' + esc(o.estado) + '</span></td>' +
          '<td class="py-2 text-slate-400 text-xs">' + esc(o.created_at) + '</td>' +
        '</tr>';
      }).join("") +
      '</tbody></table>';
  }).catch(function (err) {
    document.getElementById("stats-grid").innerHTML = '<p class="text-red-400 text-sm col-span-4">' + esc(err.message) + '</p>';
  });

  function stat(label, value, accent) {
    return '<div class="bg-slate-800 rounded-none p-5 border border-slate-700 border-l-[3px]" style="border-left-color:' + accent + '">' +
      '<p class="text-slate-400 text-xs font-bold uppercase tracking-wide mb-1">' + label + '</p>' +
      '<p class="font-display font-extrabold text-4xl text-white">' + (value !== undefined ? value : '—') + '</p>' +
    '</div>';
  }
  var esc = window.DSSec.esc; // definición única en security-utils.js
});
