# Spec de arreglos — Revisión de código YOWI (2026-08-02)

Revisor: Agente Revisor de Código. Alcance: verificar 9 hallazgos contra el código real,
descartar falsos positivos y definir el arreglo concreto para que el **Constructor** lo
implemente. Rutas siempre absolutas desde la raíz del repo `/home/user/YOWI`.

Resumen de estados:

| ID | Sev | Estado | Núcleo del arreglo |
|----|-----|--------|--------------------|
| C1 + C3 | ALTO | CONFIRMADO | Modelo de stock NULL=ilimitado / 0=agotado + descuento transaccional |
| S1 | MEDIO | CONFIRMADO | Enforzar `totp_enabled=1` en servidor en escritura admin |
| S2 | MEDIO | CONFIRMADO (matizado) | Cabecera IP confiable OPT-IN por env |
| C2 | MEDIO | CONFIRMADO | Gate del botón por CSRF listo + no abrir WhatsApp si el POST falla |
| S3 | BAJO | CONFIRMADO | Rechazar subida si el re-encode falla (3 endpoints) |
| S4 | BAJO | CONFIRMADO | `users.password_changed_at` + invalidar sesiones previas |
| S5 | BAJO | CONFIRMADO (grande) | Auto-hospedar Tailwind admin + externalizar inline + CSP `script-src 'self'` |
| S6 | BAJO | CONFIRMADO | SameSite=None+Secure cuando `DS_CROSS_SITE` en AdminSession |

---

## [C1 + C3] ALTO — Modelo de stock inconsistente (filtro oculta todo) + sobreventa

- Estado: **CONFIRMADO** (los dos son la misma raíz: la columna `stock` no distingue
  "sin control" de "agotado", y nadie descuenta inventario).
- Ubicación:
  - Columna: `/home/user/YOWI/sql/schema.sql:64` → `stock SMALLINT UNSIGNED NOT NULL DEFAULT 0`.
  - Filtro que oculta: `/home/user/YOWI/site/assets/js/catalog-engine.js:105`
    → `if (filtros.soloDisponibles && p.stock <= 0) return false;`
  - Import deja 0 por defecto: `/home/user/YOWI/site/api/admin/products/import.php:196-197`
    → `$stock = $stockNum !== null && $stockNum > 0 ? (int) $stockNum : 0;`
  - Checkout trata 0 como ilimitado y NO descuenta:
    `/home/user/YOWI/site/api/orders/create.php:41` (SELECT sin FOR UPDATE),
    `:56-60` (solo topa si `stock > 0`), y `:80-112` (transacción que inserta pero
    nunca hace UPDATE de stock).
  - Lectura pública: `/home/user/YOWI/site/api/products/list.php:54` → `(int) $r['stock']`.
  - Admin create/update: `/home/user/YOWI/site/api/admin/products/create.php:25` y
    `/home/user/YOWI/site/api/admin/products/update.php:26` → `ds_to_positive_int($body['stock'] ?? 0)`
    (colapsa vacío y 0 al mismo valor 0).
  - Admin list/UI: `/home/user/YOWI/site/api/admin/products/list.php:61` (`(int)`),
    `/home/user/YOWI/site/assets/js/admin/products.js:126` (badge `p.stock > 0 ? verde : rojo`),
    `:201` (rellena el form), `:258` (`parseInt(...) || 0`),
    `/home/user/YOWI/site/admin/productos.html:157` (`value="0"`).
  - Favoritos: `/home/user/YOWI/site/api/favorites/list.php:33` → `(int) $p['stock']`.

- Escenario: hoy `stock` nace en 0 (default de columna + import de columna vacía). El
  catálogo trata `stock <= 0` como agotado y el filtro "Solo disponibles" oculta casi
  todo. A la vez, checkout interpreta 0 como "ilimitado" (solo topa cuando `stock>0`) y
  **nunca descuenta**, así que dos clientes pueden comprar la misma última unidad
  (sobreventa). Los dos síntomas nacen del doble significado de `0`.

- Modelo aprobado por el usuario:
  - `stock IS NULL` = sin control de inventario → **siempre visible, ilimitado, no se descuenta**.
  - `stock = número` = inventario real. `0` = **agotado** (badge + oculto por "Solo disponibles").
  - `stock > 0` = disponible, se **topa** la cantidad al disponible y se **descuenta** en la transacción.

