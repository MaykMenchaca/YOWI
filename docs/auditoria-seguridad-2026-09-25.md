# Auditoría de seguridad antes de producción (2026-09-25)

Alcance: lo que cambió desde `auditoria-seguridad-2026-08-09.md`: login con Google,
gestión de administradores por HTTP, pedidos sin control de stock, borrado de cuentas y
carrusel. La red del entorno de revisión bloquea el dominio de producción, así que se
revisó el código (el mismo que corre en producción) y se probó en local con MariaDB,
`php -S` y Playwright.

## Corregido

### 1. Robo de cuenta preparado de antemano con Google (media)
`api/lib/GoogleAuth.php` (`ds_google_link_or_create`), usado por
`api/auth/google-callback.php`.

Registrarse con correo y contraseña no exige verificar el correo para entrar. Un
atacante registraba el correo de la víctima con su propia contraseña; cuando la víctima
entraba después con Google, su Google quedaba vinculado a esa cuenta y el atacante
seguía entrando con su contraseña (direcciones, teléfono, pedidos).

Ahora, al vincular Google a una cuenta con `email_verified = 0`, se borra la contraseña
(`password_hash = NULL`) y `password_changed_at = NOW()` cierra las sesiones abiertas.
Las cuentas con el correo ya verificado conservan su contraseña.

### 2. "Error del servidor" al entrar con contraseña en una cuenta de Google (bug)
`api/auth/login.php`. `password_verify($pass, NULL)` lanzaba `TypeError` por
`strict_types`, y la respuesta distinta delataba que el correo existía. Ahora responde el
mismo `Credenciales inválidas` 401, con el mismo tiempo (`ds_dummy_password_check`).

### 3. Las cuentas de Google no podían borrarse (bug + LFPDPPP)
`api/auth/delete-account.php` pedía una contraseña que esas cuentas no tienen. Ahora:
- `me.php` devuelve `tiene_password`, sin exponer el hash.
- Sin contraseña, el borrado se confirma escribiendo el correo de la cuenta.
- `change-password.php` explica "Tu cuenta entra con Google y no tiene contraseña".
- `cuenta.html` / `auth.js` ocultan "Cambiar contraseña" y piden el correo para borrar.

## Confirmado seguro

- **Gestión de admins** (`api/admin/admins/`): solo el rol dueño, CSRF, nadie cambia su
  propio rol ni se desactiva, siempre queda un dueño activo (con `FOR UPDATE`), y
  desactivar o cambiar la contraseña de un admin lo expulsa en su siguiente petición.
- **Pedidos** (`api/orders/create.php`): precio y nombre siempre de la BD, tope de 100
  por producto (sumando líneas repetidas) y de 50 líneas, aviso de privacidad exigido en
  el servidor, rate limit por IP.
- **Datos por cliente:** direcciones, favoritos y pedidos filtran siempre por el
  `user_id` de la sesión.
- **Google OAuth:** `state` de un solo uso con `hash_equals`, `aud`, `exp`,
  `email_verified`; sesión regenerada al entrar.
- **Servidor:** CORS apagado, CSP sin `unsafe-inline` en scripts, HSTS, anti-iframe,
  `nosniff`; `config/` y `lib/` bloqueados; cookies HttpOnly + Secure + SameSite; errores
  técnicos ocultos al visitante; sin archivos de respaldo, SQL o instalación en la
  carpeta pública.
- **XSS en el panel:** lo que se inserta en HTML sin `esc()` son solo contadores
  numéricos y etiquetas internas.

## Pendiente (impacto bajo hoy, sin tocar)

- Códigos de respaldo del 2FA de admin: 40 bits con SHA-256 (`lib/Recovery.php:24`).
  Solo son atacables con un volcado de la BD. Subir a 80 bits y `password_hash()`.
- Un código TOTP se puede reusar dentro de su ventana de ~90 s (sin anti-replay).
- `products/list.php` sin `LIMIT` ni techo de longitud de búsqueda (332 productos hoy).
- Los términos de las cuentas de Google solo se exigen en la pantalla de Mi cuenta, no
  en el servidor.
- `env.php` sigue dentro del docroot, protegido por `.htaccess` (`Require all denied`,
  que LiteSpeed respeta). Moverlo fuera elimina ese punto único de fallo.
