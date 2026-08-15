# Guía de despliegue — Hostinger (primera vez)

Esta guía se sigue con el hPanel de Hostinger abierto en otra pestaña, no requiere saber
programar. Sigue los pasos en orden — cada uno depende del anterior.

⚠️ **Ninguna contraseña real debe escribirse en un archivo de este repositorio, en un chat, ni
en ningún documento.** Donde esta guía dice "tu contraseña", significa: la tecleas tú, en el
momento, en el lugar correcto (hPanel, phpMyAdmin, o tu propia terminal) — nunca la pegues en
un archivo que luego se suba a ningún lado.

---

## 1. Crear la base de datos y el usuario

En hPanel → **Bases de datos → MySQL**:

1. Crea la base de datos. Hostinger le pone un prefijo tipo `u123456789_`, así que el nombre
   final será algo como `u123456789_dssupp`. Apúntalo, lo necesitas en el paso 3.
2. Crea un usuario y **asígnale una contraseña fuerte y única** (no reutilices ninguna otra).
   Apunta el nombre de usuario (también con prefijo, tipo `u123456789_app`).

**Nota honesta sobre `sql/provision-db-user.sql`**: ese archivo define dos usuarios de
privilegios mínimos (`ds_app` solo con SELECT/INSERT/UPDATE/DELETE, `ds_migrator` con permisos
de estructura). **Es muy probable que no puedas usarlo tal cual en hosting compartido** —
Hostinger normalmente no permite `CREATE USER` ni `GRANT` desde phpMyAdmin, y el usuario que
crea el propio hPanel recibe automáticamente todos los privilegios sobre esa base de datos
(incluido borrar tablas). Es una limitación del hosting compartido, no un descuido de este
proyecto. Vas a terminar con **un solo usuario con privilegios completos** sobre tu base — es
el escenario realista, y por eso los pasos 8 (respaldos) y 9 (verificación) importan tanto.

---

## 2. Importar la estructura de la base de datos

En hPanel, abre **phpMyAdmin** sobre la base que acabas de crear.

1. Pestaña **Importar** → selecciona `sql/schema.sql` de este repositorio → Continuar.
   Este archivo ya incluye las 12 tablas completas, con sus índices y el contenido inicial de
   la sección "Nosotros" — no necesitas rellenar nada a mano después.
2. Después, importa **cada archivo de `sql/migrations/` en orden alfabético** (son 20):
   ```
   2026-07-14-add-direccion-envio.sql
   2026-07-26-add-banners.sql
   2026-07-26-add-brands.sql
   2026-07-26-add-unidad.sql
   2026-07-27-add-favorites.sql
   2026-07-27-add-settings.sql
   2026-07-27-add-user-addresses.sql
   2026-07-28-add-2fa-and-tokens.sql
   2026-07-29-add-recovery-and-audit.sql
   2026-08-02-password-changed-at.sql
   2026-08-02-stock-nullable.sql
   2026-08-03-add-flavors.sql
   2026-08-03-add-product-images.sql
   2026-08-03-add-sku.sql
   2026-08-09-add-order-item-sabor-id.sql
   2026-08-09-add-orders-stock-repuesto.sql
   2026-08-10-add-admin-roles.sql
   2026-08-12-add-settings-negocio-legal.sql
   2026-08-13-legal-privacidad.sql
   2026-08-14-add-consent-timestamps.sql
   ```
   Todas están escritas para no fallar si algo ya existe (verifican antes de crear), así que
   aunque `schema.sql` ya traiga la mayoría de estos cambios, correrlas encima no hace daño —
   es la forma de no depender de que `schema.sql` esté 100% al día en el futuro.

---

## 3. Crear `env.php` en el servidor

Este archivo **no viaja en git** (nunca lo verás en el repositorio) — hay que crearlo a mano en
el servidor, una sola vez.