- Arreglo:

  1. **Migración** (una sola vez — ver caveat) →
     `/home/user/YOWI/sql/migrations/2026-08-02-stock-nullable.sql`:
     ```sql
     -- Hacer stock NULL-able (NULL = sin control / ilimitado).
     ALTER TABLE products MODIFY COLUMN stock SMALLINT UNSIGNED NULL DEFAULT NULL;
     -- Migrar los 0 existentes (que hoy significan "ilimitado") a NULL.
     -- ⚠ ONE-SHOT: correr solo una vez. Tras esto, un 0 real = agotado y NO debe re-migrarse.
     UPDATE products SET stock = NULL WHERE stock = 0;
     ```
     `ALTER ... MODIFY` es idempotente; el `UPDATE` **no** lo es (re-correrlo borraría
     "agotados" legítimos). El Constructor debe marcarlo como migración de datos de un
     solo uso en el registro de migraciones.

  2. **`sql/schema.sql:64`** → cambiar a
     `stock SMALLINT UNSIGNED NULL DEFAULT NULL,` (para instalaciones nuevas).

  3. **Import** `/home/user/YOWI/site/api/admin/products/import.php:196-197`:
     ```php
     $stockNum = $toNumber($get($row, $cols, 'stock')); // '' -> null; '0' -> 0.0
     $stock    = $stockNum === null ? null : max(0, (int) $stockNum);
     ```
     `$toNumber` (mismo archivo, líneas 37-46) ya devuelve `null` para columna vacía y
     `0.0` para `"0"`, así que columna vacía → NULL (sin control) y `0` explícito → agotado.
     Los tres binds de `$stock` (líneas 211, 213, 218) quedan igual (aceptan null).

  4. **Admin create/update** `create.php:25` y `update.php:26`:
     ```php
     $stock = (isset($body['stock']) && $body['stock'] !== '' && $body['stock'] !== null)
         ? max(0, (int) $body['stock'])
         : null;
     ```
     Los `execute([... $stock ...])` (create.php:48, update.php:58) ya pasan el valor tal cual.

  5. **Admin UI** `/home/user/YOWI/site/assets/js/admin/products.js`:
     - `:258` (payload): enviar `null` cuando el campo está vacío:
       ```js
       stock: (function () { var v = form.querySelector('[name="stock"]').value.trim();
                             return v === '' ? null : Math.max(0, parseInt(v, 10) || 0); })(),
       ```
     - `:201` (rellenar form al editar): `... = (p.stock === null || p.stock === undefined) ? '' : p.stock;`
     - `:126` (badge): tres estados →
       ```js
       (p.stock === null
         ? '<span class="px-2 py-0.5 rounded text-xs bg-slate-700 text-slate-300">Sin control</span>'
         : '<span class="px-2 py-0.5 rounded text-xs ' + (p.stock > 0 ? 'bg-green-900/50 text-green-300' : 'bg-red-900/50 text-red-300') + '">' + (p.stock > 0 ? p.stock : 'Agotado') + '</span>')
       ```
     - `/home/user/YOWI/site/admin/productos.html:157`: quitar `value="0"` (dejar vacío
       = sin control) y añadir un hint tipo "Vacío = sin control de inventario".

  6. **Lectura pública** `/home/user/YOWI/site/api/products/list.php:54`,
     `/home/user/YOWI/site/api/admin/products/list.php:61` y
     `/home/user/YOWI/site/api/favorites/list.php:33`:
     ```php
     'stock' => $r['stock'] !== null ? (int) $r['stock'] : null,
     ```

  7. **Catálogo (front)** `/home/user/YOWI/site/assets/js/catalog-engine.js`:
     - `:105` (filtro): `if (filtros.soloDisponibles && p.stock != null && p.stock <= 0) return false;`
       (NULL/undefined = ilimitado = siempre pasa; 0 = agotado = se oculta).
     - `productCardHTML` (`:67-90`): cuando `p.stock === 0`, mostrar badge "Agotado" y
       deshabilitar el botón "Agregar al carrito" (`disabled` + estilo apagado). NULL o
       >0 se renderiza normal. (Añadir un `<span>` de badge sobre la imagen y cambiar el
       `<button ...add-to-cart-btn>` por `disabled` cuando `p.stock === 0`.)

  8. **Checkout con descuento transaccional** `/home/user/YOWI/site/api/orders/create.php`:
     - Mover la lectura de producto a DENTRO de la transacción y bloquear filas:
       cambiar el `SELECT ... FROM products WHERE id = ? AND activo = 1` (`:41`) por uno
       con `FOR UPDATE`, ejecutado después de `beginTransaction()`.
     - Lógica por item:
       ```php
       $stock = $producto['stock']; // null | int
       if ($stock !== null) {
           $stock = (int) $stock;
           if ($stock <= 0) { continue; } // agotado: se descarta el item
           if ($cantidad > $stock) { $cantidad = $stock; } // topa al disponible
       }
       // ... calcular subtotal, acumular ...
       ```
     - Tras insertar cada `order_item`, si `$stock !== null` descontar con guardia:
       ```php
       $dec = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');
       $dec->execute([$cantidad, $productoId, $cantidad]);
       if ($dec->rowCount() === 0) { throw new RuntimeException('stock insuficiente'); } // rollBack
       ```
     - Reestructurar para que el `SELECT ... FOR UPDATE` + cálculo + inserción + descuento
       vivan en el mismo `try` de la transacción (hoy el cálculo está fuera, líneas 39-72,
       y la transacción empieza en `:80`). Al mover el lock dentro se evita la sobreventa
       real bajo concurrencia.

