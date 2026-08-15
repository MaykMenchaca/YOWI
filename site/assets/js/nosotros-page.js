/* Página pública "Nosotros" — el contenido editable (misión, contacto, redes...) lo
   rellena assets/js/settings-inject.js de forma genérica en toda la tienda. Este
   archivo ya solo se encarga de lo que es específico de esta página: el formulario
   de contacto, que arma un mensaje y abre WhatsApp con él. */
(function (global) {
  document.addEventListener("DOMContentLoaded", function () {
    var form = document.querySelector("#contacto form");
    if (!form) return;
    var btn = form.querySelector("button");
    if (!btn) return;
    btn.addEventListener("click", function () {
      var nombre  = (document.getElementById("nombre")  || {}).value || "";
      var email   = (document.getElementById("email")   || {}).value || "";
      var selEl   = document.getElementById("asunto");
      var asunto  = selEl ? selEl.options[selEl.selectedIndex].text : "";
      var mensaje = (document.getElementById("mensaje") || {}).value || "";
      if (!mensaje.trim()) { alert("Escribe un mensaje antes de enviar."); return; }
      var texto = "Hola DS, soy " + nombre + (email ? " (" + email + ")" : "") +
        ".\nAsunto: " + asunto + "\n" + mensaje;
      var numero = global.DSWaNumber ? global.DSWaNumber() : "5218331645172";
      window.open("https://wa.me/" + numero + "?text=" + encodeURIComponent(texto), "_blank");
    });
  });
})(window);
