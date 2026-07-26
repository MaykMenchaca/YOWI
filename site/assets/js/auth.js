/* DS Auth — conecta login.html, registro.html y cuenta.html a site/api/auth/*.
   Requiere DSApi (api-client.js) cargado antes. */
(function (global) {
  function showFormError(form, message) {
    var box = form.querySelector("[data-form-error]");
    if (!box) {
      box = document.createElement("p");
      box.setAttribute("data-form-error", "");
      box.className = "text-error text-sm mt-2";
      form.appendChild(box);
    }
    box.textContent = message;
  }

  function setBusy(form, busy) {
    var btn = form.querySelector('[type="submit"]');
    if (btn) btn.disabled = busy;
  }

  // El backend exige token CSRF en cada POST. Hay que obtenerlo (GET me.php)
  // ANTES de enviar login/registro; si no, el servidor responde 403 "Token de
  // seguridad inválido". Mismo patrón que usa cart.js para crear el pedido.
  var csrfPromise = null;
  function ensureCsrf() {
    if (!csrfPromise) {
      var url = global.DS_API_URL ? global.DS_API_URL("api/auth/me.php") : "api/auth/me.php";
      csrfPromise = fetch(url, { credentials: "include" })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (j && j.ok && j.data && j.data.csrf_token) {
            global.DSApi.setCsrfToken(j.data.csrf_token);
            return true;
          }
          throw new Error("No se pudo iniciar la sesión de seguridad");
        })
        .catch(function (e) { csrfPromise = null; throw e; });
    }
    return csrfPromise;
  }

  function bindLoginForm() {
    var form = document.getElementById("login-form");
    if (!form) return;
    ensureCsrf().catch(function () {}); // precargar el token al abrir la página
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var email = form.querySelector('[name="email"]').value;
      var password = form.querySelector('[name="password"]').value;
      setBusy(form, true);
      ensureCsrf()
        .then(function () {
          return global.DSApi.apiFetch("api/auth/login.php", { method: "POST", body: { email: email, password: password } });
        })
        .then(function () { window.location.href = "cuenta.html"; })
        .catch(function (err) { showFormError(form, err.message); setBusy(form, false); });
    });
  }

  function bindRegistroForm() {
    var form = document.getElementById("registro-form");
    if (!form) return;
    ensureCsrf().catch(function () {}); // precargar el token al abrir la página
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var password = form.querySelector('[name="password"]').value;
      var confirmPass = form.querySelector('[name="confirm_password"]').value;
      var termsEl = form.querySelector('[name="terms"]');

      // Validaciones en el navegador (el backend igual las revalida).
      if (password.length < 8) { showFormError(form, "La contraseña debe tener al menos 8 caracteres"); return; }
      if (password !== confirmPass) { showFormError(form, "Las contraseñas no coinciden"); return; }
      if (termsEl && !termsEl.checked) { showFormError(form, "Debes aceptar los términos y condiciones"); return; }

      var body = {
        nombre: form.querySelector('[name="nombre"]').value,
        email: form.querySelector('[name="email"]').value,
        telefono: (form.querySelector('[name="telefono"]') || {}).value || "",
        password: password,
        confirm_password: confirmPass,
      };
      setBusy(form, true);
      ensureCsrf()
        .then(function () {
          return global.DSApi.apiFetch("api/auth/register.php", { method: "POST", body: body });
        })
        .then(function () { window.location.href = "cuenta.html"; })
        .catch(function (err) { showFormError(form, err.message); setBusy(form, false); });
    });
  }

  function bindCuentaPage() {
    var profileBox = document.querySelector("[data-user-profile]");
    var ordersBox = document.getElementById("orders-list");
    var logoutBtn = document.querySelector("[data-logout-btn]");
    if (!profileBox && !ordersBox && !logoutBtn) return;

    global.DSApi.apiFetch("api/auth/me.php")
      .then(function (data) {
        global.DSApi.setCsrfToken(data.csrf_token);
        if (!data.user) {
          window.location.href = "login.html";
          return;
        }
        if (profileBox) {
          profileBox.querySelectorAll("[data-field]").forEach(function (el) {
            var field = el.getAttribute("data-field");
            if (data.user[field] !== undefined) el.textContent = data.user[field] || "";
          });
        }
        if (ordersBox) {
          global.DSApi.apiFetch("api/orders/list.php").then(function (orders) {
            renderOrders(orders, ordersBox);
          }).catch(function () {
            ordersBox.innerHTML = '<p class="text-on-surface-variant p-4">No se pudieron cargar tus pedidos. Recarga la página para reintentar.</p>';
          });
        }
      })
      .catch(function () { window.location.href = "login.html"; });

    if (logoutBtn) {
      logoutBtn.addEventListener("click", function (e) {
        e.preventDefault();
        global.DSApi.apiFetch("api/auth/logout.php", { method: "POST" }).then(function () {
          window.location.href = "login.html";
        });
      });
    }
  }

  function money(n) {
    return "$" + Number(n).toLocaleString("es-MX", { minimumFractionDigits: 2 }) + " MXN";
  }

  function esc(s) {
    return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }

  function renderOrders(orders, container) {
    if (!orders.length) {
      container.innerHTML = '<tr><td colspan="5" class="py-6 px-4 text-center text-on-surface-variant">Aún no tienes pedidos.</td></tr>';
      return;
    }
    container.innerHTML = orders.map(function (order) {
      return (
        '<tr class="hover:bg-surface-container-low transition-colors">' +
          '<td class="py-4 px-4 font-medium">#DS-' + esc(order.id) + '</td>' +
          '<td class="py-4 px-4 text-on-surface-variant">' + esc(order.created_at) + '</td>' +
          '<td class="py-4 px-4 font-price-display text-[16px]">' + money(order.total) + '</td>' +
          '<td class="py-4 px-4">' +
            '<span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-secondary-fixed/50 text-on-secondary-fixed-variant">' + esc(order.estado) + '</span>' +
          '</td>' +
          '<td class="py-4 px-4 text-right text-on-surface-variant">' + order.items.length + ' producto(s)</td>' +
        '</tr>'
      );
    }).join("");
  }

  document.addEventListener("DOMContentLoaded", function () {
    bindLoginForm();
    bindRegistroForm();
    bindCuentaPage();
  });
})(window);
