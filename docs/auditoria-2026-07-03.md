# Auditoría multi-agente — DS Sports Supplements
**Fecha:** 2026-07-03
**Método:** 3 agentes en paralelo (seguridad backend, frontend JS, UX/UI+color)
**Rúbricas:** `code-reviewer` (resultó ser plantilla vacía, se usó criterio manual),
`premium-web-design` + `theme-factory` (contenido real, sí aplicadas).

> Todas las cifras de contraste son ratios WCAG **medidos** en el navegador, no estimados.

---

## Hallazgos consolidados y priorizados

### 🔴 P0 — Bloquean despliegue (seguridad + funcionalidad core)

| # | Hallazgo | Archivo | Fix |
|---|----------|---------|-----|
| 1 | **Precio manipulable**: el total se recalcula server-side pero con el `precio_unitario` que manda el cliente, no el de la BD. Se puede comprar a $0.01. | `site/api/orders/create.php:36` | Leer `precio` y `nombre` reales con `SELECT ... FROM products WHERE id=? AND activo=1`, ignorar lo que manda el cliente. Validar stock. |
| 2 | **SQLi textual latente**: `$pdo->query("...WHERE id = $id")` interpola directo. Hoy neutralizado por cast a int, pero rompe el patrón. | `site/api/admin/products/update.php:42` | Prepared statement con `?` y `execute([$id])`. |
| 3 | **XSS almacenado en catálogo público**: `nombre/marca/imagen` desde MySQL se inyectan con `innerHTML` sin escapar. El admin sí escapa; el público no. | `site/assets/js/catalog-engine.js:23-43, 82-90` | Portar `esc()` (ya existe en `admin/products.js`) y envolver todos los campos. |
| 4 | **"Agregar al carrito" roto tras migración a MySQL**: API devuelve `id` INT, `cart.js` compara con `===` contra strings de `data-product-id`. `5 === "5"` es false → no agrega. | `site/assets/js/cart.js:34,50,55,190` | Normalizar con `String(a)===String(b)` en add/update/remove. |

### 🟠 P1 — Alto (seguridad hardening + accesibilidad medible)

| # | Hallazgo | Archivo | Fix |
|---|----------|---------|-----|
| 5 | Código muerto: 2 queries COUNT inútiles por request. | `site/api/admin/products/list.php:32-36` | Eliminar líneas 32-36. |
| 6 | Carpeta de uploads sin `.htaccess` anti-ejecución PHP. | `site/assets/img/productos/` | Crear `.htaccess` con `php_flag engine off`. Re-encodear imagen con GD. |
| 7 | Sin rate limiting en logins (cliente y admin) → fuerza bruta. | `auth/login.php`, `admin/auth/login.php` | Contador por IP+email en MySQL, bloqueo temporal tras N fallos. |
| 8 | **Hover WhatsApp aclara el botón**: base `#0F7A3A` = 5.43:1 ✓, pero hover `#20bd5a` = **2.47:1** ✗. | `producto.html:278`, `pedido.html:179` | Unificar hover a `#0B6E34` (más oscuro), como ya hace el JS. |
| 9 | **Placeholders de búsqueda ilegibles**: `#c2c6d2` sobre blanco = **1.71:1** ✗ (11 ocurrencias, 8 páginas). | `index, catalogo, producto, cuenta, marcas, registro, nosotros` | Cambiar a `text-outline` `#727782` = 4.49:1 ✓. |
| 10 | **KPIs del dashboard admin ilegibles**: `#185FA5` sobre slate-800 = **2.24:1** ✗, `#0F7A3A` = **2.69:1** ✗. | `admin/index.html:80-83` | Usar variantes claras en dark theme: `#378ADD`/`#5da9fe`, verde `#3de273`. |
| 11 | **Emoji `⭐` como icono** — viola regla del proyecto (SVG siempre). | `admin/products.js:95` | Reemplazar por SVG estrella (relleno/contorno). |

### 🟡 P2 — Medio (robustez + UX)

