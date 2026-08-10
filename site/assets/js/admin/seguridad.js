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

  function showRecoveryCodes(codes) {
    var box = document.getElementById("recovery-box");
    var list = document.getElementById("recovery-list");
    if (!box || !list || !codes || !codes.length) return;
    list.innerHTML = codes.map(function (c) {
      return '<li class="bg-slate-900 border border-slate-700 px-3 py-1.5 tracking-widest select-all">' +
        String(c).replace(/[&<>"']/g, "") + "</li>";
    }).join("");
    box.classList.remove("hidden");
    var copyBtn = document.getElementById("btn-copy-codes");
    if (copyBtn) {
      copyBtn.onclick = function () {
        var text = codes.join("\n");
        if (navigator.clipboard) navigator.clipboard.writeText(text);
        copyBtn.textContent = "Copiado ✓";
        setTimeout(function () { copyBtn.textContent = "Copiar todos"; }, 1500);
      };
    }
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
    // Si llegó forzado por el 2FA obligatorio, mostrar aviso.
    if (new URLSearchParams(window.location.search).get("enrolar") === "1") {
      alertMsg("Por seguridad, debes activar la verificación en dos pasos (2FA) antes de usar el panel.", false);
    }
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
        var password = document.getElementById("activate-password").value || "";
        if (!password) { alertMsg("Confirma tu contraseña para activar el 2FA.", false); return; }
        DSAdminApi.apiFetch("../api/admin/auth/2fa-activate.php", { method: "POST", body: { code: code, password: password } })
          .then(function (d) {
            document.getElementById("activate-password").value = "";
            alertMsg("2FA activado. Guarda tus códigos de recuperación abajo.", true);
            render(true);
            showRecoveryCodes(d && d.recovery_codes);
          })
          .catch(function (err) { alertMsg(err.message, false); });
      });
    }

    var btnRegen = document.getElementById("btn-regen");
    if (btnRegen) {
      btnRegen.addEventListener("click", function () {
        var code = (document.getElementById("regen-code").value || "").trim();
        DSAdminApi.apiFetch("../api/admin/auth/2fa-recovery.php", { method: "POST", body: { code: code } })
          .then(function (d) {
            alertMsg("Nuevos códigos generados. Los anteriores ya no sirven.", true);
            showRecoveryCodes(d && d.recovery_codes);
          })
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

    var changePwForm = document.getElementById("change-pw-form");
    if (changePwForm) {
      changePwForm.addEventListener("submit", function (e) {
        e.preventDefault();
        var actual = document.getElementById("pw-actual").value;
        var nueva  = document.getElementById("pw-nueva").value;
        var btn = document.getElementById("btn-change-pw");
        btn.disabled = true;
        DSAdminApi.apiFetch("../api/admin/auth/change-password.php", {
          method: "POST",
          body: { password_actual: actual, password_nueva: nueva },
        })
          .then(function () {
            changePwForm.reset();
            alertMsg("Contraseña cambiada.", true);
          })
          .catch(function (err) { alertMsg(err.message, false); })
          .then(function () { btn.disabled = false; });
      });
    }
  });
})();
