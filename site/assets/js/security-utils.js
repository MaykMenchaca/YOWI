// Utilidades de seguridad compartidas por TODO el frontend (storefront y admin).
// Antes esc()/escAttr()/safeHref() estaban copiadas en 13 archivos distintos —
// funcionalmente iguales hoy, pero 13 oportunidades de que una copia futura diverja
// y abra un hueco de XSS/URL insegura sin que nadie lo note en el resto. Cargar este
// archivo PRIMERO en cada página (antes que cualquier módulo que use estas funciones);
// cada módulo referencia window.DSSec en vez de definir su propia copia.
(function (global) {
  "use strict";

  function esc(s) {
    return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }

  // Quita caracteres de control (0x00-0x1F y 0x7F) sin depender de un rango \u en el
  // regex, para no arriesgar un mal escapado. Recorre carácter por carácter: el string
  // de una URL nunca es tan largo como para que esto importe en rendimiento.
  function stripControlChars(s) {
    var out = "";
    for (var i = 0; i < s.length; i++) {
      var code = s.charCodeAt(i);
      if (code > 0x1f && code !== 0x7f) out += s[i];
    }
    return out;
  }

  // Solo permite http/https o rutas relativas; bloquea javascript:, data:, vbscript:, etc.
  // Endurecido respecto a la versión que estaba duplicada en brands-page.js/hero-carousel.js:
  // esa solo recortaba espacios en los extremos antes de mirar el esquema, así que una
  // cadena como "java" + TAB + "script:alert(1)" (un carácter de control intercalado) no
  // calzaba con el regex del esquema y se devolvía TAL CUAL — los navegadores ignoran los
  // caracteres de control al interpretar un esquema, así que sí la ejecutarían. Aquí se
  // quitan esos caracteres ANTES de decidir. También se rechaza "//host" (protocolo-
  // relativo: hereda el esquema de la página actual, en la práctica equivale a aceptar
  // cualquier esquema). No es explotable hoy — la CSP script-src 'self' ya bloquea
  // javascript: — pero esa CSP depende al 100% de que el .htaccess se aplique; esto es
  // la segunda capa, independiente de esa dependencia.
  function safeHref(s) {
    var v = stripControlChars(String(s == null ? "" : s)).trim();
    if (!v) return "";
    if (v.slice(0, 2) === "//") return "";
    var m = /^([a-z][a-z0-9+.\-]*)\s*:/i.exec(v);
    if (m && m[1].toLowerCase() !== "http" && m[1].toLowerCase() !== "https") return "";
    return v;
  }

  global.DSSec = { esc: esc, escAttr: esc, safeHref: safeHref };
})(window);