1. Por el Administrador de archivos de hPanel (o FTP), entra a `public_html/api/config/`.
2. Copia `env.example.php` a `env.php` (mismo nombre, sin el `.example`).
3. Ábrelo y rellena:
   ```php
   'DB_HOST' => 'localhost',
   'DB_NAME' => 'u123456789_dssupp',      // el nombre real que anotaste en el paso 1
   'DB_USER' => 'u123456789_app',         // el usuario real que creaste
   'DB_PASS' => '...',                     // su contraseña real
   'DB_CHARSET' => 'utf8mb4',

   'MAIL_TRANSPORT' => 'mail',             // NO lo dejes en 'none' — ver nota abajo
   'MAIL_FROM'      => 'no-reply@tudominio.com',   // un buzón real de tu dominio
   'MAIL_FROM_NAME' => 'Distribuidor de Suplementos',
   'APP_URL'        => 'https://tudominio.com',    // tu dominio real, SIN barra final

   'TRUSTED_IP_HEADER' => '',              // déjalo vacío salvo que uses Cloudflare (ver paso 9)

   'TOTP_ENCRYPTION_KEY' => '...',         // ver punto 5 abajo — obligatoria antes del primer 2FA
   ```
4. **`MAIL_TRANSPORT => 'mail'` es obligatorio**, no opcional: con `'none'`, "olvidé mi
   contraseña" no envía nada (aunque el sitio le diga al cliente que sí). Usa un buzón que
   exista de verdad en tu dominio (créalo en hPanel → Correo si no tienes uno). Si los correos
   de recuperación te llegan a spam, es casi siempre por SPF/DKIM mal configurados en el
   dominio — revísalo en hPanel → Correo → Autenticación.
5. **Clave de cifrado del 2FA — `TOTP_ENCRYPTION_KEY`** (obligatoria antes de que cualquier
   admin active el 2FA; el secreto de cada admin se guarda cifrado en la base, no en texto
   plano). Genera la clave **en tu propia computadora**, no en el servidor:
   ```bash
   php -r "echo base64_encode(random_bytes(32));"
   ```
   Copia el resultado (una cadena en base64) a `env.php` tal cual, en el campo
   `TOTP_ENCRYPTION_KEY` de arriba.

   **Antes de depender de esto, verifica que tu plan de Hostinger tenga la extensión
   `sodium` habilitada** para la versión de PHP de tu dominio (hPanel → Configuración de
   PHP → lista de extensiones, o `php -m | grep sodium` si tienes SSH). Sin ella el 2FA no
   podrá guardarse ni verificarse — es una extensión incluida en PHP desde la versión 7.2,
   así que en un hosting con PHP moderno debería estar disponible, pero conviene
   confirmarlo antes de que un admin dependa de ella.

   ⚠️ **Nunca pierdas ni cambies esta clave una vez que algún admin tenga el 2FA activo.**
   Hacerlo invalida en silencio TODOS los secretos TOTP ya guardados — esos admins dejarán
   de poder pasar el segundo factor al iniciar sesión y quedarán bloqueados del panel (el
   2FA es obligatorio para casi toda acción admin). Si llega a pasar:

   - Si el admin bloqueado todavía conserva sus **códigos de recuperación** (se muestran
     una sola vez al activar el 2FA — pídele que los busque donde los guardó), puede
     iniciar sesión con uno de ellos: son independientes de esta clave.
   - Si también los perdió, hace falta entrar directo a la base (phpMyAdmin o SSH) y
     correr `UPDATE admins SET totp_secret = NULL, totp_enabled = 0 WHERE id = <su id>;`,
     y que esa persona reenrole su 2FA desde **Panel → Seguridad**.

---

## 4. Subir el sitio

**Sube ÚNICAMENTE el contenido de la carpeta `site/`** — el contenido, no la carpeta en sí —
a `public_html/`. Es decir, dentro de `public_html/` deben quedar directamente `index.html`,
`admin/`, `api/`, `assets/`, etc.

**NO subas**: `sql/`, `scripts/`, `docs/`, `.git/`, `node_modules/`, `graphify-out/`,
`referencias/`, `productos.json`, `package.json`, ni ningún `.md`/`.bat`/`.ps1` de la raíz del
repositorio. Ninguno de esos hace falta para que el sitio funcione, y varios contienen
contraseñas de desarrollo local o el mapa completo del código — mejor que ni siquiera lleguen
al servidor, en vez de depender de que el `.htaccess` los bloquee.

Ya dentro de `site/`, borra estos dos archivos si los subiste (son de un modo de despliegue
distinto — frontend en Vercel — que no aplica aquí, y `vercel.json` además queda descargable):

- `vercel.json`
- `.vercelignore`

---

## 5. Crear tu cuenta de dueño

