# Contexto del Proyecto

## Descripción
**Distribuidor de Suplementos (DS)** — e-commerce de suplementos deportivos. Catálogo público,
carrito y checkout por **WhatsApp** (el pedido también se registra en la BD), cuentas de cliente,
y un **panel de administración** para gestionar productos, categorías y pedidos.

## Stack Tecnológico
- **Frontend:** HTML estático + **Tailwind CSS compilado y versionado** (storefront: `app.css` vía `tailwind.config.js`; panel admin: `admin.css` vía `tailwind.admin.config.js` — ninguno de los dos usa el CDN de Tailwind hoy) + **JavaScript vanilla** (IIFE, sin framework, sin build en el servidor). Fuentes Barlow Condensed (display) + Barlow (body); iconos Material Symbols.
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
  ya compilado (coherente con "sin build en el server"). El panel admin se migró después con el mismo
  patrón (`site/assets/css/admin.css`, `tailwind.admin.config.js`, entrada `admin.input.css`) — hoy
  ninguna de las dos superficies usa el CDN de Tailwind.
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
- **2026-08-10:** **Usuarios del panel con 3 roles**, para que el dueño le dé acceso a sus empleados
  sin compartir su propia cuenta. Guía completa en `docs/roles-y-permisos.md`. Antes, la tabla
  `admins` no tenía columna de rol — cualquier cuenta que existiera era "dueño" con control total, y
  la única forma de crear una era por CLI. Decisiones del usuario, ya implementadas:
  1. **3 modos jerárquicos**: `dueno` > `operador` > `lectura`. Dueño = todo, incluida la gestión de
     usuarios y el respaldo completo (tiene los datos de todos los clientes). Operador = catálogo y
     pedidos del día a día, incluida la carga masiva de CSV completa (con reemplazo). Solo lectura =
     consulta, no puede cambiar nada.
  2. **Baja de empleado = desactivar** (`admins.activo`), no borrar — conserva su historial de
     auditoría. Borrar de verdad queda como acción secundaria para cuentas creadas por error.
  Arquitectura: `ds_require_rol()` en `site/api/lib/AdminSession.php` es el único guardián con rol —
  reusa la caché de admin de `ds_require_admin()` (una sola consulta por petición), y hereda el 2FA
  obligatorio de la auditoría de ayer. Invariantes duras verificadas en servidor (no solo en la UI):
  nadie cambia su propio rol, nadie se desactiva/borra a sí mismo, siempre debe quedar al menos un
  dueño activo (esta última con `SELECT ... FOR UPDATE` en transacción — un primer intento sin lock
  tenía la misma condición de carrera que ya se había cerrado un día antes en `RateLimit.php`; se
  corrigió antes de dar la función por terminada). Se sumó también `auth/change-password.php`, que
  antes no existía — sin él, la contraseña inicial que el dueño le da a un empleado sería la única
  que ese empleado podría usar para siempre. `scripts/scan-seguridad.sh` ahora exige que todo
  endpoint de negocio nuevo (fuera de `admin/auth/`) declare su rol explícitamente con
  `ds_require_rol()`, no solo `ds_require_admin()` a secas.
- **2026-08-11:** **Nota de compra en PDF, exportar histórico de pedidos a Excel, y panel admin
  usable en celular.** Decisión clave: **sin dependencias nuevas**, coherente con "sin Composer" —
  - La nota de compra (`site/admin/pedido-pdf.html`) es una vista imprimible standalone que usa
    `window.print()` del navegador ("Guardar como PDF"), no ninguna librería PDF. Consume el
    endpoint nuevo `orders/get.php` (detalle de 1 pedido, rol lectura) y `settings/get.php` ya
    existente para los datos de contacto del negocio.
  - El "Excel" es CSV con BOM UTF-8 (`orders/export.php`, rol dueño — mismo criterio que
    `backup/export.php`: es un volcado masivo de datos personales de clientes), mismo patrón ya
    usado por `products/export.php`. No hay ninguna librería de spreadsheets instalada ni se instaló.
  - El sidebar admin (duplicado en las 9 páginas, sin templating) pasó de fijo/sin responsive a
    off-canvas por debajo de `md:` (768px): oculto por defecto, botón hamburguesa, overlay,
    `Escape` para cerrar — lógica compartida en `mobile-nav.js`. Las tablas que recortaban
    contenido con `overflow-hidden` pasan a `overflow-x-auto` (mismo patrón que ya tenía Pedidos).
    Decisión del usuario: scroll horizontal, no tarjetas apiladas.
  - Se adoptó Material Symbols (ya usado en el storefront) para íconos en los botones del admin —
    el CSP ya permitía `fonts.googleapis.com`/`fonts.gstatic.com`, no hubo que tocarlo. La regla
    `.material-symbols-outlined` vive centralizada en `admin.input.css`, no repetida por página.
