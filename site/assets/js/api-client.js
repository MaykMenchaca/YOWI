/* DS API Client — fetch wrapper compartido para todos los endpoints en site/api/.
   Maneja CSRF, credenciales de sesión y normalización de errores. */
(function (global) {
  var csrfToken = null;

  function setCsrfToken(token) {
    csrfToken = token || null;
  }

  // Pide un token CSRF fresco a me.php. Se usa cuando un POST falla por sesión/token
  // caducado a media navegación (idle timeout, login/logout en otra pestaña) — sin esto
  // el usuario solo veía "Token de seguridad inválido" y tenía que recargar a mano.
  function refreshCsrfToken() {
    var url = global.DS_API_URL ? global.DS_API_URL("api/auth/me.php") : "api/auth/me.php";
    return fetch(url, { credentials: "include" })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (j && j.ok && j.data && j.data.csrf_token) setCsrfToken(j.data.csrf_token);
      })
      .catch(function () { /* si esto falla, el reintento de abajo fallará también y se propaga el error real */ });
  }

  function apiFetch(path, options, _isRetry) {
    options = options || {};
    var headers = Object.assign({ "Content-Type": "application/json" }, options.headers || {});
    var body = options.body;

    if (body && typeof body === "object") {
      body = Object.assign({}, body);
      if (csrfToken && !("csrf_token" in body)) {
        body.csrf_token = csrfToken;
      }
      body = JSON.stringify(body);
    }

    var url = global.DS_API_URL ? global.DS_API_URL(path) : path;

    return fetch(url, {
      method: options.method || "GET",
      headers: headers,
      // "include" funciona igual en same-origin y es necesario cross-site
      // (frontend en Vercel + backend en Hostinger) para enviar la cookie de sesión.
      credentials: "include",
      body: body,
    })
      .then(function (res) {
        return res.text().then(function (text) {
          var json;
          try { json = JSON.parse(text); }
          catch (e) {
            var errParse = new Error("Error del servidor (" + res.status + ")");
            errParse.status = res.status;
            throw errParse;
          }
          if (!res.ok || json.ok === false) {
            var msg = (json && json.error) || "Error del servidor";
            // Token CSRF caducado (no la contraseña ni el CSRF que se acaba de mandar mal
            // a propósito): reintentar UNA sola vez con un token fresco antes de rendirse,
            // en vez de dejar al usuario con un mensaje críptico a media compra.
            if (!_isRetry && res.status === 403 && msg === "Token de seguridad inválido") {
              return refreshCsrfToken().then(function () {
                return apiFetch(path, options, true);
              });
            }
            var err = new Error(msg);
            err.status = res.status;
            err.data = json && json.data; // ej. { ajustes: [...] } en orders/create.php
            throw err;
          }
          return json.data;
        });
      });
  }

  global.DSApi = { apiFetch: apiFetch, setCsrfToken: setCsrfToken };
})(window);
