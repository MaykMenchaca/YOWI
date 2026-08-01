/* Admin — gestión de 2FA (TOTP). Depende de api-client.js y auth.js. */
(function () {
  "use strict";

  var badge = document.getElementById("twofa-badge");
  var boxDisabled = document.getElementById("twofa-disabled");
  var boxEnabled = document.getElementById("twofa-enabled");
  var alertBox = document.getElementById("alert-banner");

  function alertMsg(msg, ok) {
    if (!alertBox) return;
    alertBox.textContent = msg;
    alertBox.className = "mb-4 px-4 py-2.5 text-sm border " +
      (ok ? "border-lime text-lime bg-lime/10" : "border-red-500 text-red-400 bg-red-500/10");
    alertBox.classList.remove("hidden");
  }

  function setBadge(enabled) {
    if (!badge) return;
    badge.textContent = enabled ? "Activado" : "Desactivado";
    badge.className = "shrink-0 text-[11px] font-bold uppercase tracking-widest px-3 py-1 rounded " +
      (enabled ? "bg-lime text-ink" : "bg-slate-700 text-slate-300");
  }

  function render(enabled) {
    setBadge(enabled);
    boxDisabled.classList.toggle("hidden", enabled);
    boxEnabled.classList.toggle("hidden", !enabled);
  }

  function loadStatus() {
    DSAdminApi.apiFetch("../api/admin/auth/2fa-status.php")
      .then(function (d) { render(!!d.enabled); })
      .catch(function () { alertMsg("No se pudo cargar el estado de 2FA.", false); });
  }

  // Asegura que el token CSRF esté cargado antes de las acciones POST.
  function ensureCsrf() {
    return DSAdminApi.apiFetch("../api/admin/auth/me.php")
      .then(function (d) { DSAdminApi.setCsrfToken(d.csrf_token); return d; });
  }

  document.addEventListener("DOMContentLoaded", function () {
    if (!badge) return; // no estamos en la página de seguridad
    ensureCsrf().then(loadStatus).catch(loadStatus);

    var btnSetup = document.getElementById("btn-setup");
    var setupBox = document.getElementById("setup-box");
    var secretCode = document.getElementById("secret-code");
    var otpauthLink = document.getElementById("otpauth-link");

    if (btnSetup) {
      btnSetup.addEventListener("click", function () {
        btnSetup.disabled = true;
        DSAdminApi.apiFetch("../api/admin/auth/2fa-setup.php", { method: "POST", body: {} })
          .then(function (d) {
            secretCode.textContent = d.secret;
            otpauthLink.textContent = d.otpauth_uri;
            otpauthLink.setAttribute("href", d.otpauth_uri);
            setupBox.classList.remove("hidden");
          })
          .catch(function (err) { alertMsg(err.message, false); })
          .then(function () { btnSetup.disabled = false; });
      });
    }

    var btnActivate = document.getElementById("btn-activate");
    if (btnActivate) {
      btnActivate.addEventListener("click", function () {
        var code = (document.getElementById("activate-code").value || "").trim();
        DSAdminApi.apiFetch("../api/admin/auth/2fa-activate.php", { method: "POST", body: { code: code } })
          .then(function () { alertMsg("2FA activado correctamente. Se te pedirá el código en tu próximo inicio de sesión.", true); render(true); })
          .catch(function (err) { alertMsg(err.message, false); });
      });
    }

    var btnDisable = document.getElementById("btn-disable");
    if (btnDisable) {
      btnDisable.addEventListener("click", function () {
        var code = (document.getElementById("disable-code").value || "").trim();
        DSAdminApi.apiFetch("../api/admin/auth/2fa-disable.php", { method: "POST", body: { code: code } })
          .then(function () { alertMsg("2FA desactivado.", true); render(false); })
          .catch(function (err) { alertMsg(err.message, false); });
      });
    }
  });
})();
