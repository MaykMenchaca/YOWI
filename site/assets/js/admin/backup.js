/* Admin — Respaldo y restauración por apartado (F5.3).
   Módulo compartido por las 7 páginas del panel (6 secciones + dashboard). Cada página
   solo necesita: 1) los botones con los ids fijos de abajo, 2) el input de archivo oculto,
   y 3) `data-backup-tipo="<tipo>"` en el <body> (ej. "productos", "categorias", ... "todo"
   para el dashboard). Este script detecta el tipo automáticamente y conecta todo — cero
   JS inline en el HTML.

   IDs esperados en la página:
     #backup-export-btn   <a download href="#"> — se le completa el href con el tipo
     #backup-restore-btn  <button> — dispara el <input type="file"> oculto
     #backup-restore-file <input type="file" accept=".json" class="hidden">
     #alert-banner        (opcional) contenedor de mensajes, mismo patrón que products.js;
                           si no existe, se usa window.alert() como respaldo. */
(function () {
  "use strict";

  var TIPOS_VALIDOS = ["productos", "categorias", "marcas", "pedidos", "promociones", "nosotros", "todo"];

  function showAlert(msg, type) {
    var el = document.getElementById("alert-banner");
    if (!el) {
      window.alert(msg);
      return;
    }
    el.textContent = msg;
    el.className = "mb-4 px-4 py-3 rounded text-sm font-medium " +
      (type === "success"
        ? "bg-green-900/50 text-green-300 border border-green-700"
        : "bg-red-900/50 text-red-300 border border-red-700");
    el.classList.remove("hidden");
    window.setTimeout(function () { el.classList.add("hidden"); }, 6000);
  }

  // Arma un texto legible del resumen devuelto por backup/import.php (preview o aplicado).
  function resumenTexto(tipo, resumen) {
    if (tipo === "todo" && resumen && resumen.secciones) {
      var lineas = [
        "TOTAL — crear: " + resumen.crear + ", actualizar: " + resumen.actualizar +
        ", sin cambios/omitidos: " + resumen.sin_cambios_o_omitidos + ".",
      ];
      Object.keys(resumen.secciones).forEach(function (seccion) {
        var s = resumen.secciones[seccion];
        lineas.push("  · " + seccion + ": crear " + s.crear + ", actualizar " + s.actualizar +
          ", sin cambios/omitidos " + s.sin_cambios_o_omitidos);
      });
      return lineas.join("\n");
    }
    if (!resumen) return "";
    return "Se crearán " + resumen.crear + " y se actualizarán " + resumen.actualizar +
      " registro(s) (" + resumen.sin_cambios_o_omitidos + " sin cambios u omitidos).";
  }

  function aplicar(tipo, datos) {
    DSAdminApi.apiFetch("../api/admin/backup/import.php", {
      method: "POST",
      body: { tipo: tipo, datos: datos, preview: false },
    })
      .then(function (data) {
        showAlert("Restauración aplicada. " + resumenTexto(tipo, data.resumen), "success");
        // Ningún módulo de sección expone una función de recarga en el scope global,
        // así que refrescamos la página completa para ver los datos ya actualizados.
        window.setTimeout(function () { window.location.reload(); }, 1200);
      })
      .catch(function (err) {
        showAlert("Error al restaurar: " + err.message, "error");
      });
  }

  function preview(tipo, datos) {
    DSAdminApi.apiFetch("../api/admin/backup/import.php", {
      method: "POST",
      body: { tipo: tipo, datos: datos, preview: true },
    })
      .then(function (data) {
        var advertencia = tipo === "todo"
          ? "Esto puede crear o actualizar registros en TODAS las secciones " +
            "(productos, categorías, marcas, pedidos, promociones, Nosotros).\n\n"
          : "";
        var texto = advertencia + resumenTexto(tipo, data.resumen) + "\n\n¿Aplicar la restauración?";
        if (window.confirm(texto)) aplicar(tipo, datos);
      })
      .catch(function (err) {
        showAlert("Error al analizar el respaldo: " + err.message, "error");
      });
  }

  function handleFile(tipo, rawText) {
    var parsed;
    try {
      parsed = JSON.parse(rawText);
    } catch (e) {
      showAlert("El archivo elegido no es un JSON válido.", "error");
      return;
    }

    if (!parsed || typeof parsed !== "object" || Array.isArray(parsed) ||
        typeof parsed.tipo !== "string" || !("datos" in parsed)) {
      showAlert('El archivo no tiene la forma de un respaldo válido (se esperaba {"tipo", "datos"}).', "error");
      return;
    }

    // El respaldo debe ser del mismo tipo que esta página (evita restaurar "pedidos" en
    // "categorías" por error). Excepción: en el dashboard el tipo de página ya es "todo",
    // así que un respaldo "todo" coincide de forma natural con esta misma regla.
    if (parsed.tipo !== tipo) {
      showAlert(
        'Este archivo es un respaldo de "' + parsed.tipo + '", pero esta página espera uno de "' +
        tipo + '". Sube el archivo correcto.',
        "error"
      );
      return;
    }

    preview(tipo, parsed.datos);
  }

  function init(tipo) {
    if (TIPOS_VALIDOS.indexOf(tipo) === -1) return;

    var btnExport = document.getElementById("backup-export-btn");
    if (btnExport) {
      btnExport.setAttribute("href", "../api/admin/backup/export.php?tipo=" + encodeURIComponent(tipo));
    }

    var btnRestore = document.getElementById("backup-restore-btn");
    var fileInput = document.getElementById("backup-restore-file");
    if (!btnRestore || !fileInput) return;

    btnRestore.addEventListener("click", function () {
      fileInput.value = ""; // permite volver a elegir el mismo archivo dos veces seguidas
      fileInput.click();
    });

    fileInput.addEventListener("change", function () {
      var file = fileInput.files && fileInput.files[0];
      if (!file) return;

      var reader = new FileReader();
      reader.onload = function () { handleFile(tipo, String(reader.result || "")); };
      reader.onerror = function () { showAlert("No se pudo leer el archivo elegido.", "error"); };
      reader.readAsText(file);
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    var tipo = document.body ? document.body.getAttribute("data-backup-tipo") : null;
    if (tipo) init(tipo);
  });

  window.DSBackup = { init: init };
})();