- Verificación:
  - `mysql> ALTER ...; SELECT id, stock FROM products;` → los productos importados con 0
    aparecen como NULL.
  - Producto con `stock = NULL`: aparece siempre, sin tope, y su stock no cambia tras un pedido.
  - Producto con `stock = 2`: `curl -X POST orders/create.php` pidiendo cantidad 5 →
    el pedido registra 2, y `SELECT stock` baja a 0. Un segundo pedido de ese producto se descarta.
  - Producto con `stock = 0`: en el catálogo sale badge "Agotado", botón deshabilitado, y
    el filtro "Solo disponibles" lo oculta.
  - Dos pedidos concurrentes del último ítem: solo uno descuenta; el otro cae (rollBack) → sin negativos.

---

## [S1] MEDIO — 2FA obligatorio no se aplica en el servidor

- Estado: **CONFIRMADO**.
- Ubicación:
  - `me.php` solo informa: `/home/user/YOWI/site/api/admin/auth/me.php:31` (`needs_2fa`).
  - Guardia actual sin chequeo de 2FA: `/home/user/YOWI/site/api/lib/AdminSession.php:47-52`
    (`ds_require_admin` solo verifica `admin_id`).
  - Punto único por el que pasan TODAS las escrituras admin:
    `/home/user/YOWI/site/api/lib/AdminSession.php:81-94` (`ds_admin_csrf_check`).

- Escenario: un admin autenticado con `totp_enabled = 0` puede ejecutar cualquier
  endpoint de escritura (crear/editar/borrar productos, marcas, banners, settings,
  pedidos) porque nada valida el segundo factor en el servidor; el "obligatorio" vive
  solo en el front (`needs_2fa`), que es esquivable llamando la API directo.

- Verificación de qué endpoints pasan por `ds_admin_csrf_check` (28 archivos):
  todos los POST de `admin/products`, `admin/brands`, `admin/banners`,
  `admin/categories`, `admin/settings`, `admin/orders/update-status`, más los de
  `admin/auth`: `login`, `logout`, `2fa-setup`, `2fa-activate`, `2fa-disable`, `2fa-recovery`.
  **No** pasan por ahí (son GET, sin CSRF): `auth/me.php` y `auth/2fa-status.php`.

- Arreglo: enforzar dentro de `ds_admin_csrf_check` (todos los POST admin ya lo llaman),
  con **lista blanca por basename** para el enrolamiento. Editar
  `/home/user/YOWI/site/api/lib/AdminSession.php`:

  ```php
  // Endpoints exentos del enforcement de 2FA (enrolamiento / auth). Se comparan por
  // basename de SCRIPT_NAME. login/logout no tienen admin_id útil en este punto; setup/
  // activate/recovery deben poder usarse mientras totp_enabled sigue en 0.
  const DS_2FA_EXEMPT = [
      'login.php', 'logout.php',
      '2fa-setup.php', '2fa-activate.php', '2fa-recovery.php',
  ];

  function ds_admin_require_2fa_enrolled(): void
  {
      $base = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
      if (in_array($base, DS_2FA_EXEMPT, true)) return;
      $adminId = $_SESSION['admin_id'] ?? null;
      if (empty($adminId) || !function_exists('ds_get_pdo')) return; // login flow lo maneja aparte
      $stmt = ds_get_pdo()->prepare('SELECT totp_enabled FROM admins WHERE id = ?');
      $stmt->execute([(int) $adminId]);
      if ((int) $stmt->fetchColumn() !== 1) {
          ds_json_error('Debes activar el 2FA antes de operar el panel.', 403);
      }
  }
  ```
  Llamarla al final de `ds_admin_csrf_check` (después del `hash_equals`, junto al
  `ds_admin_log`, líneas 88-93). `2fa-disable.php` **no** se exenta a propósito: solo
  tiene sentido con 2FA ya activo, así que enforzarlo es correcto (además ya valida
  `totp_enabled=1` internamente en su línea 24).

- Confirmación pedida: `2fa-activate.php` (`/home/user/YOWI/site/api/admin/auth/2fa-activate.php`)
  se puede usar SIN 2FA aún activo → **sí**, queda en la lista blanca, y su flujo espera
  `totp_enabled=0` (activa con `UPDATE ... totp_enabled=1` en `:32`). `login.php`
  llama `ds_admin_csrf_check` en `:19` cuando todavía no hay `admin_id` en sesión, por lo
  que la guardia de `admin_id` vacío lo deja pasar aunque también está en la lista blanca.

- Verificación:
  - Admin con `totp_enabled=0`: `curl` a `admin/products/create.php` con CSRF válido → **403**
    "Debes activar el 2FA...". El mismo admin puede llamar `2fa-setup.php` y `2fa-activate.php`.
  - Tras activar (`totp_enabled=1`): el mismo `create.php` responde 201.
  - `login.php` y `logout.php` siguen funcionando en ambos estados.

