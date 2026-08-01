/* DS Favoritos — botón de corazón en tarjetas/ficha de producto + estado por usuario.
   Requiere sesión iniciada; un invitado que pulse el corazón es enviado a login.
   Depende de config.js (DS_API_URL) y api-client.js (DSApi) cargados antes. */
(function (global) {
  function apiUrl(p) { return global.DS_API_URL ? global.DS_API_URL(p) : p; }

  // Estado compartido: sesión (csrf + logueado) y el conjunto de ids favoritos.
  var favIds = {};      // { productId: true }
  var loggedIn = false;
  var sessionPromise = null;

  // Obtiene el token CSRF y si hay sesión (una sola vez). Mismo patrón que cart.js.
  function ensureSession() {
    if (!sessionPromise) {
      sessionPromise = fetch(apiUrl("api/auth/me.php"), { credentials: "include" })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          var d = (j && j.ok && j.data) ? j.data : {};
          if (d.csrf_token && global.DSApi) global.DSApi.setCsrfToken(d.csrf_token);
          loggedIn = !!d.user;
          return loggedIn;
        })
        .catch(function () { sessionPromise = null; return false; });
    }
    return sessionPromise;
  }

  // Carga los ids favoritos del usuario y repinta los corazones visibles.
  function refresh() {
    return ensureSession().then(function (logged) {
      if (!logged) { favIds = {}; decorate(document); return favIds; }
      return fetch(apiUrl("api/favorites/ids.php"), { credentials: "include" })
        .then(function (r) { return r.ok ? r.json() : { ok: false }; })
        .then(function (j) {
          favIds = {};
          if (j && j.ok && Array.isArray(j.data)) {
            j.data.forEach(function (id) { favIds[String(id)] = true; });
          }
          decorate(document);
          return favIds;
        })
        .catch(function () { return favIds; });
    });
  }

  // Aplica el estado (relleno del icono) a los corazones dentro de root.
  function decorate(root) {
    (root || document).querySelectorAll(".favorite-btn[data-product-id]").forEach(function (btn) {
      paintButton(btn, !!favIds[String(btn.getAttribute("data-product-id"))]);
    });
  }

  function paintButton(btn, isFav) {
    btn.classList.toggle("is-fav", isFav);
    btn.setAttribute("aria-pressed", isFav ? "true" : "false");
    btn.setAttribute("aria-label", isFav ? "Quitar de favoritos" : "Agregar a favoritos");
    var icon = btn.querySelector(".material-symbols-outlined");
    if (icon) {
      icon.style.fontVariationSettings = isFav ? "'FILL' 1" : "'FILL' 0";
      icon.style.color = isFav ? "#E11D48" : "";
    }
  }

  // Click en un corazón: exige sesión; si la hay, alterna en el servidor.
  document.addEventListener("click", function (e) {
    var btn = e.target.closest ? e.target.closest(".favorite-btn") : null;
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation(); // no navegar al abrir la ficha si el corazón va sobre un <a>
    var productId = btn.getAttribute("data-product-id");
    if (!productId) return;

    ensureSession().then(function (logged) {
      if (!logged) { global.location.href = "login.html"; return; }
      btn.disabled = true;
      global.DSApi.apiFetch("api/favorites/toggle.php", {
        method: "POST",
        body: { producto_id: productId },
      })
        .then(function (data) {
          var fav = !!(data && data.favorito);
          favIds[String(productId)] = fav;
          if (!fav) delete favIds[String(productId)];
          // Repinta todos los corazones de ese producto (grid + ficha).
          document.querySelectorAll('.favorite-btn[data-product-id="' + productId + '"]').forEach(function (b) {
            paintButton(b, fav);
          });
          // Si estamos en el panel de favoritos de la cuenta, avisar para refrescar.
          if (typeof global.DSAccountFavoritesReload === "function") global.DSAccountFavoritesReload();
        })
        .catch(function () {})
        .then(function () { btn.disabled = false; });
    });
  });

  document.addEventListener("DOMContentLoaded", function () { refresh(); });

  global.DSFavorites = { refresh: refresh, decorate: decorate };
})(window);
