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

## Requerimientos No Funcionales
- Seguridad: prepared statements (PDO), CSRF con tokens separados cliente/admin, rate limiting de logins, `password_hash` bcrypt, uploads validados (MIME real + `.htaccess` anti-ejecución).
- Accesibilidad: contraste WCAG AA, touch targets ≥44px, foco visible, `prefers-reduced-motion`.
- Rendimiento: logos optimizados (<50 KB); Tailwind CDN es deuda técnica aceptada (idealmente compilar para producción).

## Checklist de release (Hostinger)
1. Crear `site/api/config/env.php` real (gitignored) con las credenciales de la BD de Hostinger (host `localhost`, sin `DB_PORT`).
2. Importar `sql/schema.sql` vía phpMyAdmin.
3. Crear admin con `php scripts/create-admin.php "Nombre" "correo" "password"`.
4. Cargar los 336 productos reales (`scripts/seed-products.php`) — hoy hay 12 demo.
5. Reemplazar el número de WhatsApp real (constante `WA_NUMBER` y footers).
6. Subir `site/` a `public_html/` por FTP; forzar HTTPS; verificar `fileinfo` activo y los `.htaccess`.

## Convenciones del Proyecto
- Paleta: `ink`=texto/fondos oscuros, `brand`=texto/CTA azul, `lime`=acento (solo sobre oscuro o como fondo de botón con texto oscuro), `paper`=fondo claro.
- Logo: `logo-ds-clean.png` (blanco) sobre fondos oscuros; `logo-ds-blue.png` (azul) sobre tarjetas claras.
- No romper los ganchos `id`/`name`/`data-*` que el JS usa; no tocar `site/api/**` salvo necesidad.