- **2026-08-11:** **Preparación para el primer despliegue real en Hostinger.** Guía completa en
  `docs/despliegue-hostinger.md`. Dos auditorías (despliegue y seguridad de datos/contraseñas)
  encontraron que el hasheo de contraseñas ya era correcto en el 100% de los puntos del código
  (bcrypt vía `password_hash`/`password_verify`, cero texto plano), pero identificaron 5 cosas
  que sí había que arreglar antes de exponer el sitio:
  1. **Un cliente que olvidaba su contraseña la perdía para siempre** (correo desactivado + sin
     forma de cambiarla estando logueado). Se agregó `site/api/auth/change-password.php` y una
     sección nueva en `cuenta.html`, espejo exacto del ya existente para admins.
  2. **La cookie de sesión del panel admin se emitía sin `Secure` detrás de un proxy** (Cloudflare) —
     la corrección ya existía para la cookie de cliente y no se había replicado a
     `AdminSession.php`.
  3. **Política de contraseñas**: nueva `ds_validate_password()` centralizada en `Validate.php`
     (mínimo por `mb_strlen`, no `strlen` — antes una contraseña corta con acentos pasaba el
     filtro de 8; máximo 72 bytes explícito por el truncado silencioso de bcrypt; rechazo contra
     ~200 contraseñas comunes). Reemplaza las 6 validaciones sueltas que había en el proyecto.
     También se agregó `password_needs_rehash()` en ambos logins (sin tocar `password_changed_at`,
     para no cerrar la sesión que se acaba de abrir).
  4. **`schema.sql` quedaba incompleto para una instalación nueva**: le faltaban el índice único
     de SKU y las 12 filas de contenido inicial de `settings` (la página Nosotros salía en blanco).
     Ambos ya viven en `schema.sql`, no solo en sus migraciones de origen.
  5. **Con el catálogo vacío, la tienda mostraba 24 productos demo con precios inventados**
     (`catalog-engine.js`) — ahora una API que responde vacía muestra un estado honesto
     ("Estamos preparando el catálogo"); el demo solo aparece si la API falla de verdad.
  Quedó documentado como pendiente, a propósito, para una sesión futura (no bloquea el
  lanzamiento): el secreto TOTP de los admins se guarda en claro en la BD, no hay aviso de
  privacidad ni borrado de cuenta (LFPDPPP), y `admin_audit_log` sigue sin pantalla para leerlo.

## Requerimientos No Funcionales
- Seguridad: prepared statements (PDO), CSRF con tokens separados cliente/admin, rate limiting de
  logins (con `GET_LOCK` para que no se evada en paralelo), `password_hash` bcrypt, uploads validados
  (MIME real + `.htaccess` anti-ejecución), 2FA obligatorio en todo el panel admin (GET y POST, vía
  `ds_require_admin()`), roles de admin (`dueno`/`operador`/`lectura`, vía `ds_require_rol()`),
  `esc()`/`safeHref()` centralizados en `security-utils.js`, escáner propio
  (`scripts/scan-seguridad.sh`).
- Accesibilidad: contraste WCAG AA, touch targets ≥44px, foco visible, `prefers-reduced-motion`.
- Rendimiento: logos optimizados (<50 KB); Tailwind compilado en storefront y panel admin (ver
  Stack Tecnológico) — ninguna de las dos superficies depende del CDN de Tailwind en producción.

## Checklist de release (Hostinger)

**Guía completa, con pasos exactos: `docs/despliegue-hostinger.md`.** Lo de abajo es un resumen;
si hay alguna diferencia entre este resumen y esa guía, la guía manda (se actualiza con más
frecuencia).

1. Crear `site/api/config/env.php` real (gitignored) a partir de `env.example.php`, con las
   credenciales reales de la BD de Hostinger y `MAIL_TRANSPORT => 'mail'` (no `'none'`).
2. Importar `sql/schema.sql` y **las 17 migraciones de `sql/migrations/` en orden alfabético**
   (todas idempotentes) — `schema.sql` por sí solo no basta (ver la guía para el detalle de qué
   le faltaba y ya se corrigió: índice de SKU y contenido inicial de `settings`).
3. Crear el admin dueño — la guía cubre los dos caminos (con y sin SSH) sin que la contraseña
   quede escrita en ningún archivo.
4. Cargar el catálogo real por **el importador CSV del panel** (Productos → Importar CSV), no
   con `scripts/seed-products.php` — ese script usa `assets/data/productos-demo.json` (24
   productos de ejemplo) o, si le pasas `productos.json`, ese archivo solo trae
   nombre/marca/categoría/cantidad (sin precio, stock ni imagen), así que todo entraría a $0.00
   y marcado agotado.
5. El número de WhatsApp real (`5218344241599`) ya está puesto en el código y en `settings`;
   solo revisa que siga siendo el correcto si cambia en el futuro (está hardcodeado en 8
   archivos además de la tabla `settings` — no hay un solo lugar para actualizarlo).
6. Subir **solo el contenido de `site/`** a `public_html/` (no el repositorio completo); forzar
   HTTPS; verificar los `.htaccess`.
7. Confirmar que **todos** los admins tengan el 2FA activado antes de exponer el panel — desde
   la auditoría de seguridad de hoy, un admin sin 2FA solo puede ver la pantalla para enrolarlo,
   nada más (antes, con solo la contraseña se podía leer/exportar todo el panel).

## Convenciones del Proyecto
- Paleta: `ink`=texto/fondos oscuros, `brand`=texto/CTA azul, `lime`=acento (solo sobre oscuro o como fondo de botón con texto oscuro), `paper`=fondo claro.
- Logo: `logo-ds-clean.png` (blanco) sobre fondos oscuros; `logo-ds-blue.png` (azul) sobre tarjetas claras.
- No romper los ganchos `id`/`name`/`data-*` que el JS usa; no tocar `site/api/**` salvo necesidad.
