/* Admin auth — login y guard de sesión para las páginas del panel. */
(function () {
  "use strict";

  var IS_LOGIN_PAGE = window.location.pathname.indexOf("login.html") !== -1;

  function init() {
    DSAdminApi.apiFetch("../api/admin/auth/me.php")
      .then(function (data) {
        DSAdminApi.setCsrfToken(data.csrf_token);

        if (data.admin) {
          // Sesión activa
          if (IS_LOGIN_PAGE) {
            window.location.replace("index.html");
            return;
          }
          // 2FA obligatorio: si no lo ha activado, forzar el enrolamiento antes de operar.
          var IS_SECURITY_PAGE = window.location.pathname.indexOf("seguridad.html") !== -1;
          if (data.needs_2fa && !IS_SECURITY_PAGE) {
            window.location.replace("seguridad.html?enrolar=1");
            return;
          }
          var nameEl = document.getElementById("admin-name");
          if (nameEl) nameEl.textContent = data.admin.nombre;
        } else {
          // Sin sesión
          if (!IS_LOGIN_PAGE) {
            window.location.replace("login.html");
          } else {
            setupLoginForm(data.csrf_token);
          }
        }
      })
      .catch(function () {
        if (!IS_LOGIN_PAGE) window.location.replace("login.html");
      });
  }

  function setupLoginForm(csrf) {
    var form = document.getElementById("login-form");
    var errEl = document.getElementById("login-error");
    if (!form) return;
    var totpField = document.getElementById("totp-field");
    var totpInput = document.getElementById("totp");

    function showError(msg) {
      errEl.textContent = msg;
      errEl.classList.remove("hidden");
    }

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      errEl.textContent = "";
      errEl.classList.add("hidden");

      var email = form.querySelector('[name="email"]').value.trim();
      var pass  = form.querySelector('[name="password"]').value;
      var totp  = totpInput ? totpInput.value.trim() : "";

      var body = { email: email, password: pass, csrf_token: csrf };
      if (totp) body.totp = totp;

      DSAdminApi.apiFetch("../api/admin/auth/login.php", {
        method: "POST",
        body: body,
      })
        .then(function (data) {
          // Contraseña correcta pero falta el segundo factor: mostrar el campo de código.
          if (data && data.twofa_required) {
            if (totpField) totpField.classList.remove("hidden");
            if (totpInput) totpInput.focus();
            showError("Ingresa el código de tu app autenticadora.");
            return;
          }
          window.location.replace("index.html");
        })
        .catch(function (err) {
          // Si ya estábamos en el paso 2FA, mantener el campo visible.
          if (totpField && !totpField.classList.contains("hidden") && totpInput) totpInput.focus();
          showError(err.message || "Credenciales incorrectas");
        });
    });
  }

  function setupLogout() {
    var btn = document.getElementById("logout-btn");
    if (!btn) return;
    btn.addEventListener("click", function () {
      DSAdminApi.apiFetch("../api/admin/auth/logout.php", {
        method: "POST",
        body: {},
      })
        .then(function () { window.location.replace("login.html"); })
        .catch(function () { window.location.replace("login.html"); });
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    init();
    setupLogout();
  });
})();
