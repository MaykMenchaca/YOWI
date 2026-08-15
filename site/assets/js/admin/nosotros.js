/* Admin — editor del contenido editable de la tienda (tabla settings): Nosotros,
   Contacto, Redes, Datos del negocio y Textos legales.

   Los campos a leer/guardar NO están en una lista aparte aquí: son simplemente todos
   los [name] del formulario. Antes esta lista vivía hardcodeada en este archivo —una
   quinta copia de las claves de settings, además de las de save.php/get.php/import.php—
   así que agregar un campo significaba tocar dos archivos. Con esto basta con agregar
   el campo al HTML (con el mismo nombre que su clave en site/api/lib/Settings.php). */
(function () {
  "use strict";
  var form = null;

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
        form.querySelectorAll("[name]").forEach(function (el) {
          if (data[el.name] != null) el.value = data[el.name];
        });
      })
      .catch(function (err) { showAlert("Error al cargar: " + err.message, "error"); });
  }

  function save(e) {
    if (e) e.preventDefault();
    var settings = {};
    form.querySelectorAll("[name]").forEach(function (el) {
      settings[el.name] = el.value;
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