| # | Hallazgo | Archivo | Fix |
|---|----------|---------|-----|
| 12 | Falta `session.cookie_secure`. | `lib/Session.php`, `lib/AdminSession.php` | `ini_set('session.cookie_secure','1')` condicionado a HTTPS. |
| 13 | `ds_logout_user()` no es simétrico: destruye sesión incondicional (cerraría admin coexistente). | `lib/Session.php:39-44` | Espejar `ds_logout_admin`: unset + destroy solo si no hay admin. |
| 14 | **Sin navegación en móvil**: barra de categorías `hidden md:flex` sin hamburguesa. | todas las públicas (header) | Botón hamburguesa ≥44px + drawer. |
| 15 | `#378ADD` como texto de precio/badge no cumple (3.59:1 grande, peor en badge 10px). | `index.html:312`, badge carrito | Precio y badge → `#185FA5` (6.52:1). #378ADD solo decorativo. |
| 16 | Touch targets de botones hero < 44px (dependen solo de padding). | `index.html:237` y otros | `min-h-[44px]` explícito. |
| 17 | `NaN` en cantidades corrompe badge/totales; carrito sin saneo al cargar. | `cart.js:25,57,171` | Sanear `getCart()`: cantidad/precio a número válido, descartar líneas inválidas. |
| 18 | Selector de badge frágil (`a[href="pedido.html"] span.absolute`). | `cart.js:26` | Usar `[data-cart-badge]`. |
| 19 | `openModal` hace llamada `limit=1` desperdiciada + trae 9999 productos para editar 1. | `admin/products.js:145-158` | Eliminar llamada inútil; usar datos ya renderizados. |

### 🟢 P3 — Bajo (pulido)

| # | Hallazgo | Archivo | Fix |
|---|----------|---------|-----|
| 20 | Fetches sin `.catch` → pantallas en blanco sin feedback. | `catalog-engine.js`, `auth.js:66`, `cart.js:189` | `.catch` con estado de error visible. |
| 21 | `res.json()` asume siempre JSON → "Unexpected token <" si el servidor da HTML. | `api-client.js:29`, `admin/api-client.js:31` | try/catch en el parse, error genérico. |
| 22 | Modales admin sin foco/`Escape`/trap. | `admin/products.js`, `admin/categories.js` | focus al abrir, restaurar al cerrar, listener Escape. |
| 23 | `precio_original` no se valida contra `precio` (descuento negativo). | `admin/products/create.php`, `update.php` | Validar `precio_original > precio`. |
| 24 | Pedidos anónimos sin límite (spam potencial con P0#1). | `orders/create.php:58` | Confirmar si checkout invitado es deseado; si sí, rate limit. |
| 25 | Clichés de diseño AI: layout SaaS genérico, jerarquía tipográfica plana. | `index.html` | Hero dramático (`clamp()` 64-80px, tracking negativo), romper simetría del grid. |
| 26 | Duplicación api-client público/admin (aceptable bajo YAGNI). | `api-client.js` × 2 | Unificar solo si se tocan ambos otra vez. |

---

## Veredicto de la paleta cobalto (theme-factory)
Sólida y profesional, cercana a **Ocean Depths** / **Tech Innovation**. No hay choque azul/verde
(viven en roles distintos). El único problema es **disciplina de uso**: dos azules compitiendo sin
jerarquía. Regla a adoptar: **`#185FA5` para todo texto/CTA azul; `#378ADD` solo decoración/iconos.**
En dark theme (admin), invertir a variantes claras (`#5da9fe`, `#3de273`).

## Lo que está bien hecho
- Backend: prepared statements (salvo #2), CSRF timing-safe con tokens separados, `password_hash`,
  autorización consistente `ds_require_admin()`, sin IDOR, upload con validación MIME real robusta,
  secretos gitignored + `.htaccess`.
- Frontend: guard de sesión admin sólido, `esc()` consistente en todo el JS admin, sin `console.log`
  olvidados, total recalculado server-side.
- UX: bug histórico WhatsApp 1.98:1 **corregido** (base 5.43:1), iconos SVG, foco visible,
  `prefers-reduced-motion`, tokens semánticos, admin usable.

---

## Etapas de ejecución propuestas

- **Etapa 1 — P0 (bloquean despliegue):** #1 precio BD, #2 SQLi, #3 XSS catálogo, #4 carrito roto.
- **Etapa 2 — P1 seguridad:** #5 código muerto, #6 .htaccess uploads, #7 rate limiting.
- **Etapa 3 — P1 accesibilidad:** #8 hover WhatsApp, #9 placeholders, #10 KPIs admin, #11 emoji.
- **Etapa 4 — P2:** #12-19 (cookie_secure, logout, nav móvil, roles de color, touch targets, saneo, etc.).
- **Etapa 5 — P3 pulido:** #20-26 (catch, modales, hero dramático, validaciones finas).
