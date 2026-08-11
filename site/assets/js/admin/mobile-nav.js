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

  function setMenuOpen(menu, toggle, open) {
    menu.classList.toggle("hidden", !open);
    menu.classList.toggle("flex", open);
    toggle.setAttribute("aria-expanded", open ? "true" : "false");
  }

  function initPageActionsMenu() {
    var toggle = document.getElementById("page-actions-toggle");
    var menu = document.getElementById("page-actions-menu");
    if (!toggle || !menu) return;

    toggle.addEventListener("click", function (e) {
      e.stopPropagation();
      var isOpen = !menu.classList.contains("hidden");
      setMenuOpen(menu, toggle, !isOpen);
    });

    document.addEventListener("click", function (e) {
      var isOpen = !menu.classList.contains("hidden");
      if (!isOpen) return;
      if (menu.contains(e.target) || toggle.contains(e.target)) return;
      setMenuOpen(menu, toggle, false);
    });

    document.addEventListener("keydown", function (e) {
      if (e.key !== "Escape") return;
      var isOpen = !menu.classList.contains("hidden");
      if (isOpen) setMenuOpen(menu, toggle, false);
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    init();
    initPageActionsMenu();
  });
})();
