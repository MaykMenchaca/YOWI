(function () {
  "use strict";

  function initViewer(mount) {
    var reduce = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    var isSmall = window.innerWidth < 480;

    if (!window.THREE || reduce) return false;

    var THREE = window.THREE;
    var width = mount.clientWidth;
    var height = mount.clientHeight;

    var renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, isSmall ? 1.5 : 2));
    renderer.setSize(width, height);
    renderer.setClearColor(0x000000, 0);
    mount.appendChild(renderer.domElement);
    renderer.domElement.style.cursor = "grab";
    renderer.domElement.setAttribute("aria-hidden", "true");

    var scene = new THREE.Scene();
    var camera = new THREE.PerspectiveCamera(32, width / height, 0.1, 100);
    camera.position.set(0, 0.3, 6.2);

    scene.add(new THREE.AmbientLight(0xffffff, 0.55));
    var key = new THREE.DirectionalLight(0xffffff, 1.1);
    key.position.set(3, 4, 5);
    scene.add(key);
    var rim = new THREE.DirectionalLight(0x378add, 0.6);
    rim.position.set(-4, 1, -3);
    scene.add(rim);

    var group = new THREE.Group();
    scene.add(group);

    // Cuerpo del bote (cilindro)
    var bodyGeo = new THREE.CylinderGeometry(1.05, 1.15, 2.6, 48);
    var bodyMat = new THREE.MeshStandardMaterial({ color: 0xd4e3ff, roughness: 0.35, metalness: 0.05 });
    var body = new THREE.Mesh(bodyGeo, bodyMat);
    group.add(body);

    // Tapa
    var lidGeo = new THREE.CylinderGeometry(0.78, 0.82, 0.55, 48);
    var lidMat = new THREE.MeshStandardMaterial({ color: 0x185fa5, roughness: 0.4, metalness: 0.08 });
    var lid = new THREE.Mesh(lidGeo, lidMat);
    lid.position.y = 1.55;
    group.add(lid);

    // Etiqueta frontal (plano con texto en canvas 2D)
    var labelCanvas = document.createElement("canvas");
    labelCanvas.width = 512;
    labelCanvas.height = 512;
    var ctx = labelCanvas.getContext("2d");
    ctx.fillStyle = "#ffffff";
    ctx.fillRect(0, 0, 512, 512);
    ctx.strokeStyle = "#B5D4F4";
    ctx.lineWidth = 10;
    ctx.strokeRect(5, 5, 502, 502);
    ctx.fillStyle = "#185FA5";
    ctx.font = "700 120px Barlow Condensed, sans-serif";
    ctx.textAlign = "center";
    ctx.fillText("DS", 256, 260);
    ctx.fillStyle = "#378ADD";
    ctx.beginPath();
    ctx.moveTo(300, 300); ctx.lineTo(230, 380); ctx.lineTo(275, 380);
    ctx.lineTo(245, 440); ctx.lineTo(330, 350); ctx.lineTo(285, 350);
    ctx.closePath();
    ctx.fill();
    ctx.fillStyle = "#5B6472";
    ctx.font = "500 32px Barlow, sans-serif";
    ctx.fillText("PROTEÍNA", 256, 470);

    var labelTex = new THREE.CanvasTexture(labelCanvas);
    labelTex.colorSpace = THREE.SRGBColorSpace;
    var labelGeo = new THREE.CylinderGeometry(1.06, 1.16, 1.55, 48, 1, true, -0.85, 1.7);
    var labelMat = new THREE.MeshStandardMaterial({ map: labelTex, roughness: 0.5, side: THREE.DoubleSide });
    var label = new THREE.Mesh(labelGeo, labelMat);
    group.add(label);

    group.rotation.y = 0.4;

    // Interacción: arrastrar para rotar, auto-rotación suave cuando está quieto
    var dragging = false, lastX = 0, velocity = 0;

    function pointerDown(x) { dragging = true; lastX = x; renderer.domElement.style.cursor = "grabbing"; }
    function pointerMove(x) {
      if (!dragging) return;
      var dx = x - lastX;
      lastX = x;
      velocity = dx * 0.01;
      group.rotation.y += velocity;
    }
    function pointerUp() { dragging = false; renderer.domElement.style.cursor = "grab"; }

    renderer.domElement.addEventListener("mousedown", function (e) { pointerDown(e.clientX); });
    window.addEventListener("mousemove", function (e) { pointerMove(e.clientX); });
    window.addEventListener("mouseup", pointerUp);
    renderer.domElement.addEventListener("touchstart", function (e) { pointerDown(e.touches[0].clientX); }, { passive: true });
    renderer.domElement.addEventListener("touchmove", function (e) { pointerMove(e.touches[0].clientX); }, { passive: true });
    renderer.domElement.addEventListener("touchend", pointerUp);

    var raf;
    function animate() {
      raf = requestAnimationFrame(animate);
      if (!dragging) {
        velocity *= 0.94;
        group.rotation.y += velocity + 0.0035; // deriva de auto-rotación lenta
      }
      renderer.render(scene, camera);
    }
    animate();

    function onResize() {
      var w = mount.clientWidth, h = mount.clientHeight;
      camera.aspect = w / h;
      camera.updateProjectionMatrix();
      renderer.setSize(w, h);
    }
    window.addEventListener("resize", onResize);

    mount._cleanup = function () {
      cancelAnimationFrame(raf);
      window.removeEventListener("resize", onResize);
      renderer.dispose();
    };

    return true;
  }

  function boot() {
    var mount = document.querySelector("[data-product-3d]");
    if (!mount) return;
    var fallback = mount.querySelector("img");

    if (!("WebGLRenderingContext" in window)) return; // sin WebGL: se queda el <img>

    var script = document.createElement("script");
    script.src = "https://unpkg.com/three@0.160.0/build/three.min.js";
    script.onload = function () {
      var ok = initViewer(mount);
      if (ok && fallback) {
        fallback.style.display = "none";
        var hint = document.createElement("span");
        hint.textContent = "Arrastra para girar";
        hint.style.cssText = "position:absolute;bottom:8px;left:50%;transform:translateX(-50%);font-size:11px;color:#5B6472;background:#ffffffcc;padding:3px 10px;border-radius:999px;pointer-events:none;";
        mount.appendChild(hint);
      }
    };
    script.onerror = function () { /* CDN falló: se queda el <img> de siempre */ };
    document.head.appendChild(script);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
