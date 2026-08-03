/* Admin API Client — idéntico al público pero apunta a api/admin/ y usa admin_csrf. */
(function (global) {
  var csrfToken = null;

  function setCsrfToken(token) {
    csrfToken = token || null;
  }

  // path relativo a la raíz del sitio: ej. "api/admin/products/list.php"
  function apiFetch(path, options) {
    options = options || {};
    var isFormData = options.body instanceof FormData;
    var headers = isFormData ? {} : Object.assign({ "Content-Type": "application/json" }, options.headers || {});
    var body = options.body;

    if (!isFormData && body && typeof body === "object") {
      body = Object.assign({}, body);
      if (csrfToken && !("csrf_token" in body)) {
        body.csrf_token = csrfToken;
      }
      body = JSON.stringify(body);
    } else if (isFormData && csrfToken && !body.has("csrf_token")) {
      body.append("csrf_token", csrfToken);
    }

    return fetch(path, {
      method: options.method || "GET",
      headers: headers,
      credentials: "same-origin",
      body: body,
    }).then(function (res) {
      return res.text().then(function (text) {
        var json;
        try { json = JSON.parse(text); }
        catch (e) { throw new Error("Error del servidor (" + res.status + ")"); }
        if (!res.ok || json.ok === false) {
          var mensaje = (json && json.error) || "Error del servidor";
          // El backend exige 2FA activo para cualquier botón que escriba (crear, editar,
          // borrar, importar...). Antes cada página mostraba ese 403 como un error suelto;
          // ahora se lleva directo a la pantalla donde ya se puede activar el 2FA.
          if (res.status === 403 && /activar el 2FA/i.test(mensaje) && !/\/seguridad\.html/.test(location.pathname)) {
            location.href = "seguridad.html?enrolar=1";
            return new Promise(function () {}); // corta la cadena mientras navega
          }
          throw new Error(mensaje);
        }
        return json.data;
      });
    });
  }

  global.DSAdminApi = { apiFetch: apiFetch, setCsrfToken: setCsrfToken };
})(window);
