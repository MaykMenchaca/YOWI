# Contexto del Proyecto

## Descripción
**Distribuidor de Suplementos (DS)** — e-commerce de suplementos deportivos. Catálogo público,
carrito y checkout por **WhatsApp** (el pedido también se registra en la BD), cuentas de cliente,
y un **panel de administración** para gestionar productos, categorías y pedidos.

## Stack Tecnológico
- **Frontend:** HTML estático + **Tailwind CSS por CDN** (config inline por página) + **JavaScript vanilla** (IIFE, sin framework, sin build). Fuentes Barlow Condensed (display) + Barlow (body); iconos Material Symbols (SVG).
- **Backend:** **PHP plano + PDO** (sin framework, sin Composer). API JSON (`{ok,data}` / `{ok,error}`).
- **Base de datos:** **MySQL / MariaDB**. Tablas: `users, admins, categories, products, orders, order_items, login_attempts`.
- **Servicios externos:** WhatsApp (wa.me) para el checkout. No hay pasarela de pago.

## Decisiones Arquitectónicas
- **2026-07:** Sin frameworks ni build — despliegue simple en shared hosting.
- **2026-07:** Catálogo migrado de JSON a MySQL; el precio del pedido se calcula server-side desde la BD.
- **2026-07:** Rediseño visual "gimnasio" (paleta ink `#0B0F1A` + lima `#8FD11F` + brand `#1F5FD9`).
- **2026-07-05:** **Despliegue = Hostinger (Apache + PHP + MySQL).** Se eliminó `vercel.json`: Vercel
  no ejecuta PHP nativamente, así que rompería el backend/login/catálogo. El JS usa rutas relativas
  (`api/...`) asumiendo el mismo host, coherente con LAMP.
- **2026-07-14:** **Tailwind compilado en las 9 páginas públicas.** Se reemplazó el CDN de Tailwind +
  la config inline por un CSS estático versionado (`site/assets/css/app.css`), generado con
  `npm run build:css` (config en `tailwind.config.js`, entrada `site/assets/css/tailwind.input.css`).
  Elimina el parpadeo y el runtime JIT en móvil. El build corre en local; el server solo sirve el CSS
  ya compilado (coherente con "sin build en el server"). **El panel admin sigue en CDN** (config
  distinta, herramienta interna) — pendiente migrar en un follow-up.
- **2026-07-14:** **Modo de despliegue "split" opcional (frontend en Vercel + API en Hostinger).**
  Vercel no ejecuta PHP, así que no puede alojar el backend. Se añadió una capa de config
  (`site/assets/js/config.js` → `DS_CONFIG.API_BASE`) para que el frontend apunte al backend PHP en
  otro dominio; CORS credenciado en `site/api/.htaccess` (se activa solo si el Origin coincide) y
  cookies `SameSite=None; Secure` en `Session.php` vía `SetEnv DS_CROSS_SITE 1`. **Default sigue siendo
  same-origin (todo en Hostinger), sin cambios.** Guía en `docs/deploy-vercel-split.md`. Caveat: las
  rutas con sesión (login/cuenta/admin) dependen de cookies de terceros que los navegadores restringen;
  el catálogo público y el checkout por WhatsApp sí funcionan cross-site.
- **2026-07-14:** **Fallback de catálogo demo estático.** Si `api/products/list.php` no responde o
  no hay backend (p. ej. deploy en Vercel sin PHP), `catalog-engine.js` carga
  `site/assets/data/productos-demo.json` (24 productos de ejemplo) y muestra un banner "Vista previa".
  Ese mismo archivo es el que consume `scripts/seed-products.php` para poblar la BD real. Con backend
  activo el fallback nunca se usa. **Precios del demo son de ejemplo, no reales.**
- **2026-07-14:** **Checkout con envío a domicilio.** El pedido ahora captura dirección completa
  (calle, colonia, CP, ciudad, estado, referencias), teléfono y notas, con validación inline
  (antes solo nombre + ciudad). El mensaje de WhatsApp se arma con todo y el pago es por transferencia
  (SPEI). Se agregó la columna `orders.direccion_envio` (migración en `sql/migrations/`) y el admin la
  muestra en el listado de pedidos.
