/* Admin mobile nav — sidebar off-canvas para viewports angostos. */
(function () {
  "use strict";

  function setOpen(sidebar, overlay, toggle, open) {
    sidebar.classList.toggle("-translate-x-full", !open);
    overlay.classList.toggle("hidden", !open);
    toggle.setAttribute("aria-expanded", open ? "true" : "false");
  }

  function init() {
    var toggle = document.getElementById("sidebar-toggle");
    var sidebar = document.getElementById("admin-sidebar");
    var overlay = document.getElementById("sidebar-overlay");
    if (!toggle || !sidebar || !overlay) return;

    toggle.addEventListener("click", function () {
      var isOpen = !sidebar.classList.contains("-translate-x-full");
      setOpen(sidebar, overlay, toggle, !isOpen);
    });

    overlay.addEventListener("click", function () {
      setOpen(sidebar, overlay, toggle, false);
    });

    document.addEventListener("keydown", function (e) {
      if (e.key !== "Escape") return;
      var isOpen = !sidebar.classList.contains("-translate-x-full");
      if (isOpen) setOpen(sidebar, overlay, toggle, false);
    });
  }

  document.addEventListener("DOMContentLoaded", init);
})();
