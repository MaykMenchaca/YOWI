/* Admin — editor del contenido de la página "Nosotros" (tabla settings) */
(function () {
  "use strict";
  var form = null;

  var FIELDS = [
    "nosotros_mision",
    "val1_titulo", "val1_texto", "val2_titulo", "val2_texto", "val3_titulo", "val3_texto",
    "contacto_direccion", "contacto_telefono", "contacto_email", "contacto_horario", "contacto_whatsapp",
  ];

  function showAlert(msg, type) {
    var el = document.getElementById("alert-banner");
    if (!el) return;
    el.textContent = msg;
    el.className = "mb-4 px-4 py-3 rounded text-sm font-medium " +
      (type === "success" ? "bg-green-900/50 text-green-300 border border-green-700"
                          : "bg-red-900/50 text-red-300 border border-red-700");
    el.classList.remove("hidden");
    setTimeout(function () { el.classList.add("hidden"); }, 4000);
  }

  function load() {
    DSAdminApi.apiFetch("../api/admin/settings/get.php")
      .then(function (data) {
        data = data || {};
        FIELDS.forEach(function (k) {
          var el = form.querySelector('[name="' + k + '"]');
          if (el && data[k] != null) el.value = data[k];
        });
      })
      .catch(function (err) { showAlert("Error al cargar: " + err.message, "error"); });
  }

  function save(e) {
    if (e) e.preventDefault();
    var settings = {};
    FIELDS.forEach(function (k) {
      var el = form.querySelector('[name="' + k + '"]');
      if (el) settings[k] = el.value;
    });
    DSAdminApi.apiFetch("../api/admin/settings/save.php", { method: "POST", body: { settings: settings } })
      .then(function () { showAlert("Cambios guardados. Ya se ven en la página pública.", "success"); })
      .catch(function (err) { showAlert("Error al guardar: " + err.message, "error"); });
  }

  document.addEventListener("DOMContentLoaded", function () {
    form = document.getElementById("nosotros-form");
    document.getElementById("save-btn").addEventListener("click", save);
    form.addEventListener("submit", save);
    load();
  });
})();
