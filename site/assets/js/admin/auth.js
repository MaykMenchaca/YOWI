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

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      errEl.textContent = "";

      var email = form.querySelector('[name="email"]').value.trim();
      var pass  = form.querySelector('[name="password"]').value;

      DSAdminApi.apiFetch("../api/admin/auth/login.php", {
        method: "POST",
        body: { email: email, password: pass, csrf_token: csrf },
      })
        .then(function () {
          window.location.replace("index.html");
        })
        .catch(function (err) {
          errEl.textContent = err.message || "Credenciales incorrectas";
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