---

## [S2] MEDIO — `ds_client_ip()` solo usa REMOTE_ADDR (colapso tras proxy/CDN)

- Estado: **CONFIRMADO** (matizado: hoy es la opción *segura* por defecto; el arreglo
  añade un opt-in explícito, sin debilitar el default).
- Ubicación: `/home/user/YOWI/site/api/lib/RateLimit.php:18-22`. Consumidores:
  `/home/user/YOWI/site/api/admin/auth/login.php:28`, `.../auth/login.php` cliente
  (grep `ds_client_ip`), y `/home/user/YOWI/site/api/orders/create.php:19`.

- Escenario: en Hostinger detrás de Cloudflare u otro proxy, `REMOTE_ADDR` es la IP del
  proxy, no del cliente. Todos los usuarios comparten una misma IP → los límites por IP
  (`DS_LOGIN_IP_MAX_FAILS`, rate-limit de pedidos) se disparan para todos (falsos 429) o
  se vuelven inútiles. Confiar en `X-Forwarded-For` sin más sería peor (falsificable).

- Arreglo (opt-in por env, patrón de `ds_mail_config` en
  `/home/user/YOWI/site/api/lib/Mailer.php:14-27`). Editar
  `/home/user/YOWI/site/api/lib/RateLimit.php`:
  ```php
  function ds_client_ip(): string
  {
      static $trusted = null;
      if ($trusted === null) {
          $envPath = __DIR__ . '/../config/env.php';
          $env = file_exists($envPath) ? require $envPath : [];
          $trusted = trim((string) ($env['TRUSTED_IP_HEADER'] ?? '')); // '' = desactivado
      }
      if ($trusted !== '') {
          // 'CF-Connecting-IP' -> $_SERVER['HTTP_CF_CONNECTING_IP']
          $key = 'HTTP_' . strtoupper(str_replace('-', '_', $trusted));
          $val = trim((string) ($_SERVER[$key] ?? ''));
          // X-Forwarded-For puede traer lista "cliente, proxy1, ..."; tomar el primero.
          if ($val !== '' && strpos($val, ',') !== false) {
              $val = trim(explode(',', $val)[0]);
          }
          if ($val !== '' && filter_var($val, FILTER_VALIDATE_IP)) {
              return substr($val, 0, 45);
          }
      }
      return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
  }
  ```
  Añadir a `/home/user/YOWI/site/api/config/env.example.php` (y documentar en env.php):
  `'TRUSTED_IP_HEADER' => '', // p.ej. 'CF-Connecting-IP' si el sitio está tras Cloudflare`.
  Regla: **si no está configurada, sigue REMOTE_ADDR** (comportamiento actual). Nunca se
  confía en la cabecera sin la config explícita del operador.

- Nota (opcional, misma raíz): `ds_admin_log` usa `REMOTE_ADDR` directo
  (`AdminSession.php:107`). El Constructor puede reusar `ds_client_ip()` allí para
  auditar la IP real, pero requiere incluir `RateLimit.php` en ese contexto; queda fuera
  del alcance mínimo de S2.

- Verificación:
  - Sin `TRUSTED_IP_HEADER`: `curl -H 'CF-Connecting-IP: 1.2.3.4'` → el límite sigue
    contando por `REMOTE_ADDR` (la cabecera se ignora).
  - Con `'TRUSTED_IP_HEADER' => 'CF-Connecting-IP'`: dos requests con IPs distintas en esa
    cabecera cuentan como IPs distintas; una cabecera basura ("no-ip") cae a REMOTE_ADDR.

---

## [C2] MEDIO — Condición de carrera del CSRF al enviar el pedido

- Estado: **CONFIRMADO**.
- Ubicación: `/home/user/YOWI/site/assets/js/cart.js`:
  - Token fire-and-forget: `:275-280` (fetch a `auth/me.php` dentro de `DOMContentLoaded`,
    setea el CSRF cuando resuelve).
  - `submitOrder`: `:224-267`. Abre WhatsApp **antes** del POST (`:242`), luego hace el
    POST (`:244-266`) cuyo `.catch` solo hace `console.warn` (`:265`).
  - Botón: `:322-333` (listener de `submit-order-btn`); `syncCheckoutState` `:216-222`.
  - Cliente API sin token → no manda `csrf_token` (`/home/user/YOWI/site/assets/js/api-client.js:17`),
    y el servidor responde 403 (`orders/create.php` vía `ds_csrf_check`).

- Escenario: si el usuario pulsa "Enviar" antes de que resuelva `me.php`, el POST sale
  sin `csrf_token` → **403 silencioso** (solo `console.warn`), el pedido no se guarda en
  BD, pero WhatsApp **sí** se abrió (`window.open` va primero). El cliente cree que pidió;
  el negocio no tiene registro.