Tu correo: `menchacaramirez.ma1821@gmail.com`. Dos caminos, según si tu plan de Hostinger
incluye acceso SSH:

### Si tienes SSH

```bash
php scripts/create-admin.php "Mario Menchaca" "menchacaramirez.ma1821@gmail.com" "tu-contraseña"
```
El script ya se encarga de guardarla hasheada (bcrypt) y de darte el rol de dueño completo.

Después de correrlo, **borra esa línea de tu historial de comandos** (tu contraseña quedó ahí
en texto):
```bash
history -d $(history | tail -2 | head -1 | awk '{print $1}')
```
(o edita `~/.bash_history` a mano si prefieres estar seguro).

### Si NO tienes SSH (lo más común en hosting compartido)

Genera el hash de tu contraseña **en tu propia computadora**, no en el servidor:
```bash
php -r "echo password_hash('tu-contraseña', PASSWORD_DEFAULT);"
```
Vas a obtener algo como `$2y$12$...` (una sola línea). Copia SOLO ese hash — tu contraseña en
claro nunca sale de tu computadora.

En phpMyAdmin, pestaña **SQL**, corre (reemplazando `<EL_HASH_QUE_COPIASTE>` por lo que
obtuviste, entre comillas):
```sql
INSERT INTO admins (nombre, email, password_hash, rol, activo, totp_enabled)
VALUES ('Mario Menchaca', 'menchacaramirez.ma1821@gmail.com', '<EL_HASH_QUE_COPIASTE>', 'dueno', 1, 0);
```

### Primer inicio de sesión — hazlo en este orden

1. Entra a `https://tudominio.com/admin/login.html` con tu correo y la contraseña que elegiste.
2. El panel te va a pedir activar el 2FA de inmediato — no puedes usar el panel sin eso.
   Usa Google Authenticator, Authy, o cualquier app de códigos.
3. Ve a **Panel → Seguridad** y cambia tu contraseña por una definitiva, aunque acabes de
   ponerla — así queda una que nunca estuvo en ningún chat ni documento, solo en tu cabeza (o
   tu gestor de contraseñas).
4. Ve a **Panel → Nosotros** y revisa las 5 secciones (Nosotros, Contacto, Redes, Datos del
   negocio, Textos legales). `schema.sql` ya trae cargado el contenido real que diste durante
   el desarrollo — teléfono, dirección, misión/visión, y las Políticas de compra/envío y
   Términos transcritos de tu documento de mayo 2025 — pero conviene confirmarlo ahí mismo antes
   de anunciar el sitio, y llenar lo que faltó (redes sociales, razón social/RFC si facturas).
   Todo lo que cambies ahí se refleja de inmediato en la tienda pública, sin tocar código.

---

## 6. Verificaciones después de subir

Con el sitio ya en línea, confirma esto antes de anunciarlo a nadie:

- **HTTPS forzado**: entra por `http://tudominio.com` (sin la S) y confirma que te redirige
  solo a `https://`.
- **Cabeceras de seguridad presentes**: desde tu computadora,
  ```bash
  curl -I https://tudominio.com/
  ```
  deberías ver `Strict-Transport-Security`, `X-Frame-Options`, `Content-Security-Policy`, etc.
  Si no aparecen, es que `mod_headers` no está activo en tu plan — contacta a soporte de
  Hostinger.
- **Carpetas sensibles bloqueadas**: `https://tudominio.com/api/config/env.php` y cualquier
  archivo dentro de `https://tudominio.com/api/lib/` deben dar **403 Forbidden**, nunca mostrar
  contenido.
- **Las carpetas de imágenes no truenan**: sube una imagen de prueba a un producto/marca/banner
  desde el panel. Si te da un error 500 en vez de guardar la imagen, es por el `php_flag engine
  off` de esas carpetas — es una instrucción de Apache clásico (`mod_php`) que en el LiteSpeed
  de Hostinger a veces no se entiende. La protección real (`Require all denied` sobre archivos
  PHP) sigue funcionando igual; si ves el 500, avísame y quito esa línea específica.
- **Tamaño de subida de imágenes**: en hPanel → **PHP Config** (o "Configuración de PHP"),
  confirma que `upload_max_filesize` y `post_max_size` sean de al menos `16M` — si están más
  bajos, subir fotos de producto grandes fallará en silencio o con un error confuso.

---

