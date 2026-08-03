/* DS Cart — carrito en localStorage + envío de pedido (WhatsApp + registro en BD).
   Clave de localStorage: "ds_cart" → array de líneas
   {producto_id, sabor_id, sabor, nombre, precio, cantidad, imagen}.
   Producto + sabor son líneas DISTINTAS: la identidad de una línea es
   producto_id + sabor_id (sabor_id null = sin sabor), no solo producto_id. */
(function (global) {
  var STORAGE_KEY = "ds_cart";
  var WA_NUMBER = "5218344241599";
  // El token CSRF se obtiene de forma asíncrona (me.php). Hasta que esté listo, el botón
  // "Enviar" permanece deshabilitado para evitar un POST sin token (403 silencioso).
  var csrfReady = false;

  // Identidad de una línea del carrito: mismo producto CON distinto sabor son líneas
  // distintas (ej. Chocolate y Vainilla del mismo producto conviven en el carrito).
  function lineKey(productoId, saborId) {
    return String(productoId) + "::" + (saborId == null || saborId === "" ? "" : String(saborId));
  }

  function getCart() {
    try {
      var raw = localStorage.getItem(STORAGE_KEY);
      var items = raw ? JSON.parse(raw) : [];
      if (!Array.isArray(items)) return [];
      // Sanear cada línea: descartar las sin producto_id, normalizar tipos.
      // Protege contra un ds_cart manipulado en localStorage (cantidad "abc", precio negativo…).
      return items
        .filter(function (i) { return i && i.producto_id != null && i.producto_id !== ""; })
        .map(function (i) {
          return {
            producto_id: i.producto_id,
            sabor_id: i.sabor_id != null && i.sabor_id !== "" ? i.sabor_id : null,
            sabor: i.sabor ? String(i.sabor) : null,
            nombre: String(i.nombre || ""),
            precio: Math.max(0, Number(i.precio) || 0),
            cantidad: Math.max(0, parseInt(i.cantidad, 10) || 0),
            imagen: String(i.imagen || ""),
          };
        })
        .filter(function (i) { return i.cantidad > 0; });
    } catch (e) {
      return [];
    }
  }

  function saveCart(items) {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
    } catch (e) { /* localStorage no disponible (modo privado): el carrito no persiste */ }
    updateCartBadge(items);
  }

  function updateCartBadge(items) {
    items = items || getCart();
    var count = items.reduce(function (sum, i) { return sum + i.cantidad; }, 0);
    // Selector estable por atributo; fallback al viejo por si algún header no se actualizó.
    var badges = document.querySelectorAll('[data-cart-badge], a[href="pedido.html"] span.absolute');
    badges.forEach(function (badge) {
      badge.textContent = String(count);
    });
  }

  // sabor (opcional): {id, nombre, precio} — cuando el producto tiene sabores, el precio
  // de la línea es el del sabor elegido (ya resuelto por catalog-engine.js: el propio si
  // lo tiene, si no el del producto), nunca el precio "base" del producto a ciegas.
  function addItem(producto, cantidad, sabor) {
    cantidad = cantidad || 1;
    var saborId = sabor ? sabor.id : null;
    var items = getCart();
    var key = lineKey(producto.id, saborId);
    var existing = items.filter(function (i) { return lineKey(i.producto_id, i.sabor_id) === key; })[0];
    if (existing) {
      existing.cantidad += cantidad;
    } else {
      items.push({
        producto_id: producto.id,
        sabor_id: saborId,
        sabor: sabor ? sabor.nombre : null,
        nombre: producto.nombre,
        precio: sabor ? sabor.precio : producto.precio,
        cantidad: cantidad,
        imagen: producto.imagen,
      });
    }
    saveCart(items);
  }

  function removeItem(productoId, saborId) {
    var key = lineKey(productoId, saborId);
    saveCart(getCart().filter(function (i) { return lineKey(i.producto_id, i.sabor_id) !== key; }));
  }

  function updateQty(productoId, saborId, delta) {
    delta = parseInt(delta, 10);
    if (isNaN(delta)) return;
    var key = lineKey(productoId, saborId);
    var items = getCart();
    var item = items.filter(function (i) { return lineKey(i.producto_id, i.sabor_id) === key; })[0];
    if (!item) return;
    item.cantidad += delta;
    if (item.cantidad <= 0) {
      items = items.filter(function (i) { return lineKey(i.producto_id, i.sabor_id) !== key; });
    }
    saveCart(items);
  }

  function money(n) {
    return "$" + Number(n).toLocaleString("es-MX", { minimumFractionDigits: 2 }) + " MXN";
  }

  function esc(s) {
    return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }

  function cartLineHTML(item) {
    var subtotal = money(item.precio * item.cantidad);
    var saborAttr = item.sabor_id != null ? ' data-sabor-id="' + esc(item.sabor_id) + '"' : "";
    var nombreLinea = item.sabor ? esc(item.nombre) + ' — ' + esc(item.sabor) : esc(item.nombre);
    return (
      '<div class="bg-surface-container-lowest border custom-border rounded-lg p-stack-md flex flex-col sm:flex-row items-center gap-stack-md custom-shadow transition-shadow hover:shadow-md" data-cart-line="' + item.producto_id + '"' + saborAttr + '>' +
        '<img class="w-24 h-24 object-contain rounded bg-surface-container-low p-2" loading="lazy" src="' + esc(item.imagen) + '" alt="' + esc(item.nombre) + '"/>' +
        '<div class="flex-grow w-full text-center sm:text-left">' +
          '<h3 class="font-headline-sm text-headline-sm text-[#042C53]">' + nombreLinea + '</h3>' +
        '</div>' +
        '<div class="font-price-display text-price-display text-primary whitespace-nowrap">' + money(item.precio) + '</div>' +
        '<div class="flex items-center gap-2 border custom-border rounded bg-surface">' +
          '<button type="button" class="p-2 text-on-surface-variant hover:text-primary transition-colors cart-qty-btn" data-delta="-1" data-id="' + item.producto_id + '"' + saborAttr + '><span class="material-symbols-outlined text-sm">remove</span></button>' +
          '<span class="w-8 text-center font-body-md font-semibold">' + item.cantidad + '</span>' +
          '<button type="button" class="p-2 text-on-surface-variant hover:text-primary transition-colors cart-qty-btn" data-delta="1" data-id="' + item.producto_id + '"' + saborAttr + '><span class="material-symbols-outlined text-sm">add</span></button>' +
        '</div>' +
        '<div class="font-price-display text-price-display text-primary whitespace-nowrap">' + subtotal + '</div>' +
        '<button type="button" aria-label="Eliminar producto" class="text-error hover:text-error-container transition-colors p-2 cart-remove-btn" data-id="' + item.producto_id + '"' + saborAttr + '>' +
          '<span class="material-symbols-outlined">delete</span>' +
        '</button>' +
      '</div>'
    );
  }

  function renderCart(container) {
    if (!container) return;
    var items = getCart();
    if (!items.length) {
      container.innerHTML = '<p class="p-6 text-center text-on-surface-variant">Tu carrito está vacío. <a href="catalogo.html" class="text-primary underline">Ver productos</a></p>';
      return;
    }
    container.innerHTML = items.map(cartLineHTML).join("");
  }

  function renderSummary(container) {
    if (!container) return;
    var items = getCart();
    var total = items.reduce(function (sum, i) { return sum + i.precio * i.cantidad; }, 0);
    container.querySelectorAll("[data-summary]").forEach(function (el) {
      var field = el.getAttribute("data-summary");
      if (field === "count") el.textContent = String(items.length);
      if (field === "total") el.textContent = money(total);
    });
  }

  // Campos obligatorios del checkout (envío a domicilio).
  var REQUIRED_FIELDS = ["nombre", "telefono", "calle", "colonia", "cp", "ciudad", "estado"];
  var ALL_FIELDS = REQUIRED_FIELDS.concat(["referencias", "notas"]);

  function readForm(form) {
    var d = {};
    ALL_FIELDS.forEach(function (n) {
      var el = form.querySelector('[name="' + n + '"]');
      d[n] = el ? String(el.value || "").trim() : "";
    });
    return d;
  }

  function buildDireccion(d) {
    var parts = [];
    if (d.calle) parts.push(d.calle);
    if (d.colonia) parts.push("Col. " + d.colonia);
    if (d.cp) parts.push("CP " + d.cp);
    if (d.ciudad) parts.push(d.ciudad);
    if (d.estado) parts.push(d.estado);
    var linea = parts.join(", ");
    if (d.referencias) linea += " (Ref: " + d.referencias + ")";
    return linea;
  }

  function buildWhatsAppMessage(items, d) {
    var total = items.reduce(function (sum, i) { return sum + i.precio * i.cantidad; }, 0);
    var lines = ["Hola DS, quiero hacer este pedido:", ""];
    items.forEach(function (i) {
      var nombre = i.sabor ? i.nombre + " (" + i.sabor + ")" : i.nombre;
      lines.push("• " + i.cantidad + "x " + nombre + " — " + money(i.precio));
    });
    lines.push("");
    lines.push("Subtotal: " + money(total));
    lines.push("");
    lines.push("*Datos de envío*");
    lines.push("Nombre: " + d.nombre);
    lines.push("Tel: " + d.telefono);
    lines.push("Dirección: " + buildDireccion(d));
    if (d.notas) lines.push("Notas: " + d.notas);
    lines.push("");
    lines.push("Pago: transferencia (SPEI)");
    return lines.join("\n");
  }

  function showFieldError(form, name, msg) {
    var p = form.querySelector('[data-error-for="' + name + '"]');
    if (p) { p.textContent = msg; p.classList.remove("hidden"); }
    var el = form.querySelector('[name="' + name + '"]');
    if (el) el.classList.add("border-error");
  }

  function clearFieldError(form, name) {
    var p = form.querySelector('[data-error-for="' + name + '"]');
    if (p) { p.textContent = ""; p.classList.add("hidden"); }
    var el = form.querySelector('[name="' + name + '"]');
    if (el) el.classList.remove("border-error");
  }

  // Valida el formulario; devuelve el nombre del primer campo inválido, o null si todo ok.
  function validateForm(form, d) {
    REQUIRED_FIELDS.concat(["form"]).forEach(function (n) { clearFieldError(form, n); });
    var firstInvalid = null;
    REQUIRED_FIELDS.forEach(function (n) {
      if (!d[n]) {
        showFieldError(form, n, "Campo obligatorio");
        if (!firstInvalid) firstInvalid = n;
      }
    });
    if (d.telefono && d.telefono.replace(/\D/g, "").length < 10) {
      showFieldError(form, "telefono", "Ingresa un teléfono de 10 dígitos");
      if (!firstInvalid) firstInvalid = "telefono";
    }
    if (d.cp && !/^\d{5}$/.test(d.cp)) {
      showFieldError(form, "cp", "El código postal son 5 dígitos");
      if (!firstInvalid) firstInvalid = "cp";
    }
    return firstInvalid;
  }

  // Oculta el formulario de envío y desactiva el botón cuando el carrito está vacío.
  function syncCheckoutState() {
    var has = getCart().length > 0;
    var card = document.getElementById("shipping-card");
    var btn = document.getElementById("submit-order-btn");
    if (card) card.classList.toggle("hidden", !has);
    if (btn) {
      // Deshabilitar hasta que el carrito tenga items Y el CSRF esté listo.
      btn.disabled = !has || !csrfReady;
      if (has && !csrfReady) {
        if (!btn.getAttribute("data-label")) btn.setAttribute("data-label", btn.textContent);
        btn.textContent = "Preparando…";
      } else if (btn.getAttribute("data-label")) {
        btn.textContent = btn.getAttribute("data-label");
      }
    }
  }

  function submitOrder(form) {
    var items = getCart();
    if (!items.length) {
      showFieldError(form, "form", "Tu carrito está vacío.");
      return;
    }
    var d = readForm(form);
    var firstInvalid = validateForm(form, d);
    if (firstInvalid) {
      showFieldError(form, "form", "Revisa los campos marcados.");
      var el = form.querySelector('[name="' + firstInvalid + '"]');
      if (el && el.focus) el.focus();
      return;
    }

    var mensaje = buildWhatsAppMessage(items, d);
    var waUrl = "https://wa.me/" + WA_NUMBER + "?text=" + encodeURIComponent(mensaje);

    // Reservar la pestaña de WhatsApp AHORA, con el gesto del click (los pop-ups se
    // bloquean si se abren en un callback async). Solo se redirige si el POST tiene éxito;
    // si el pedido falla, se cierra y NO se abre WhatsApp (evita "pedido fantasma").
    var waWin = window.open("", "_blank");

    if (global.DSApi) {
      global.DSApi.apiFetch("api/orders/create.php", {
        method: "POST",
        body: {
          nombre_cliente: d.nombre,
          ciudad: d.ciudad,
          telefono: d.telefono,
          direccion_envio: buildDireccion(d),
          notas: d.notas,
          items: items.map(function (i) {
            return {
              producto_id: i.producto_id,
              sabor_id: i.sabor_id,
              nombre_producto: i.nombre,
              precio_unitario: i.precio,
              cantidad: i.cantidad,
            };
          }),
          mensaje_whatsapp: mensaje,
        },
      })
        .then(function () {
          saveCart([]);
          if (waWin) { waWin.location = waUrl; } else { window.open(waUrl, "_blank"); }
        })
        .catch(function (err) {
          if (waWin) waWin.close();
          showFieldError(form, "form", "No se pudo registrar el pedido: " + err.message + ". Intenta de nuevo.");
        });
    } else {
      // Sin cliente API (degradado): abrir WhatsApp igual, sin regresión.
      if (waWin) { waWin.location = waUrl; } else { window.open(waUrl, "_blank"); }
    }
  }

  document.addEventListener("DOMContentLoaded", function () {
    updateCartBadge();

    // Obtener el token CSRF del cliente para poder registrar el pedido en la BD.
    // (pedido.html no carga auth.js, así que el token hay que fijarlo aquí; sin esto el
    //  POST a orders/create.php responde 403 y el pedido nunca se guarda.)
    if (global.DSApi) {
      fetch(global.DS_API_URL ? global.DS_API_URL("api/auth/me.php") : "api/auth/me.php", { credentials: "include" })
        .then(function (r) { return r.json(); })
        .then(function (j) { if (j && j.ok && j.data && j.data.csrf_token) global.DSApi.setCsrfToken(j.data.csrf_token); })
        .catch(function () {})
        // Éxito o fallo: liberar el botón. Si falló, el POST se intentará y, si da 403,
        // submitOrder muestra el error (no dejar el botón muerto para siempre).
        .then(function () { csrfReady = true; syncCheckoutState(); });
    } else {
      csrfReady = true; // sin DSApi no hay token que esperar (flujo degradado)
    }

    document.addEventListener("click", function (e) {
      var qtyBtn = e.target.closest(".cart-qty-btn");
      if (qtyBtn) {
        updateQty(qtyBtn.getAttribute("data-id"), qtyBtn.getAttribute("data-sabor-id"), parseInt(qtyBtn.getAttribute("data-delta"), 10));
        var container = document.getElementById("cart-items");
        var summary = document.getElementById("cart-summary");
        if (container) renderCart(container);
        if (summary) renderSummary(summary);
        syncCheckoutState();
        return;
      }
      var removeBtn = e.target.closest(".cart-remove-btn");
      if (removeBtn) {
        removeItem(removeBtn.getAttribute("data-id"), removeBtn.getAttribute("data-sabor-id"));
        var container2 = document.getElementById("cart-items");
        var summary2 = document.getElementById("cart-summary");
        if (container2) renderCart(container2);
        if (summary2) renderSummary(summary2);
        syncCheckoutState();
        return;
      }
      var addBtn = e.target.closest(".add-to-cart-btn");
      if (addBtn && !addBtn.disabled && global.DSCatalog) {
        // Si la ficha ya eligió un sabor (F3.6), el propio botón lo lleva marcado en
        // data-sabor-* (ver updateAddToCartState en catalog-engine.js); las tarjetas del
        // catálogo sin sabores no traen esos atributos y sabor queda null.
        var saborId = addBtn.getAttribute("data-sabor-id");
        var sabor = saborId
          ? {
              id: saborId,
              nombre: addBtn.getAttribute("data-sabor-nombre"),
              precio: parseFloat(addBtn.getAttribute("data-sabor-precio")),
            }
          : null;
        global.DSCatalog.fetchProductos().then(function (productos) {
          var wanted = addBtn.getAttribute("data-product-id");
          var p = productos.filter(function (item) { return String(item.id) === String(wanted); })[0];
          if (p) addItem(p, 1, sabor);
        }).catch(function () {
          alert("No se pudo agregar el producto. Revisa tu conexión e inténtalo de nuevo.");
        });
      }
    });

    var cartItems = document.getElementById("cart-items");
    var cartSummary = document.getElementById("cart-summary");
    if (cartItems) renderCart(cartItems);
    if (cartSummary) renderSummary(cartSummary);
    syncCheckoutState();

    var checkoutForm = document.getElementById("checkout-form");
    var submitBtn = document.getElementById("submit-order-btn");
    if (checkoutForm && submitBtn) {
      submitBtn.addEventListener("click", function (e) {
        // stopImmediatePropagation: el texto del botón contiene "WhatsApp",
        // lo que también lo capturaría el listener genérico de enhance.js
        // (abriría una segunda pestaña con un mensaje fijo). Este listener
        // se registra primero, así que bloquea el genérico sin tocar enhance.js.
        // preventDefault también evita el submit nativo del <form> (recarga).
        e.preventDefault();
        e.stopImmediatePropagation();
        submitOrder(checkoutForm);
      });

      // Enter dentro de un campo dispara submit del form (no un click al botón):
      // lo interceptamos aquí para validar sin recargar la página.
      checkoutForm.addEventListener("submit", function (e) {
        e.preventDefault();
        submitOrder(checkoutForm);
      });

      // Limpiar el error de un campo en cuanto el usuario lo corrige.
      checkoutForm.addEventListener("input", function (e) {
        var name = e.target && e.target.getAttribute ? e.target.getAttribute("name") : null;
        if (name) clearFieldError(checkoutForm, name);
      });
    }
  });

  global.DSCart = {
    getCart: getCart,
    addItem: addItem,
    removeItem: removeItem,
    updateQty: updateQty,
    renderCart: renderCart,
    renderSummary: renderSummary,
    buildWhatsAppMessage: buildWhatsAppMessage,
    submitOrder: submitOrder,
  };
})(window);