- Arreglo (dos partes, ambas en `cart.js`):
  1. **Gate del botón hasta que el CSRF esté listo**: introducir un flag de módulo
     `var csrfReady = false;`. En el `.then` del fetch de `me.php` (`:278`), tras
     `setCsrfToken`, poner `csrfReady = true; syncCheckoutState();`. Si no hay `DSApi` o
     el fetch falla, igualmente marcar `csrfReady = true` (degradado: el POST se intentará
     y, si falla, la parte 2 muestra el error — no se debe dejar el botón muerto para siempre).
     Ajustar `syncCheckoutState` (`:216-222`):
     `if (btn) btn.disabled = !has || !csrfReady;` y opcionalmente el label a
     "Preparando…" mientras `!csrfReady`.
  2. **No abrir WhatsApp si el POST falla**: reordenar `submitOrder` para POSTear primero
     y abrir WhatsApp solo en éxito. Para no perder el gesto del usuario (los pop-ups
     bloquean `window.open` en callbacks async), abrir una ventana en blanco de inmediato
     dentro del click y luego redirigirla o cerrarla:
     ```js
     var waUrl = "https://wa.me/" + WA_NUMBER + "?text=" + encodeURIComponent(mensaje);
     var waWin = window.open("", "_blank"); // reserva la pestaña con el gesto del click
     if (global.DSApi) {
       global.DSApi.apiFetch("api/orders/create.php", { method: "POST", body: {...} })
         .then(function () {
           saveCart([]);
           if (waWin) { waWin.location = waUrl; } else { window.open(waUrl, "_blank"); }
         })
         .catch(function (err) {
           if (waWin) waWin.close();
           showFieldError(form, "form", "No se pudo registrar el pedido: " + err.message + ". Intenta de nuevo.");
         });
     } else {
       if (waWin) { waWin.location = waUrl; } else { window.open(waUrl, "_blank"); }
     }
     ```
     Quitar el `window.open(...)` incondicional de `:242`.

- Verificación:
  - Con red lenta (throttle en DevTools): el botón "Enviar" está deshabilitado hasta que
    `me.php` responde; no se puede disparar un POST sin token.
  - Forzar 403 (borrar el token en `DSApi`): WhatsApp no se abre y aparece el error visible
    en el formulario; con la corrección el pedido válido sí abre WhatsApp tras el 201/200.
  - Caso sin `DSApi`: WhatsApp abre igual (degradado), sin regresión.

---

## [S3] BAJO — Fallback a `move_uploaded_file` si el re-encode falla

- Estado: **CONFIRMADO**.
- Ubicación (mismo patrón en 3 endpoints):
  - `/home/user/YOWI/site/api/admin/products/upload-image.php:66-71`
  - `/home/user/YOWI/site/api/admin/brands/upload-image.php:64-68`
  - `/home/user/YOWI/site/api/admin/banners/upload-image.php:64-68`
  - Helper: `/home/user/YOWI/site/api/lib/Image.php:13` (`ds_reencode_image` devuelve
    `false` si GD no está o el formato no aplica).

- Escenario: si `ds_reencode_image()` devuelve `false` (GD ausente, o un archivo que pasó
  la detección MIME pero GD no puede decodificar), el código cae a `move_uploaded_file`,
  guardando el archivo **tal cual** — perdiendo la defensa que elimina payloads/EXIF de
  polyglots. Riesgo acotado (la carpeta tiene `.htaccess` que apaga PHP y el panel está
  tras login+2FA), pero contradice la intención de "re-encodar siempre".

- Arreglo: en los 3 endpoints, reemplazar el bloque fallback por un rechazo claro:
  ```php
  if (!ds_reencode_image($file['tmp_name'], $destDir . $safeName, $mime)) {
      @unlink($destDir . $safeName); // por si se creó un archivo parcial
      ds_json_error('No se pudo procesar la imagen de forma segura. Revisa el archivo (JPG, PNG o WebP válido) e inténtalo de nuevo.', 422);
  }
  ```
  (Sustituye `upload-image.php:66-71` de productos y `:64-68` de brands/banners.)
  No se elimina el helper ni se toca `Image.php`.

- Verificación:
  - Subir una imagen válida → sigue funcionando (se re-encoda, responde `url`).
  - En un host sin GD, o subiendo un archivo que engañe al MIME pero GD no decodifique →
    responde 422 con el mensaje, y **no** queda archivo en `assets/img/{productos,brands,banners}/`.

---

## [S4] BAJO — `password-reset.php` no invalida sesiones activas

- Estado: **CONFIRMADO**.
- Ubicación:
  - Reset sin invalidar: `/home/user/YOWI/site/api/auth/password-reset.php:49-55`
    (actualiza `password_hash` pero no toca sesiones ni marca fecha de cambio).
  - Sesión cliente: `/home/user/YOWI/site/api/lib/Session.php:43-57`
    (`ds_session_enforce_timeout` ya guarda/usa `user_login_time`, seteado en
    `ds_login_user` `:82`).
  - Tabla: `/home/user/YOWI/sql/schema.sql:116-125` (`users`, sin `password_changed_at`).