- **2026-08-09:** **Auditoría del lado de usuario** (catálogo con sabores/galería, carrito, checkout,
  cuenta) — informe completo en `docs/auditoria-usuario-2026-08-09.md`. Halló que un anónimo podía
  vaciar el inventario completo con **una sola petición** (`orders/create.php` topaba la cantidad al
  stock disponible en vez de rechazar, y cancelar un pedido no reponía el stock). Se tomaron 4
  decisiones de producto, ya implementadas:
  1. **Cancelar un pedido repone el stock**, de forma idempotente (columna `orders.stock_repuesto`,
     migración `2026-08-09-add-orders-stock-repuesto.sql`). Requirió guardar el sabor exacto de cada
     línea (`order_items.sabor_id`, migración `2026-08-09-add-order-item-sabor-id.sql`) porque antes
     solo se guardaba el nombre como texto.
  2. **Ante stock insuficiente: ajustar e informar**, nunca rechazar todo el pedido en silencio. El
     servidor devuelve qué se ajustó (`ajustes`) y el front reconstruye el mensaje de WhatsApp desde
     la respuesta del servidor, no desde el carrito local.
  3. **Tope duro de 100 unidades por línea** (`DS_MAX_QTY_PER_LINE` en `create.php`), como red de
     seguridad adicional — no sustituye a la decisión 1, que es la que hace el daño recuperable.
  4. **Los pedidos de invitado no se vinculan** al registrarse después (YAGNI; el canal real de
     atención es WhatsApp).
  También se cerró un CORS placeholder activo en `site/api/.htaccess`, se añadió protección de doble
  clic en el checkout, reintento de CSRF caducado, `.htaccess` en `site/api/lib/`, CSP/HSTS en
  `vercel.json`, y rate limiting en 3 endpoints que no lo tenían. Detalle completo, hallazgos Medios/
  Bajos y lo confirmado como seguro (IDOR, CSRF, SQLi, XSS) en el informe.

## Requerimientos No Funcionales
- Seguridad: prepared statements (PDO), CSRF con tokens separados cliente/admin, rate limiting de logins, `password_hash` bcrypt, uploads validados (MIME real + `.htaccess` anti-ejecución).
- Accesibilidad: contraste WCAG AA, touch targets ≥44px, foco visible, `prefers-reduced-motion`.
- Rendimiento: logos optimizados (<50 KB); **Tailwind ya compilado** en las páginas públicas (`app.css`); falta migrar el panel admin (aún en CDN).

## Checklist de release (Hostinger)
1. Crear `site/api/config/env.php` real (gitignored) con las credenciales de la BD de Hostinger (host `localhost`, sin `DB_PORT`).
2. Importar `sql/schema.sql` vía phpMyAdmin. **En BD ya desplegada**, correr además la migración
   `sql/migrations/2026-07-14-add-direccion-envio.sql` (agrega `orders.direccion_envio`).
3. Crear admin con `php scripts/create-admin.php "Nombre" "correo" "password"`.
4. Cargar los 336 productos reales (`scripts/seed-products.php`) — hoy hay 12 demo.
5. Reemplazar el número de WhatsApp real (constante `WA_NUMBER` y footers).
6. Subir `site/` a `public_html/` por FTP; forzar HTTPS; verificar `fileinfo` activo y los `.htaccess`.

## Convenciones del Proyecto
- Paleta: `ink`=texto/fondos oscuros, `brand`=texto/CTA azul, `lime`=acento (solo sobre oscuro o como fondo de botón con texto oscuro), `paper`=fondo claro.
- Logo: `logo-ds-clean.png` (blanco) sobre fondos oscuros; `logo-ds-blue.png` (azul) sobre tarjetas claras.
- No romper los ganchos `id`/`name`/`data-*` que el JS usa; no tocar `site/api/**` salvo necesidad.
