/* DS Config — punto único para la URL del backend.
 *
 * Debe cargarse ANTES que api-client.js, catalog-engine.js y cart.js.
 *
 * API_BASE:
 *   ""  → mismo origen (todo servido desde Hostinger). Es el default y no
 *         requiere CORS ni cambios de cookies.
 *   "https://tienda.tudominio.com" → modo "frontend en Vercel + API aparte":
 *         el frontend (Vercel) llama al backend PHP alojado en Hostinger.
 *         Requiere CORS habilitado en site/api/.htaccess y, para las rutas con
 *         sesión (login/cuenta/admin/registro de pedido), cookies cross-site.
 */
(function (global) {
  global.DS_CONFIG = global.DS_CONFIG || {
    API_BASE: "",
  };

  // Une API_BASE con una ruta relativa del API. Con API_BASE="" devuelve la
  // ruta tal cual (comportamiento same-origin de siempre).
  global.DS_API_URL = function (path) {
    var base = String((global.DS_CONFIG && global.DS_CONFIG.API_BASE) || "").replace(/\/+$/, "");
    var p = String(path || "").replace(/^\/+/, "");
    return base ? base + "/" + p : p;
  };
})(window);