- Escenario: tras un reset de contraseña (p. ej. porque la cuenta fue comprometida), las
  sesiones ya abiertas del atacante siguen válidas hasta el timeout idle/absoluto — el
  reset no las expulsa.

- Arreglo:
  1. **Migración** `/home/user/YOWI/sql/migrations/2026-08-02-password-changed-at.sql`
     (idempotente en MariaDB con `IF NOT EXISTS`):
     ```sql
     ALTER TABLE users ADD COLUMN IF NOT EXISTS password_changed_at DATETIME NULL AFTER password_hash;
     ```
     Añadir también la columna a `/home/user/YOWI/sql/schema.sql` (tras `password_hash`).
  2. **Fijarla al resetear** `password-reset.php:51`:
     `UPDATE users SET password_hash = ?, password_changed_at = NOW() WHERE id = ?`
     (dentro de la transacción existente; añadir el bind).
  3. **Invalidar sesiones previas** en `Session.php`. `user_login_time` (unix) ya existe.
     Añadir una comprobación que corra para usuarios logueados; la vía menos intrusiva es
     un helper llamado desde `ds_current_user_id()` (que ya arranca la sesión):
     ```php
     function ds_session_check_password_change(): void
     {
         if (empty($_SESSION['user_id']) || !function_exists('ds_get_pdo')) return;
         $login = (int) ($_SESSION['user_login_time'] ?? 0);
         try {
             $stmt = ds_get_pdo()->prepare('SELECT password_changed_at FROM users WHERE id = ?');
             $stmt->execute([(int) $_SESSION['user_id']]);
             $changed = $stmt->fetchColumn();
             if ($changed && strtotime((string) $changed) > $login) {
                 unset($_SESSION['user_id'], $_SESSION['csrf_token'],
                       $_SESSION['user_last_activity'], $_SESSION['user_login_time']);
             }
         } catch (Throwable $e) { error_log('pw-change check: ' . $e->getMessage()); }
     }
     ```
     Llamarla en `ds_current_user_id()` justo después de `ds_session_start()`
     (`Session.php:60-62`). Es un lookup por PK; costo aceptable para esta tienda.
     `ds_login_user` no necesita cambios (el nuevo login tiene `user_login_time > password_changed_at`).

- Caveat honesto: esto añade **una consulta por request autenticado** de cliente. Para
  esta escala es trivial; se documenta por transparencia. Si preocupara, se puede cachear
  `password_changed_at` en sesión y refrescar cada N segundos, pero YAGNI por ahora.

- Nota: `update-profile.php` podría también cambiar contraseña; si lo hace, debería fijar
  `password_changed_at` igual. Fuera del alcance declarado de S4 — el Constructor lo revise
  si aplica.

- Verificación:
  - Sesión A logueada. Ejecutar un reset válido para ese usuario. En la siguiente request
    de A → `me.php` devuelve `user:null` (sesión invalidada). La nueva sesión creada con la
    contraseña nueva funciona normal.
  - `SELECT password_changed_at FROM users WHERE id=...` refleja el `NOW()` del reset.

---

## [S6] BAJO — `AdminSession` fija SameSite=Lax siempre

- Estado: **CONFIRMADO**.
- Ubicación: `/home/user/YOWI/site/api/lib/AdminSession.php:12-21` (`ds_admin_session_start`,
  línea 16 hardcodea `Lax`). Referencia correcta: `/home/user/YOWI/site/api/lib/Session.php:26-36`.

- Escenario: si el panel admin se sirviera cross-site (frontend en otro dominio que el
  backend) con `DS_CROSS_SITE=1`, la cookie de sesión de cliente usa `None+Secure`
  (Session.php) pero la de admin queda en `Lax` → el navegador no la envía cross-site y el
  login admin no persiste. Inconsistencia con la sesión de cliente.

- Arreglo: reflejar la lógica de `Session.php` en `ds_admin_session_start`. Reemplazar la
  línea 16 (`ini_set('session.cookie_samesite', 'Lax');`) y el `cookie_secure` de la línea
  18 por:
  ```php
  $crossSite = getenv('DS_CROSS_SITE') === '1'
      || (isset($_SERVER['DS_CROSS_SITE']) && $_SERVER['DS_CROSS_SITE'] === '1');
  if ($crossSite) {
      ini_set('session.cookie_samesite', 'None');
      ini_set('session.cookie_secure', '1');
  } else {
      ini_set('session.cookie_samesite', 'Lax');
      ini_set('session.cookie_secure', !empty($_SERVER['HTTPS']) ? '1' : '0');
  }
  ```
  (mantener `cookie_httponly` y `use_strict_mode` como están, líneas 15 y 17).

- Verificación:
  - Sin `DS_CROSS_SITE`: la cookie admin sale `SameSite=Lax` (igual que hoy) — sin regresión.
  - Con `SetEnv DS_CROSS_SITE 1`: inspeccionar `Set-Cookie` de `me.php`/`login.php` admin →
    `SameSite=None; Secure` (igual que la de cliente).

