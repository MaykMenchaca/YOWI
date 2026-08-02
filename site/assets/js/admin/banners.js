/* Admin — gestión de promociones (banners del hero) */
(function () {
  "use strict";

  var tableBody = null;
  var modal = null;
  var form  = null;
  var editingId = null;
  var lastFocused = null;

  function esc(s) {
    return String(s || "").replace(/[&<>"']/g, function (c) {
      return { "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;" }[c];
    });
  }

  function showAlert(msg, type) {
    var el = document.getElementById("alert-banner");
    if (!el) return;
    el.textContent = msg;
    el.className = "mb-4 px-4 py-3 rounded text-sm font-medium " +
      (type === "success"
        ? "bg-green-900/50 text-green-300 border border-green-700"
        : "bg-red-900/50 text-red-300 border border-red-700");
    el.classList.remove("hidden");
    setTimeout(function () { el.classList.add("hidden"); }, 5000);
  }

  function showModal() {
    lastFocused = document.activeElement;
    modal.classList.remove("hidden");
    var first = form.querySelector('input, select, textarea, button');
    if (first) first.focus();
  }

  function closeModal() {
    modal.classList.add("hidden");
    editingId = null;
    form.reset();
    if (lastFocused && lastFocused.focus) lastFocused.focus();
  }

  // ── tabla ─────────────────────────────────────────────────────────────────────
  var current = [];
  function loadBanners() {
    DSAdminApi.apiFetch("../api/admin/banners/list.php")
      .then(function (data) { current = data || []; renderTable(current); })
      .catch(function (err) { showAlert("Error al cargar: " + err.message, "error"); });
  }

  function renderTable(banners) {
    if (!tableBody) return;
    if (!banners || banners.length === 0) {
      tableBody.innerHTML = '<tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">Aún no hay promociones. Crea la primera con "+ Nueva promoción".</td></tr>';
      return;
    }
    tableBody.innerHTML = banners.map(function (b) {
      return '<tr class="border-b border-slate-700 hover:bg-slate-700/30">' +
        '<td class="px-3 py-3"><img src="../' + esc(b.imagen) + '" alt="" class="w-28 h-14 object-cover rounded bg-slate-900"></td>' +
        '<td class="px-3 py-3"><div class="text-slate-200 font-medium">' + (esc(b.titulo) || '<span class="text-slate-500">(sin título)</span>') + '</div>' +
          (b.enlace ? '<div class="text-slate-400 text-xs truncate max-w-xs">' + esc(b.enlace) + '</div>' : '') + '</td>' +
        '<td class="px-3 py-3 text-center text-slate-300">' + b.orden + '</td>' +
        '<td class="px-3 py-3 text-center">' +
          '<span class="px-2 py-0.5 rounded text-xs ' + (b.activo ? 'bg-blue-900/50 text-blue-300' : 'bg-slate-700 text-slate-400') + '">' +
            (b.activo ? 'Visible' : 'Oculto') + '</span></td>' +
        '<td class="px-3 py-3 whitespace-nowrap">' +
          '<button class="text-blue-400 hover:text-blue-300 mr-2 edit-btn" data-id="' + b.id + '">Editar</button>' +
          '<button class="text-red-400 hover:text-red-300 delete-btn" data-id="' + b.id + '">Eliminar</button>' +
        '</td>' +
      '</tr>';
    }).join("");

    tableBody.querySelectorAll(".edit-btn").forEach(function (btn) {
      btn.addEventListener("click", function () { openModal(parseInt(btn.dataset.id, 10)); });
    });
    tableBody.querySelectorAll(".delete-btn").forEach(function (btn) {
      btn.addEventListener("click", function () { removeBanner(parseInt(btn.dataset.id, 10)); });
    });
  }

  // ── modal ─────────────────────────────────────────────────────────────────────
  function openModal(id) {
    editingId = id || null;
    document.getElementById("modal-title").textContent = editingId ? "Editar promoción" : "Nueva promoción";
    form.reset();
    document.getElementById("current-imagen").value = "";
    var prev = document.getElementById("image-preview");
    prev.src = ""; prev.classList.add("hidden");

    if (editingId) {
      var b = current.filter(function (x) { return x.id === editingId; })[0];
      if (b) fillForm(b);
    }
    showModal();
  }

  function fillForm(b) {
    form.querySelector('[name="titulo"]').value = b.titulo || "";
    form.querySelector('[name="enlace"]').value = b.enlace || "";
    form.querySelector('[name="orden"]').value  = b.orden != null ? b.orden : 0;
    form.querySelector('[name="activo"]').checked = b.activo !== false;
    document.getElementById("current-imagen").value = b.imagen || "";
    if (b.imagen) {
      var prev = document.getElementById("image-preview");
      prev.src = "../" + b.imagen;
      prev.classList.remove("hidden");
    }
  }

  function removeBanner(id) {
    if (!confirm("¿Eliminar esta promoción? Ya no se mostrará en la portada.")) return;
    DSAdminApi.apiFetch("../api/admin/banners/delete.php", { method: "POST", body: { id: id } })
      .then(function () { loadBanners(); showAlert("Promoción eliminada", "success"); })
      .catch(function (err) { showAlert(err.message, "error"); });
  }

  // ── submit ────────────────────────────────────────────────────────────────────
  function submitForm(e) {
    e.preventDefault();

    var fileInput = form.querySelector('[name="imagen_file"]');
    var currentImg = document.getElementById("current-imagen").value;
    var imagePromise = Promise.resolve(null);

    if (fileInput && fileInput.files && fileInput.files[0]) {
      var fd = new FormData();
      fd.append("imagen", fileInput.files[0]);
      imagePromise = DSAdminApi.apiFetch("../api/admin/banners/upload-image.php", { method: "POST", body: fd });
    } else if (!currentImg) {
      showAlert("Sube una imagen para la promoción.", "error");
      return;
    }

    imagePromise.then(function (imgData) {
      var body = {
        titulo: form.querySelector('[name="titulo"]').value.trim() || null,
        imagen: imgData ? imgData.url : currentImg,
        enlace: form.querySelector('[name="enlace"]').value.trim() || null,
        orden:  parseInt(form.querySelector('[name="orden"]').value, 10) || 0,
        activo: form.querySelector('[name="activo"]').checked ? 1 : 0,
      };
      if (editingId) body.id = editingId;
      var path = editingId ? "../api/admin/banners/update.php" : "../api/admin/banners/create.php";
      return DSAdminApi.apiFetch(path, { method: "POST", body: body });
    })
      .then(function () {
        closeModal();
        loadBanners();
        showAlert(editingId ? "Promoción actualizada" : "Promoción creada", "success");
      })
      .catch(function (err) { showAlert(err.message, "error"); });
  }

  function setupImagePreview() {
    var fileInput = form.querySelector('[name="imagen_file"]');
    if (!fileInput) return;
    fileInput.addEventListener("change", function () {
      if (!fileInput.files || !fileInput.files[0]) return;
      var reader = new FileReader();
      reader.onload = function (e) {
        var prev = document.getElementById("image-preview");
        prev.src = e.target.result;
        prev.classList.remove("hidden");
      };
      reader.readAsDataURL(fileInput.files[0]);
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    tableBody = document.getElementById("banners-tbody");
    modal     = document.getElementById("banner-modal");
    form      = document.getElementById("banner-form");

    document.getElementById("new-banner-btn").addEventListener("click", function () { openModal(null); });
    document.getElementById("modal-cancel").addEventListener("click", closeModal);
    // Botón "Cancelar" del pie del modal (antes era un <script> inline en promociones.html).
    var cancelBtn = document.getElementById("modal-cancel-btn");
    if (cancelBtn) cancelBtn.addEventListener("click", closeModal);
    form.addEventListener("submit", submitForm);

    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && !modal.classList.contains("hidden")) closeModal();
    });

    setupImagePreview();
    loadBanners();
  });
})();
