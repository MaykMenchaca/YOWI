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
          throw new Error((json && json.error) || "Error del servidor");
        }
        return json.data;
      });
    });
  }

  global.DSAdminApi = { apiFetch: apiFetch, setCsrfToken: setCsrfToken };
})(window);