---

## [S5] BAJO — CSP del admin laxa (`script-src 'unsafe-inline'` + CDN de Tailwind)

- Estado: **CONFIRMADO** (arreglo grande: es un mini-proyecto de build, no un one-liner).
- Ubicación:
  - CSP: `/home/user/YOWI/site/admin/.htaccess:8`
    (`script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com`).
  - CDN + config inline: cada página admin, línea 8 (`<script src="https://cdn.tailwindcss.com">`)
    y línea 9 (`tailwind.config = {...}` inline; en `login.html` va en líneas 9-27).
  - Build storefront de referencia: `package.json:7` (`build:css`) y
    `/home/user/YOWI/tailwind.config.js`.

- Inventario EXACTO de `<script>` inline en el admin (a externalizar/eliminar):

  | Página | `<script>` inline | Qué hace | Acción |
  |--------|-------------------|----------|--------|
  | `login.html` | L8 CDN, L9-27 `tailwind.config` | config Tailwind | eliminar (self-host) |
  | `index.html` | L8 CDN, L9 config, **L87-146 lógica dashboard** | carga métricas + tabla de pedidos recientes (`stat()`, `esc()`) | mover a `assets/js/admin/dashboard.js` |
  | `categorias.html` | L8 CDN, L9 config, **L131-135** | oculta `#cat-modal` al cancelar | mover a `categories.js` |
  | `marcas.html` | L8 CDN, L9 config, **L200-204** | oculta `#brand-modal` al cancelar | mover a `brands.js` |
  | `promociones.html` | L8 CDN, L9 config, **L156-160** | oculta `#banner-modal` al cancelar | mover a `banners.js` |
  | `productos.html` | L8 CDN, L9 config, **L260-264** | oculta `#product-modal` al cancelar | mover a `products.js` |
  | `nosotros.html` | L8 CDN, L9 config | solo config | eliminar (self-host) |
  | `pedidos.html` | L8 CDN, L9 config | solo config | eliminar (self-host) |
  | `seguridad.html` | L8 CDN, L9 config | solo config | eliminar (self-host) |

  Nota: `index.html` genera `style="border-left-color:..."` inline (L138) y varias páginas
  tienen `<style>body{...}</style>` y atributos `style=`. Eso es **style-src**, no script-src;
  se deja `style-src 'unsafe-inline'` intacto. El objetivo de S5 es solo `script-src 'self'`.

- Arreglo:

  1. **Config de build del admin** — nuevo `/home/user/YOWI/tailwind.admin.config.js`
     replicando la config inline (paleta ink/brand/lime/paper/cobalt + fuentes Barlow) y
     escaneando el admin:
     ```js
     module.exports = {
       content: ["./site/admin/*.html", "./site/assets/js/admin/*.js", "./site/assets/js/fuzzy.js"],
       theme: { extend: {
         fontFamily: { display: ['"Barlow Condensed"','sans-serif'], body: ['"Barlow"','sans-serif'] },
         colors: {
           ink: "#0B0F1A", brand: "#1F5FD9", lime: "#8FD11F", paper: "#F7F8FA",
           cobalt: { 500: "#378ADD", 600: "#185FA5", 700: "#0D4A80" },
         },
       } },
       plugins: [],
     };
     ```
     ⚠ El admin usa mucho la paleta **por defecto** de Tailwind (slate, green, red, blue,
     yellow, cyan) y clases con opacidad (`bg-green-900/50`, `bg-slate-700/30`). Al
     auto-hospedar con `content` escaneando HTML+JS, Tailwind incluye solo las clases que
     detecta; como los colores dinámicos en `products.js` (green/red/blue) son literales en
     el código fuente, el scan los captura. Verificar visualmente tras el build.

  2. **Input CSS** — nuevo `/home/user/YOWI/site/assets/css/admin.input.css` con
     `@tailwind base; @tailwind components; @tailwind utilities;` (+ el `body{font-family:'Barlow'}`).

  3. **Script de build** — añadir a `package.json:8`:
     ```json
     "build:css:admin": "tailwindcss -c tailwind.admin.config.js -i site/assets/css/admin.input.css -o site/assets/css/admin.css --minify",
     ```
     (y opcional `"watch:css:admin"` análogo). Correr con `npx tailwindcss ...` / `npm run build:css:admin`.

  4. **Externalizar scripts inline**:
     - Crear `/home/user/YOWI/site/assets/js/admin/dashboard.js` con el bloque L87-146 de
       `index.html` (envuelto en su `DOMContentLoaded`, sin cambios de lógica).
     - Mover cada handler `modal-cancel-btn` a su módulo (`categories.js`, `brands.js`,
       `banners.js`, `products.js`), idealmente dentro del `DOMContentLoaded` existente.

  5. **En las 9 páginas admin**: quitar `<script src="cdn.tailwindcss.com">` (L8) y el
     `<script>tailwind.config...</script>` (L9), y en su lugar poner
     `<link rel="stylesheet" href="../assets/css/admin.css">`. Añadir
     `<script src="../assets/js/admin/dashboard.js"></script>` en `index.html`. Eliminar los
     `<script>...modal-cancel...</script>` de categorias/marcas/promociones/productos.

  6. **CSP** `/home/user/YOWI/site/admin/.htaccess:8`: cambiar
     `script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com` → `script-src 'self'`.
     Dejar el resto (`style-src 'self' 'unsafe-inline' https://fonts.googleapis.com`,
     `font-src`, etc.) igual. Actualizar el comentario de cabecera (L1-6) que hoy justifica
     el CDN.

