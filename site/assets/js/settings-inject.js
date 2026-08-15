/* DS Settings — capa única que inyecta el contenido editable del panel
   (api/settings/get.php) en TODO el storefront. Generaliza lo que antes hacía
   nosotros-page.js solo en su propia página, para que cambiar un dato desde
   el panel (teléfono, dirección, redes, WhatsApp...) se refleje en cada
   página sin tocar código.

   Convención de atributos (se leen una sola vez, cuando la petición resuelve):

     data-set="clave"                  Texto (escapado). "\n" -> <br>.
     data-set-href="clave"             El valor va al atributo href tal cual
                                        (tel:, mailto:, enlaces de redes, mapa).
     data-set-lista="clave"            Una línea del valor = un elemento de
                                        lista: el primer hijo del contenedor
                                        se usa como plantilla y se clona por
                                        cada línea no vacía.
     data-wa-link="mensaje opcional"   Arma https://wa.me/<numero>?text=<msj>
                                        con el número vigente de WhatsApp.
     data-set-ocultar-si-vacio="clave" Oculta el elemento si la clave viene
                                        vacía (ej. un ícono de red social que
                                        el dueño no ha configurado).

   Expone:
     window.DSSettings      objeto {clave: valor} en cuanto la petición resuelve
                             (queda {} si falla — nunca datos inventados).
     window.DSSettingsReady promesa que resuelve con ese mismo objeto, para
                             quien necesite esperarlo explícitamente.
     window.DSWaNumber()    número de WhatsApp vigente, listo para usar en
                             wa.me/<numero> (dígitos únicamente). Síncrono:
                             lee de window.DSSettings, con respaldo correcto
                             si la petición aún no ha resuelto o falló. */
(function (global) {
  function apiUrl(p) { return global.DS_API_URL ? global.DS_API_URL(p) : p; }
  var esc = (global.DSSec && global.DSSec.esc) ? global.DSSec.esc : function (s) { return String(s); };

  // Respaldo si la petición todavía no resuelve o falla: el número real vigente,
  // nunca un valor inventado. Debe mantenerse igual al de la migración/semilla.
  var FALLBACK_WA = "5218331645172";
  var MENSAJE_WA_DEFAULT = "Hola DS, me interesa un producto de su catálogo.";

  global.DSSettings = {};

  global.DSWaNumber = function () {
    var n = global.DSSettings && global.DSSettings.contacto_whatsapp;
    n = n ? String(n).replace(/\D+/g, "") : "";
    return n || FALLBACK_WA;
  };

  function aplicarSet(d) {
    document.querySelectorAll("[data-set]").forEach(function (el) {
      var k = el.getAttribute("data-set");
      if (d[k] === undefined) return;
      el.innerHTML = esc(d[k]).replace(/\n/g, "<br>");
    });
  }

  // data-set-href acepta dos formas:
  //   data-set-href="clave"           el valor de la clave YA es una URL completa
  //                                   (redes sociales, botón "Cómo llegar").
  //   data-set-href="tel:clave"       arma "tel:+<dígitos de la clave>" — se usa con
  //                                   contacto_whatsapp (ya validado como solo dígitos
  //                                   con lada) para que el número que se puede marcar
  //                                   sea siempre el vigente, aunque el texto visible
  //                                   (contacto_telefono) tenga espacios o formato libre.
  function aplicarHref(d) {
    document.querySelectorAll("[data-set-href]").forEach(function (el) {
      var raw = el.getAttribute("data-set-href");
      var m = raw.match(/^(tel|mailto):(.+)$/);
      var clave = m ? m[2] : raw;
      if (!d[clave]) return;
      if (m && m[1] === "tel") {
        var digitos = String(d[clave]).replace(/\D+/g, "");
        if (digitos) el.setAttribute("href", "tel:+" + digitos);
      } else if (m && m[1] === "mailto") {
        el.setAttribute("href", "mailto:" + d[clave]);
      } else {
        el.setAttribute("href", d[clave]);
      }
    });
  }

  function aplicarLista(d) {
    document.querySelectorAll("[data-set-lista]").forEach(function (el) {
      var k = el.getAttribute("data-set-lista");
      if (d[k] === undefined) return;
      var molde = el.firstElementChild;
      if (!molde) return;
      var lineas = String(d[k]).split("\n").map(function (l) { return l.trim(); }).filter(Boolean);
      el.innerHTML = "";
      lineas.forEach(function (linea) {
        var clon = molde.cloneNode(true);
        var destino = clon.hasAttribute("data-set-lista-texto")
          ? clon
          : (clon.querySelector("[data-set-lista-texto]") || clon);
        destino.textContent = linea;
        el.appendChild(clon);
      });
    });
  }

  function aplicarWaLink() {
    var numero = global.DSWaNumber();
    document.querySelectorAll("[data-wa-link]").forEach(function (el) {
      var msg = el.getAttribute("data-wa-link") || MENSAJE_WA_DEFAULT;
      el.setAttribute("href", "https://wa.me/" + numero + (msg ? "?text=" + encodeURIComponent(msg) : ""));
    });
  }

  function aplicarOcultarSiVacio(d) {
    document.querySelectorAll("[data-set-ocultar-si-vacio]").forEach(function (el) {
      var k = el.getAttribute("data-set-ocultar-si-vacio");
      if (!d[k] || !String(d[k]).trim()) el.style.display = "none";
    });
  }

  function aplicar(d) {
    global.DSSettings = d;
    aplicarSet(d);
    aplicarHref(d);
    aplicarLista(d);
    aplicarWaLink();
    aplicarOcultarSiVacio(d);
    document.dispatchEvent(new CustomEvent("ds-settings-ready", { detail: d }));
  }

  function ejecutarCuandoListoElDOM(fn) {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", fn);
    } else {
      fn();
    }
  }

  global.DSSettingsReady = fetch(apiUrl("api/settings/get.php"), { credentials: "include" })
    .then(function (r) { return r.json(); })
    .then(function (j) {
      var d = (j && j.ok && j.data) ? j.data : {};
      ejecutarCuandoListoElDOM(function () { aplicar(d); });
      return d;
    })
    .catch(function () {
      ejecutarCuandoListoElDOM(function () { aplicar({}); });
      return {};
    });
})(window);
