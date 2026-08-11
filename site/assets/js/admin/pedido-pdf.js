/* Admin — nota de compra (recibo imprimible) de un pedido. */
(function () {
  "use strict";

  var esc = window.DSSec.esc;

  var ESTADO_LABELS = {
    pendiente: "Pendiente",
    confirmado: "Confirmado",
    enviado: "Enviado",
    entregado: "Entregado",
    cancelado: "Cancelado",
  };

  function money(n) {
    return "$" + Number(n).toLocaleString("es-MX", { minimumFractionDigits: 2 });
  }

  // created_at llega como "YYYY-MM-DD HH:MM:SS" (MySQL); no todos los navegadores
  // parsean ese formato con espacio de forma confiable vía Date(), así que se
  // reemplaza por "T" antes de construir la fecha.
  function formatDate(s) {
    if (!s) return "";
    var d = new Date(String(s).replace(" ", "T"));
    if (isNaN(d.getTime())) return String(s);
    return d.toLocaleDateString("es-MX", {
      day: "numeric", month: "long", year: "numeric",
      hour: "2-digit", minute: "2-digit",
    });
  }

  function showStatus(msg) {
    var box = document.getElementById("status-box");
    var receipt = document.getElementById("receipt");
    if (receipt) receipt.classList.add("hidden");
    if (box) {
      box.textContent = msg;
      box.classList.remove("hidden");
    }
  }

  function renderBusinessInfo(settings) {
    var el = document.getElementById("business-info");
    if (!el) return;
    var lines = [];
    if (settings.contacto_direccion) lines.push(esc(settings.contacto_direccion));
    if (settings.contacto_telefono) lines.push("Tel: " + esc(settings.contacto_telefono));
    if (settings.contacto_email) lines.push(esc(settings.contacto_email));
    el.innerHTML = lines.map(function (l) { return "<div>" + l + "</div>"; }).join("");
  }

  function renderOrder(o) {
    document.getElementById("order-title").textContent = "Pedido #" + o.id;
    document.getElementById("order-meta").innerHTML =
      esc(formatDate(o.created_at)) + " &middot; " + esc(ESTADO_LABELS[o.estado] || o.estado);

    var clienteLines = [];
    clienteLines.push("<div class=\"font-semibold\">" + esc(o.nombre_cliente) + "</div>");
    if (o.telefono) clienteLines.push("<div>" + esc(o.telefono) + "</div>");
    if (o.ciudad) clienteLines.push("<div>" + esc(o.ciudad) + "</div>");
    if (o.direccion_envio) clienteLines.push("<div>" + esc(o.direccion_envio) + "</div>");
    document.getElementById("cliente-info").innerHTML = clienteLines.join("");

    var tbody = document.getElementById("items-tbody");
    tbody.innerHTML = (o.items || []).map(function (i) {
      var nombre = esc(i.nombre) + (i.sabor ? " (" + esc(i.sabor) + ")" : "");
      return '<tr class="border-b border-slate-100">' +
        '<td class="py-2">' + nombre + '</td>' +
        '<td class="py-2 text-center">' + Number(i.cantidad) + '</td>' +
        '<td class="py-2 text-right">' + money(i.precio_unitario) + '</td>' +
        '<td class="py-2 text-right">' + money(i.subtotal) + '</td>' +
      '</tr>';
    }).join("");

    document.getElementById("order-total").textContent = money(o.total);

    document.getElementById("status-box").classList.add("hidden");
    document.getElementById("receipt").classList.remove("hidden");
  }

  document.addEventListener("DOMContentLoaded", function () {
    var printBtn = document.getElementById("print-btn");
    if (printBtn) printBtn.addEventListener("click", function () { window.print(); });

    var id = parseInt(new URLSearchParams(location.search).get("id"), 10);
    if (!id || id <= 0) {
      showStatus("Falta el id del pedido.");
      return;
    }

    DSAdminApi.apiFetch("../api/admin/orders/get.php?id=" + id)
      .then(function (order) {
        DSAdminApi.apiFetch("../api/admin/settings/get.php")
          .catch(function () { return {}; })
          .then(function (settings) {
            renderBusinessInfo(settings || {});
            renderOrder(order);
          });
      })
      .catch(function (err) {
        showStatus(err.message === "Pedido no encontrado" ? "Pedido no encontrado." : "Error: " + err.message);
      });
  });
})();