- Verificación:
  - `npm run build:css:admin` genera `site/assets/css/admin.css` sin errores.
  - Abrir cada página admin: idéntica a hoy (colores, fuentes, layout). El dashboard carga
    métricas y pedidos; los modales de productos/categorías/marcas/promociones se cierran
    con "Cancelar".
  - DevTools → Console: **sin violaciones de CSP**; Network no pide `cdn.tailwindcss.com`.
  - `curl -sI https://.../admin/index.html | grep -i content-security` → `script-src 'self'`.

---

## Orden de construcción sugerido

1. **C1 + C3 (stock)** — primero, porque la migración `2026-08-02-stock-nullable.sql`
   es one-shot y toca schema + import + create/update + list + catálogo + checkout. Hacerlo
   como una unidad y probar de punta a punta antes de seguir.
2. **S4 (password_changed_at)** — migración independiente + toques en Session/password-reset.
   Va temprano porque también es migración de esquema (evita mezclar despliegues de BD).
3. **S1 (2FA server-side)** — solo PHP en `AdminSession.php`; sin migración. Rápido y de alto valor.
4. **S6 (SameSite admin)** — one-liner en `AdminSession.php`; agrúpalo con S1 (mismo archivo).
5. **S2 (IP confiable)** — `RateLimit.php` + `env.example.php`; independiente.
6. **S3 (rechazo de re-encode)** — 3 endpoints de upload; independiente y trivial.
7. **C2 (carrera CSRF)** — solo `cart.js`; independiente.
8. **S5 (CSP admin / self-host Tailwind)** — último: es el más grande, requiere build y
   tocar las 9 páginas; que no bloquee los fixes de seguridad más pequeños.

Dependencias: C1 y C3 comparten la **misma** migración de stock (hacerlas juntas). S1 y S6
comparten `AdminSession.php`. Ningún otro par comparte archivos críticos.

---

## Notas para el Constructor (patrones a reusar)

- **Helpers `ds_*`**: reusar `ds_json_error`/`ds_json_success` (Response.php),
  `ds_clean_string`/`ds_to_positive_int`/`ds_clean_url` (Validate.php),
  `ds_get_pdo()` (PDO en modo excepción, `EMULATE_PREPARES=false`). No reinventar.
- **Lectura de env**: patrón `require env.php` con caché estática, como `ds_mail_config`
  (Mailer.php:14-27). Úsalo para S2 (`TRUSTED_IP_HEADER`). `env.php` está gitignored →
  documentar toda nueva clave en `env.example.php`.
- **Migraciones**: van en `/home/user/YOWI/sql/migrations/AAAA-MM-DD-<tema>.sql`, y deben
  ser **idempotentes** siempre que se pueda: `CREATE TABLE IF NOT EXISTS`,
  `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` (MariaDB lo soporta), `MODIFY COLUMN`. El
  **único paso NO idempotente** es el `UPDATE products SET stock=NULL WHERE stock=0` de C1:
  marcarlo explícitamente como one-shot. Replicar cada cambio de esquema también en
  `sql/schema.sql` para instalaciones nuevas.
- **Build CSS**: `npm run build:css` compila el storefront (`tailwind.config.js` →
  `site/assets/css/app.css`). Para S5 añadir `build:css:admin` análogo con una config y un
  input propios; ejecutarlo con `npx tailwindcss`/`npm run build:css:admin`. El admin usa
  la paleta por defecto de Tailwind además de la custom: verificar visualmente el build.
- **Transacciones + concurrencia**: para el descuento de stock (C3) usar
  `SELECT ... FOR UPDATE` dentro de `beginTransaction()` y `UPDATE ... WHERE stock >= ?`
  con verificación de `rowCount()`; en cualquier fallo, `rollBack()` (ya hay un `try/catch`
  con `rollBack` en `orders/create.php:80-117` para extender).
- **CSRF**: el front añade `csrf_token` automáticamente si `setCsrfToken` corrió
  (api-client.js). Cliente usa `$_SESSION['csrf_token']`; admin usa `$_SESSION['admin_csrf']`
  vía `ds_admin_csrf_check`. No mezclar.
- **No tocar** `CLAUDE.md`, `PRODUCT.md`, `DESIGN.md` salvo indicación explícita del usuario.
