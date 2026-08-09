/* Página pública "Nosotros" — rellena el contenido editable desde api/settings/get.php
   y hace que el formulario de contacto abra WhatsApp con el mensaje armado. */
(function (global) {
  function apiUrl(p) { return global.DS_API_URL ? global.DS_API_URL(p) : p; }

  var esc = window.DSSec.esc; // definición única en security-utils.js

  var waNumber = "5218344241599"; // fallback

  document.addEventListener("DOMContentLoaded", function () {
    fetch(apiUrl("api/settings/get.php"), { credentials: "include" })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        var d = (j && j.ok && j.data) ? j.data : {};
        Object.keys(d).forEach(function (k) {
          document.querySelectorAll('[data-set="' + k + '"]').forEach(function (el) {
            // Respeta saltos de línea (dirección/horario) como <br>.
            el.innerHTML = esc(d[k]).replace(/\n/g, "<br>");
          });
        });
        if (d.contacto_whatsapp) {
          var num = String(d.contacto_whatsapp).replace(/\D+/g, "");
          if (num) waNumber = num;
        }
      })
      .catch(function () {});

    // Formulario de contacto -> abre WhatsApp con el mensaje.
    var form = document.querySelector("#contacto form");
    if (form) {
      var btn = form.querySelector("button");
      if (btn) {
        btn.addEventListener("click", function () {
          var nombre  = (document.getElementById("nombre")  || {}).value || "";
          var email   = (document.getElementById("email")   || {}).value || "";
          var selEl   = document.getElementById("asunto");
          var asunto  = selEl ? selEl.options[selEl.selectedIndex].text : "";
          var mensaje = (document.getElementById("mensaje") || {}).value || "";
          if (!mensaje.trim()) { alert("Escribe un mensaje antes de enviar."); return; }
          var texto = "Hola DS, soy " + nombre + (email ? " (" + email + ")" : "") +
            ".\nAsunto: " + asunto + "\n" + mensaje;
          window.open("https://wa.me/" + waNumber + "?text=" + encodeURIComponent(texto), "_blank");
        });
      }
    }
  });
})(window);
