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
- **2026-08-09 (misma noche):** **Auditoría de seguridad — panel admin + revisión de clases de ataque
  modernas.** Informe completo en `docs/auditoria-seguridad-2026-08-09.md`. El hallazgo más grave: un
  admin **sin 2FA enrolado** (el estado por defecto de toda cuenta nueva) bastaba con su contraseña
  para leer/exportar toda la BD por GET y **enrolar su propio 2FA, secuestrando la cuenta de forma
  permanente** — el enforcement de 2FA vivía escondido dentro del chequeo de CSRF, que solo corre en
  POST. También se confirmó que el tope de 100/línea de esta misma mañana era evadible repitiendo la
  línea (5×30 = 150 del mismo producto). Cambios estructurales, ya implementados:
  1. **`ds_require_admin()` es ahora el único guardián** de los 41 endpoints admin — exige sesión y
     2FA activo por defecto, GET o POST. Solo 4 endpoints (enrolamiento/estado de 2FA) piden
     `ds_require_admin(true)` explícitamente para saltarse la exigencia de 2FA.
  2. **Re-pedir la contraseña al activar el 2FA** (`2fa-activate.php`), para que una sesión ya
     secuestrada por otra vía no pueda tomar el segundo factor sin conocerla.
  3. **`orders/create.php` agrega por `(producto_id, sabor_id)` antes de aplicar el tope**, y rechaza
     pedidos con más de 50 líneas — cierra la evasión del punto anterior.
  4. **`esc()`/`escAttr()`/`safeHref()` centralizados** en `site/assets/js/security-utils.js`
     (`window.DSSec`), cargado primero en las 20 páginas HTML. Antes eran 13 copias — al unificarlas
     se encontró y arregló un fallo real en `safeHref` (no filtraba caracteres de control antes de
     mirar el esquema de la URL; mismo endurecimiento aplicado en `ds_clean_url()` del servidor).
  5. **`.semgrep.yml` + `scripts/scan-seguridad.sh`** — escáner de seguridad con reglas propias del
     proyecto (funciones peligrosas, SQL concatenado, open redirect, `eval`/`new Function`), más dos
     comprobaciones estructurales (todo endpoint admin llama al guardián; todo POST admin valida
     CSRF). `pip install semgrep`, no toca el despliegue.
  6. **`docs/guia-seguridad.md`** — reglas de oro derivadas de los hallazgos reales de esta auditoría,
     con checklist de PR.
  También: rate limit en los 3 endpoints de 2FA que no lo tenían (fuerza bruta de TOTP sin límite),
  `RateLimit.php` con `GET_LOCK()` para que los límites no se evadan con peticiones en paralelo, los
  `.htaccess` anti-ejecución de `assets/img/{banners,brands}/` ahora viajan en git (antes el
  `.gitignore` los excluía junto con las imágenes), y varios quick wins (CSV injection en el export
  de productos, bomba de descompresión de imagen, `.vercelignore`, `Cache-Control: no-store`).
  Recomendaciones Medias/Bajas no implementadas (política de contraseñas, `totp_secret` en claro,
  aviso de privacidad ausente — la más urgente del lote, ver LFPDPPP en el informe) documentadas ahí
  mismo para una futura sesión.

## Requerimientos No Funcionales
- Seguridad: prepared statements (PDO), CSRF con tokens separados cliente/admin, rate limiting de
  logins (con `GET_LOCK` para que no se evada en paralelo), `password_hash` bcrypt, uploads validados
  (MIME real + `.htaccess` anti-ejecución), 2FA obligatorio en todo el panel admin (GET y POST, vía
  `ds_require_admin()`), `esc()`/`safeHref()` centralizados en `security-utils.js`, escáner propio
  (`scripts/scan-seguridad.sh`).
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
7. Configurar `MAIL_TRANSPORT` en `env.php` (no dejarlo en `none`): con el correo desactivado,
   "olvidé mi contraseña" no envía nada y el cliente queda bloqueado sin ninguna forma real de
   recuperar su cuenta (el mensaje que ve es genérico a propósito, así que no se nota solo).
8. Confirmar que **todos** los admins tengan el 2FA activado antes de exponer el panel — desde
   la auditoría de seguridad de hoy, un admin sin 2FA solo puede ver la pantalla para enrolarlo,
   nada más (antes, con solo la contraseña se podía leer/exportar todo el panel).

## Convenciones del Proyecto
- Paleta: `ink`=texto/fondos oscuros, `brand`=texto/CTA azul, `lime`=acento (solo sobre oscuro o como fondo de botón con texto oscuro), `paper`=fondo claro.
- Logo: `logo-ds-clean.png` (blanco) sobre fondos oscuros; `logo-ds-blue.png` (azul) sobre tarjetas claras.
- No romper los ganchos `id`/`name`/`data-*` que el JS usa; no tocar `site/api/**` salvo necesidad.