## 7. Sobre el candado HTTPS permanente (HSTS)

El sitio manda una cabecera que le dice al navegador "entra siempre por HTTPS a este dominio
**y a todos sus subdominios**, durante un año, pase lo que pase". Es buena seguridad, pero
**no se puede revertir rápido** una vez que un visitante la recibe una sola vez.

Antes de que cualquiera visite el sitio: confirma que el certificado SSL de Hostinger esté
activo y funcionando en tu dominio principal. Si en el futuro agregas un subdominio (por
ejemplo `blog.tudominio.com`) sin HTTPS ahí, los navegadores que ya visitaron el sitio
principal se van a negar a cargarlo. No es algo que deba preocuparte para el lanzamiento, pero
consérvalo en mente.

---

## 8. Respaldos automáticos

`scripts/backup-db.sh` no se sube al servidor como parte del sitio (queda fuera de
`public_html/`, ver paso 4), pero sí conviene activarlo aparte:

1. Sube ese único archivo a una carpeta **fuera** de `public_html/` (p. ej. junto a tu
   usuario, en `~/scripts/`).
2. En hPanel → **Cron Jobs**, agrega una tarea diaria (por ejemplo 3:15 am):
   ```
   DB_USER=tu_usuario DB_PASS=tu_contraseña bash ~/scripts/backup-db.sh >> ~/backups/backup.log 2>&1
   ```
   Guarda 7 respaldos diarios y 4 semanales por defecto, siempre fuera del área pública.

---

## 9. Opcional — Cloudflare

Si más adelante pones el sitio detrás de Cloudflare (protección extra contra ataques, caché),
solo necesitas UN cambio en `env.php`:
```php
'TRUSTED_IP_HEADER' => 'CF-Connecting-IP',
```
Esto es necesario para que el sistema de bloqueo por intentos fallidos de login mida la IP real
del visitante y no la de Cloudflare. La cookie de sesión ya está preparada para funcionar
correctamente detrás de un proxy así (se verificó y corrigió específicamente para este caso).

---

## Lo que queda pendiente, a propósito (no bloquea el lanzamiento)

Documentado aquí para que lo sepas y no te lo encuentres por sorpresa — ninguno impide abrir la
tienda, pero conviene revisarlos en una sesión futura:

- El código de un admin de 2FA es válido unos 90 segundos más de lo estrictamente necesario, y
  no se sube el "costo" del cifrado de contraseñas más allá del valor por defecto de PHP —
  ambos son ajustes finos, no urgencias.
- No hay retención/purga automática de pedidos viejos (queda como decisión contable tuya, no
  técnica) ni backend de mensajes de contacto (el formulario de Nosotros abre WhatsApp
  directamente, a propósito — ver `docs/project-context.md`).

**Ya resuelto** (documentado aquí porque estaba en esta misma lista antes): el aviso de
privacidad ahora existe (`privacidad.html`), declara con precisión qué datos se recaban y con
quién se comparten (WhatsApp/Meta y la paquetería), el registro y el checkout exigen y guardan
el consentimiento con fecha real, y tanto el cliente como el dueño pueden eliminar una cuenta
(anonimizando sus pedidos, no borrándolos, porque hacen falta para tu contabilidad). Aun así es
un texto base — conviene que lo revise un profesional legal antes de tratarlo como definitivo.
El secreto del 2FA ya se guarda cifrado en la base (ver punto 5 del paso 3, arriba), y el panel
tiene una pantalla nueva (**Panel → Auditoría**, solo dueño) para leer el historial de acciones
de administradores sin tener que consultar la base directamente.

⚠️ **Un punto para ti, no del sitio**: el documento de políticas que nos diste (mayo 2025)
incluye la frase "SU INFORMACIÓN ESTÁ SEGURA. NO COMPARTIMOS DATOS CON TERCEROS". Esa frase
**no se transcribió al sitio** (`terminos.html` no la incluye) precisamente porque no es exacta
tal como opera la tienda hoy: el pedido se transfiere a WhatsApp/Meta y el domicilio se comparte
con la paquetería, y el nuevo `privacidad.html` lo declara con precisión. Si sigues usando esa
frase en el PDF original, en redes o en tu perfil de WhatsApp Business, conviene que la ajustes
para que no contradiga lo que el aviso de privacidad del sitio ya dice.
