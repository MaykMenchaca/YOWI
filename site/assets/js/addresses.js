/* DS Direcciones — CRUD de direcciones en Mi cuenta + autollenado del checkout.
   Depende de config.js (DS_API_URL) y api-client.js (DSApi) cargados antes. */
(function (global) {
  function apiUrl(p) { return global.DS_API_URL ? global.DS_API_URL(p) : p; }

  function esc(s) {
    return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }

  var loggedIn = false;
  var sessionPromise = null;
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

  function fetchAddresses() {
    return global.DSApi.apiFetch("api/addresses/list.php");
  }

  function addressLine(a) {
    var parts = [];
    if (a.calle) parts.push(a.calle);
    if (a.colonia) parts.push("Col. " + a.colonia);
    if (a.cp) parts.push("CP " + a.cp);
    if (a.ciudad) parts.push(a.ciudad);
    if (a.estado) parts.push(a.estado);
    return parts.join(", ");
  }

  // ─────────────────────────── Mi cuenta ───────────────────────────
  function initAccount() {
    var listBox = document.getElementById("addresses-list");
    if (!listBox) return;
    var modal = document.getElementById("address-modal");
    var form = document.getElementById("address-form");
    var title = document.getElementById("address-modal-title");
    var errBox = form ? form.querySelector("[data-address-error]") : null;
    var addBtn = document.getElementById("add-address-btn");
    var cancelBtn = document.getElementById("address-cancel");
    var FIELDS = ["etiqueta", "nombre", "telefono", "calle", "colonia", "cp", "ciudad", "estado", "referencias"];

    function load() {
      fetchAddresses()
        .then(function (list) { render(list); })
        .catch(function () {
          listBox.innerHTML = '<p class="col-span-full text-center text-error py-6">No se pudieron cargar tus direcciones.</p>';
        });
    }

    function render(list) {
      if (!list || !list.length) {
        listBox.innerHTML = '<div class="col-span-full text-center text-gray-500 py-10">' +
          '<span class="material-symbols-outlined text-5xl text-gray-300">location_on</span>' +
          '<p class="mt-3 font-bold text-ink">Aún no tienes direcciones</p>' +
          '<p class="text-sm mt-1">Agrega una para usarla al hacer tus pedidos.</p></div>';
        return;
      }
      listBox.innerHTML = list.map(function (a) {
        return (
          '<div class="border border-gray-200 rounded-lg p-4 flex flex-col" data-address=\'' + esc(JSON.stringify(a)) + '\'>' +
            '<div class="flex items-center justify-between mb-1">' +
              '<span class="font-extrabold text-ink uppercase text-sm">' + esc(a.etiqueta || "Dirección") + '</span>' +
              (a.es_predeterminada ? '<span class="text-[10px] font-bold uppercase tracking-widest bg-lime text-ink px-2 py-0.5 rounded">Predeterminada</span>' : '') +
            '</div>' +
            '<p class="text-sm font-bold text-ink">' + esc(a.nombre) + '</p>' +
            (a.telefono ? '<p class="text-xs text-gray-500">Tel: ' + esc(a.telefono) + '</p>' : '') +
            '<p class="text-sm text-gray-600 mt-1">' + esc(addressLine(a)) + '</p>' +
            (a.referencias ? '<p class="text-xs text-gray-400 mt-1">Ref: ' + esc(a.referencias) + '</p>' : '') +
            '<div class="flex gap-2 mt-3 pt-3 border-t border-gray-100">' +
              '<button type="button" class="addr-edit-btn text-xs font-bold uppercase tracking-widest text-brand hover:underline flex items-center gap-1"><span class="material-symbols-outlined text-sm">edit</span>Editar</button>' +
              '<button type="button" class="addr-del-btn text-xs font-bold uppercase tracking-widest text-error hover:underline flex items-center gap-1 ml-auto"><span class="material-symbols-outlined text-sm">delete</span>Eliminar</button>' +
            '</div>' +
          '</div>'
        );
      }).join("");
    }

    function openModal(a) {
      a = a || {};
      if (title) title.textContent = a.id ? "Editar dirección" : "Nueva dirección";
      form.querySelector('[name="id"]').value = a.id || "";
      FIELDS.forEach(function (f) {
        var el = form.querySelector('[name="' + f + '"]');
        if (el) el.value = a[f] || "";
      });
      var chk = form.querySelector('[name="es_predeterminada"]');
      if (chk) chk.checked = !!a.es_predeterminada;
      if (errBox) errBox.classList.add("hidden");
      modal.classList.remove("hidden");
    }
    function closeModal() { modal.classList.add("hidden"); }

    if (addBtn) addBtn.addEventListener("click", function () { openModal(null); });
    if (cancelBtn) cancelBtn.addEventListener("click", closeModal);

    listBox.addEventListener("click", function (e) {
      var card = e.target.closest("[data-address]");
      if (!card) return;
      var data;
      try { data = JSON.parse(card.getAttribute("data-address")); } catch (_) { data = {}; }
      if (e.target.closest(".addr-edit-btn")) { openModal(data); return; }
      if (e.target.closest(".addr-del-btn")) {
        if (!global.confirm("¿Eliminar esta dirección?")) return;
        global.DSApi.apiFetch("api/addresses/delete.php", { method: "POST", body: { id: data.id } })
          .then(load)
          .catch(function (err) { global.alert(err.message); });
      }
    });

    if (form) {
      form.addEventListener("submit", function (e) {
        e.preventDefault();
        var body = { id: form.querySelector('[name="id"]').value || 0 };
        FIELDS.forEach(function (f) {
          var el = form.querySelector('[name="' + f + '"]');
          body[f] = el ? el.value.trim() : "";
        });
        var chk = form.querySelector('[name="es_predeterminada"]');
        body.es_predeterminada = !!(chk && chk.checked);
        if (!body.nombre) { showErr("El nombre es obligatorio"); return; }
        if (!body.calle) { showErr("La calle es obligatoria"); return; }
        if (body.cp && !/^\d{5}$/.test(body.cp)) { showErr("El código postal son 5 dígitos"); return; }
        global.DSApi.apiFetch("api/addresses/save.php", { method: "POST", body: body })
          .then(function () { closeModal(); load(); })
          .catch(function (err) { showErr(err.message); });
      });
    }
    function showErr(msg) { if (errBox) { errBox.textContent = msg; errBox.classList.remove("hidden"); } }

    ensureSession().then(function (logged) { if (logged) load(); });
  }

  // ─────────────────────────── Checkout ───────────────────────────
  function initCheckout() {
    var select = document.getElementById("saved-address");
    if (!select) return;
    var wrapper = document.getElementById("saved-address-wrapper");
    var form = document.getElementById("checkout-form");
    var addresses = [];

    ensureSession().then(function (logged) {
      if (!logged) return;
      fetchAddresses().then(function (list) {
        addresses = list || [];
        if (!addresses.length) return;
        if (wrapper) wrapper.classList.remove("hidden");
        select.innerHTML = '<option value="">— Escribir una nueva —</option>' +
          addresses.map(function (a, i) {
            var label = (a.etiqueta ? a.etiqueta + ": " : "") + addressLine(a);
            return '<option value="' + i + '">' + esc(label) + '</option>';
          }).join("");
        // Preseleccionar la predeterminada y autollenar.
        var defIdx = addresses.findIndex(function (a) { return a.es_predeterminada; });
        if (defIdx >= 0) { select.value = String(defIdx); fill(addresses[defIdx]); }
      }).catch(function () {});
    });

    select.addEventListener("change", function () {
      var idx = select.value;
      if (idx === "") return;
      var a = addresses[Number(idx)];
      if (a) fill(a);
    });

    function fill(a) {
      if (!form) return;
      var map = { nombre: a.nombre, telefono: a.telefono, calle: a.calle, colonia: a.colonia,
        cp: a.cp, ciudad: a.ciudad, estado: a.estado, referencias: a.referencias };
      Object.keys(map).forEach(function (name) {
        var el = form.querySelector('[name="' + name + '"]');
        if (el && map[name] != null) {
          el.value = map[name];
          el.dispatchEvent(new Event("input", { bubbles: true })); // limpia errores de cart.js
        }
      });
    }
  }

  document.addEventListener("DOMContentLoaded", function () {
    initAccount();
    initCheckout();
  });

  global.DSAddresses = { ensureSession: ensureSession };
})(window);
