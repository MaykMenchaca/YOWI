/* DS API Client — fetch wrapper compartido para todos los endpoints en site/api/.
   Maneja CSRF, credenciales de sesión y normalización de errores. */
(function (global) {
  var csrfToken = null;

  function setCsrfToken(token) {
    csrfToken = token || null;
  }

  function apiFetch(path, options) {
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

    return fetch(path, {
      method: options.method || "GET",
      headers: headers,
      credentials: "same-origin",
      body: body,
    })
      .then(function (res) {
        return res.text().then(function (text) {
          var json;
          try { json = JSON.parse(text); }
          catch (e) { throw new Error("Error del servidor (" + res.status + ")"); }
          if (!res.ok || json.ok === false) {
            throw new Error((json && json.error) || "Error del servidor");
          }
          return json.data;
        });
      });
  }

  global.DSApi = { apiFetch: apiFetch, setCsrfToken: setCsrfToken };
})(window);
