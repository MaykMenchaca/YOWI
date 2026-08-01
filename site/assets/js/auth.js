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

    var currentUser = null;

    function fillProfile(user) {
      if (!profileBox) return;
      profileBox.querySelectorAll("[data-field]").forEach(function (el) {
        var field = el.getAttribute("data-field");
        if (user[field] !== undefined) el.textContent = user[field] || "";
      });
    }

    global.DSApi.apiFetch("api/auth/me.php")
      .then(function (data) {
        global.DSApi.setCsrfToken(data.csrf_token);
        if (!data.user) { window.location.href = "login.html"; return; }
        currentUser = data.user;
        fillProfile(currentUser);
        if (ordersBox) {
          global.DSApi.apiFetch("api/orders/list.php").then(function (orders) {
            renderOrders(orders, ordersBox);
          }).catch(function () {
            ordersBox.innerHTML = '<tr><td colspan="5" class="py-6 px-4 text-center text-on-surface-variant">No se pudieron cargar tus pedidos.</td></tr>';
          });
        }
        loadFavorites();
      })
      .catch(function () { window.location.href = "login.html"; });

    // ── Favoritos ──
    var favBox = document.getElementById("favorites-list");
    function loadFavorites() {
      if (!favBox) return;
      global.DSApi.apiFetch("api/favorites/list.php")
        .then(function (list) { renderFavorites(list, favBox); })
        .catch(function () {
          favBox.innerHTML = '<p class="col-span-full text-center text-error py-6">No se pudieron cargar tus favoritos.</p>';
        });
    }
    // favorites.js llama a esto tras alternar un corazón para refrescar el panel.
    global.DSAccountFavoritesReload = loadFavorites;

    if (favBox) {
      // Agregar al carrito desde una tarjeta de favorito (datos ya embebidos).
      favBox.addEventListener("click", function (e) {
        var addBtn = e.target.closest(".fav-add-btn");
        if (!addBtn || !global.DSCart) return;
        global.DSCart.addItem({
          id: addBtn.getAttribute("data-id"),
          nombre: addBtn.getAttribute("data-nombre"),
          precio: Number(addBtn.getAttribute("data-precio")) || 0,
          imagen: addBtn.getAttribute("data-imagen"),
        }, 1);
        addBtn.textContent = "Agregado ✓";
        setTimeout(function () { addBtn.textContent = "Agregar al carrito"; }, 1500);
      });
    }

    if (logoutBtn) {
      logoutBtn.addEventListener("click", function (e) {
        e.preventDefault();
        global.DSApi.apiFetch("api/auth/logout.php", { method: "POST" }).then(function () {
          window.location.href = "login.html";
        });
      });
    }

    // ── Pestañas: conmutar paneles ──
    var tabs = Array.prototype.slice.call(document.querySelectorAll(".account-tab"));
    var ACT = ["bg-surface-container", "text-brand", "font-medium", "ring-1", "ring-primary/20"];
    var INACT = ["text-gray-500", "hover:bg-surface", "hover:text-brand", "transition-colors"];
    function showPanel(name) {
      document.querySelectorAll("[data-panel]").forEach(function (p) {
        p.classList.toggle("hidden", p.getAttribute("data-panel") !== name);
      });
      tabs.forEach(function (t) {
        var on = t.getAttribute("data-tab") === name;
        ACT.forEach(function (c) { t.classList.toggle(c, on); });
        INACT.forEach(function (c) { t.classList.toggle(c, !on); });
      });
    }
    tabs.forEach(function (t) {
      t.addEventListener("click", function (e) { e.preventDefault(); showPanel(t.getAttribute("data-tab")); });
    });

    // ── Editar perfil ──
    var editBtn = document.getElementById("edit-profile-btn");
    var modal = document.getElementById("edit-modal");
    var editForm = document.getElementById("edit-form");
    if (editBtn && modal && editForm) {
      var errBox = editForm.querySelector("[data-edit-error]");
      editBtn.addEventListener("click", function () {
        editForm.querySelector('[name="nombre"]').value = (currentUser && currentUser.nombre) || "";
        editForm.querySelector('[name="telefono"]').value = (currentUser && currentUser.telefono) || "";
        if (errBox) errBox.classList.add("hidden");
        modal.classList.remove("hidden");
      });
      var cancel = document.getElementById("edit-cancel");
      if (cancel) cancel.addEventListener("click", function () { modal.classList.add("hidden"); });
      editForm.addEventListener("submit", function (e) {
        e.preventDefault();
        var nombre = editForm.querySelector('[name="nombre"]').value.trim();
        var telefono = editForm.querySelector('[name="telefono"]').value.trim();
        if (!nombre) { if (errBox) { errBox.textContent = "El nombre no puede estar vacío."; errBox.classList.remove("hidden"); } return; }
        global.DSApi.apiFetch("api/auth/update-profile.php", { method: "POST", body: { nombre: nombre, telefono: telefono } })
          .then(function (d) {
            if (currentUser) { currentUser.nombre = d.nombre; currentUser.telefono = d.telefono; }
            fillProfile(currentUser || d);
            modal.classList.add("hidden");
          })
          .catch(function (er) { if (errBox) { errBox.textContent = er.message; errBox.classList.remove("hidden"); } });
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

  function qtyLabel(p) {
    var c = String(p.cantidad == null ? "" : p.cantidad).trim();
    var u = String(p.unidad == null ? "" : p.unidad).trim();
    return u ? (c + " " + u).trim() : c;
  }

  function renderFavorites(list, container) {
    if (!list || !list.length) {
      container.innerHTML = '<div class="col-span-full text-center text-gray-500 py-10">' +
        '<span class="material-symbols-outlined text-5xl text-gray-300">favorite</span>' +
        '<p class="mt-3 font-bold text-ink">Aún no tienes favoritos</p>' +
        '<p class="text-sm mt-1">Marca el corazón en cualquier producto para guardarlo aquí. <a href="catalogo.html" class="text-brand underline">Ver catálogo</a></p></div>';
      return;
    }
    container.innerHTML = list.map(function (p) {
      var detailUrl = "producto.html?id=" + encodeURIComponent(p.id);
      return (
        '<div class="bg-white border border-gray-200 p-3 flex flex-col relative">' +
          '<button type="button" class="favorite-btn is-fav absolute top-2 right-2 flex items-center justify-center h-9 w-9 rounded-full bg-white/90 shadow hover:bg-white transition" data-product-id="' + esc(p.id) + '" aria-label="Quitar de favoritos" aria-pressed="true">' +
            '<span class="material-symbols-outlined" style="font-variation-settings:\'FILL\' 1;color:#E11D48;">favorite</span>' +
          '</button>' +
          '<a href="' + detailUrl + '" class="block h-28 bg-gray-100 mb-2 flex items-center justify-center overflow-hidden p-2">' +
            '<img class="max-h-full object-contain" loading="lazy" src="' + esc(p.imagen) + '" alt="' + esc(p.nombre) + '" data-fallback="assets/img/producto-placeholder.svg"/>' +
          '</a>' +
          '<div class="text-[10px] uppercase font-extrabold text-gray-500 tracking-wide">' + esc(p.marca) + '</div>' +
          '<h3 class="font-bold text-sm leading-tight mb-1"><a href="' + detailUrl + '" class="hover:text-brand">' + esc(p.nombre) + '</a></h3>' +
          '<div class="text-xs text-gray-400 mb-2">' + esc(qtyLabel(p)) + '</div>' +
          '<div class="text-brand font-extrabold text-base mt-auto mb-2">' + money(p.precio) + '</div>' +
          '<button type="button" class="fav-add-btn bg-lime text-ink font-extrabold uppercase tracking-wide px-3 py-2 min-h-[44px] w-full text-xs hover:opacity-90 transition-opacity" ' +
            'data-id="' + esc(p.id) + '" data-nombre="' + esc(p.nombre) + '" data-precio="' + esc(p.precio) + '" data-imagen="' + esc(p.imagen) + '">Agregar al carrito</button>' +
        '</div>'
      );
    }).join("");
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

  function showFormOk(form, message) {
    var box = form.querySelector("[data-form-ok]");
    if (box) { box.textContent = message; box.classList.remove("hidden"); }
  }

  function bindForgotForm() {
    var form = document.getElementById("forgot-form");
    if (!form) return;
    ensureCsrf().catch(function () {});
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var email = form.querySelector('[name="email"]').value;
      setBusy(form, true);
      ensureCsrf()
        .then(function () {
          return global.DSApi.apiFetch("api/auth/password-forgot.php", { method: "POST", body: { email: email } });
        })
        .then(function (d) {
          showFormOk(form, (d && d.message) || "Si el correo está registrado, te enviamos instrucciones.");
          form.querySelector('[type="submit"]').textContent = "Enviado";
        })
        .catch(function (err) { showFormError(form, err.message); setBusy(form, false); });
    });
  }

  function bindResetForm() {
    var form = document.getElementById("reset-form");
    if (!form) return;
    var token = new URLSearchParams(window.location.search).get("token") || "";
    ensureCsrf().catch(function () {});
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var password = form.querySelector('[name="password"]').value;
      var confirmPass = form.querySelector('[name="confirm_password"]').value;
      if (password.length < 8) { showFormError(form, "La contraseña debe tener al menos 8 caracteres"); return; }
      if (password !== confirmPass) { showFormError(form, "Las contraseñas no coinciden"); return; }
      setBusy(form, true);
      ensureCsrf()
        .then(function () {
          return global.DSApi.apiFetch("api/auth/password-reset.php", {
            method: "POST",
            body: { token: token, password: password, confirm_password: confirmPass },
          });
        })
        .then(function (d) {
          showFormOk(form, (d && d.message) || "Tu contraseña se actualizó.");
          setTimeout(function () { window.location.href = "login.html"; }, 1500);
        })
        .catch(function (err) { showFormError(form, err.message); setBusy(form, false); });
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    bindLoginForm();
    bindRegistroForm();
    bindCuentaPage();
    bindForgotForm();
    bindResetForm();
  });
})(window);
