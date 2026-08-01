/* Mostrar/ocultar contraseña en login (sin handler inline, compatible con CSP estricta). */
(function () {
  document.addEventListener("DOMContentLoaded", function () {
    var btn = document.getElementById("toggle-password-btn");
    var input = document.getElementById("password");
    var icon = document.getElementById("toggleIcon");
    if (!btn || !input) return;
    btn.addEventListener("click", function () {
      var show = input.type === "password";
      input.type = show ? "text" : "password";
      if (icon) icon.textContent = show ? "visibility_off" : "visibility";
    });
  });
})();
