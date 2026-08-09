# Auditoría de seguridad — usuario y admin (2026-08-09)

Continuación de la auditoría del lado de usuario del mismo día
(`docs/auditoria-usuario-2026-08-09.md`). Esta ronda cubrió lo que faltaba: **el panel
admin nunca había tenido una auditoría de seguridad** (la revisión previa fue de
UX/botones) — son 41 endpoints admin contra 20 públicos, el doble de superficie sin
revisar. También se re-examinó el lado de usuario buscando clases de ataque modernas no
cubiertas antes (evasión de límites, DoS, bombas de descompresión, etc.).

Metodología: descubrimiento con 3 agentes en paralelo (superficie del admin / clases de
ataque modernas / configuración-despliegue-datos-cripto), seguido de **reproducción en
vivo de cada hallazgo Crítico/Alto antes de arreglarlo, y re-verificación en vivo después
del arreglo**. Nada se marcó como cerrado solo por lectura de código.

**Alcance de reparación, decidido por el usuario**: arreglar Crítico y Alto. Medio y
Bajo quedan documentados con su `ruta:línea` y arreglo propuesto, sin implementar.

---

## Corrección a la auditoría de esta misma mañana

Antes de los hallazgos nuevos: dos afirmaciones de `docs/auditoria-usuario-2026-08-09.md`
resultaron falsas al probarlas con un ataque distinto al que se usó para "cerrarlas".
Se corrigen aquí en vez de reescribir el documento original:

1. **"H1 cerrado con el tope de 100/línea" — incompleto.** El tope se probó con *una*
   línea de cantidad 999999 (bloqueaba). Nunca se probó **repetir la misma línea**. Ver
   C-2 abajo: 5 líneas de 30 sumaban 150 unidades del mismo producto, evadiendo el tope
   de 100. Ya está cerrado (ver C-2), pero el cierre de esta mañana no era completo.
2. **"Los ~30 `innerHTML` pasan por `esc()`" — inexacto.** `admin/dashboard.js:38-41,46`
   interpolaba `o.estado`, `o.created_at` y `err.message` sin `esc()`. Hoy son valores de
   enum/fecha de BD y un mensaje de error estático (sin riesgo real), pero la afirmación
   de cobertura total era falsa. Corregido en el quick win de la sección 2.8 de abajo.

La lección de fondo (ambas vienen del mismo patrón) está en
`docs/guia-seguridad.md#8` y no se repite aquí.

---

## 🔴 Hallazgos Críticos — verificados en vivo y cerrados

### C-1 — Con solo la contraseña de un admin se roba toda la BD y se secuestra la cuenta

**Verificado en vivo, reproducido de punta a punta:**
1. `POST admin/auth/login.php` con contraseña correcta y **sin** TOTP (cuenta con
   `totp_enabled=0`, el estado por defecto de `schema.sql:14` para todo admin nuevo) →
   sesión admin completa creada. `login.php:49` solo exige TOTP si `totp_enabled=1`.
2. `GET admin/backup/export.php?tipo=todo` → **200, volcado completo**: catálogo,
   categorías, marcas, pedidos (con teléfono y `direccion_envio`), promociones. Porque
   `ds_require_admin()` (`AdminSession.php:56-61`, antes del arreglo) solo comprobaba
   `admin_id` en sesión — el enforcement de 2FA vivía **dentro de
   `ds_admin_csrf_check()`** (`:128`), que **solo corre en POST**. Ningún GET pasaba por
   ahí.
3. `POST admin/auth/2fa-setup.php` + `2fa-activate.php` con un autenticador propio → el
   atacante enrola SU 2FA (estos dos endpoints están necesariamente exentos del check,
   para poder enrolarse por primera vez) y **deja fuera al dueño legítimo de forma
   permanente**.

**Arreglo — guardián único (`site/api/lib/AdminSession.php`):**
```php
function ds_require_admin(bool $permitirSinEnrolar = false): int {
    $id = ds_current_admin_id();
    if ($id === null) ds_json_error('No autenticado como administrador', 401);
    if (!$permitirSinEnrolar) ds_admin_require_2fa_enrolled($id);
    return $id;
}
```
`ds_admin_require_2fa_enrolled()` dejó de decidir por `basename(SCRIPT_NAME)` (una
variable derivada de la petición) y ahora recibe el `$adminId` explícito. El enforcement
de 2FA se sacó de `ds_admin_csrf_check()` — ahora **cada uno de los 41 endpoints admin
lo hereda con solo llamar `ds_require_admin()`**, en GET o POST. Solo 4 endpoints pasan
`ds_require_admin(true)` (sesión sin exigir 2FA aún): `2fa-setup.php`, `2fa-activate.php`,
`2fa-recovery.php`, `2fa-status.php` — los que un admin necesita antes de tener 2FA.

**Re-verificado en vivo tras el arreglo:** login sin TOTP → `GET backup/export.php`,
`GET orders/list.php`, `GET products/list.php` → los tres **403 "Debes activar el 2FA
antes de operar el panel."** El flujo de enrolamiento normal (setup → activate → ya con
2FA, `GET backup/export.php`) se probó completo y sigue funcionando.

### C-1b — Contraseña re-pedida al activar el 2FA (decisión del usuario)

Aunque C-1 ya cierra el acceso de solo-contraseña, una sesión ya secuestrada por otra vía
(equipo desatendido, cookie robada) podía todavía enrolar un 2FA propio sin volver a
demostrar que conoce la contraseña. `admin/auth/2fa-activate.php` ahora exige `password`
en el body y la verifica con `password_verify()` contra el hash del admin en sesión antes
de activar el 2FA. Front actualizado en `site/admin/seguridad.html` +
`assets/js/admin/seguridad.js` (campo nuevo, se limpia tras usarlo).
**Verificado en vivo**: sin `password` → 400; con password incorrecta → 401 "Contraseña
incorrecta"; con la correcta → 200, 2FA activado.

### C-2 — El tope de 100/línea de esta mañana era evadible repitiendo la línea

**Verificado en vivo:** `orders/create.php` agregaba cada entrada del array del cliente a
`$requested[]` de forma independiente (`:83`, antes del arreglo) y aplicaba
`DS_MAX_QTY_PER_LINE` **por entrada** (`:72`), no por producto. El `usort` que ordena
`$requested` por `(producto_id, sabor_id)` (agregado esta mañana para el deadlock) **solo
ordena, no agrega**.

```json
{"items": [{"producto_id":49,"cantidad":30}, ... ×5]}
```
→ 150 unidades del mismo producto descontadas en una sola petición anónima (verificado:
stock 1000→850). Con stocks reales más pequeños, esto sigue vaciando el catálogo con una
petición — exactamente el ataque que el tope pretendía cerrar.

**Agravante adicional, verificado por lectura de código (no explotado en vivo por
prudencia — habría requerido miles de líneas y locks retenidos largo rato en un servidor
compartido con otros probando en paralelo):** sin techo al número de líneas, una petición
con ~10⁵ elementos dispararía ~10⁵ `SELECT ... FOR UPDATE` dentro de una única
transacción, reteniendo locks sobre buena parte del catálogo durante minutos.

**Arreglo (`site/api/orders/create.php`):**
1. `count($items) > 50` → 400 antes de procesar nada.
2. Se agrega por clave `producto_id|sabor_id` **sumando cantidades** antes de aplicar el
   tope — no se guarda cada línea cruda del cliente.
3. `DS_MAX_QTY_PER_LINE` se aplica al **total agregado**, y el `ajuste` reportado
   (`motivo: limite_cantidad`) refleja `cantidad_pedida` (la suma) vs. `cantidad_final`.

**Re-verificado en vivo:** el mismo ataque (5×30 del mismo producto) ahora crea **una**
línea de 100 unidades y reporta `ajustes: [{"cantidad_pedida":150,"cantidad_final":100}]`.
Un carrito normal con 2 productos distintos sigue funcionando sin ajustes espurios
(verificado). Una petición de 51 líneas → 400 "El pedido tiene demasiadas líneas (máximo
50)", sin tocar la BD.

---

## 🟠 Hallazgos Altos — verificados y cerrados

### A-1 — Fuerza bruta de TOTP sin ningún límite en 2fa-disable/2fa-recovery/2fa-activate

**Verificado en vivo:** 15 códigos inválidos consecutivos contra `2fa-disable.php` → 15×
401, **nunca un 429**. Los tres endpoints no incluían siquiera `RateLimit.php`. Con una
sesión secuestrada, ~333 000 intentos agotan el espacio de códigos válidos de una ventana
(factible en horas). Éxito = 2FA desactivado o 10 códigos de recuperación nuevos en mano
del atacante.

**Arreglo:** `ds_rate_limit_ip('2fa', ds_client_ip(), 10, 15)` en los tres endpoints
(`2fa-disable.php`, `2fa-recovery.php`, `2fa-activate.php`).
**Re-verificado en vivo:** 15 intentos → exactamente 10 pasan, los 5 siguientes 429.

### A-2 — Los `.htaccess` anti-ejecución de `banners/`/`brands/` no viajaban en git

**Verificado:** `git ls-files` confirma que `site/assets/img/productos/.htaccess` está
versionado, pero `banners/.htaccess` y `brands/.htaccess` **no** (`.gitignore:35-36`
ignoraba las carpetas enteras). Son exactamente las carpetas donde escriben
`banners/upload-image.php` y `brands/upload-image.php`. Un despliegue reconstruido desde
git (o un clon nuevo) quedaba sin el guard `php_flag engine off` + `Require all denied`
en dos de las tres carpetas de subida.

**Arreglo:** `.gitignore` cambiado a ignorar el contenido pero no el `.htaccess`
(`site/assets/img/banners/*` + `!site/assets/img/banners/.htaccess`, ídem `brands`), y
los dos archivos se agregaron a git con `git add -f`.

### A-3 — Rate limits evadibles con peticiones en paralelo (check-then-act sin lock)

**Verificado por código, no reproducible en vivo en este entorno** (el servidor de
desarrollo `php -S` de este sandbox procesa una conexión a la vez — 15 peticiones
paralelas contra `password-forgot.php` dieron exactamente 5×200 + 10×429, es decir el
servidor las serializó él solo y el resultado no distingue "atómico" de "no atómico".
Esto no prueba que el código estuviera bien: la ausencia de carrera es un artefacto del
servidor de pruebas, no del fix). Por lectura de código, la raíz era real: `RateLimit.php`
hacía `SELECT COUNT(*)` y luego `INSERT` sin nada entre medio — dos conexiones
concurrentes (Apache/PHP-FPM real sí las sirve en paralelo) podían leer el mismo contador
por debajo del umbral y pasar las dos.

**Arreglo:** `GET_LOCK()` de MySQL por clave antes de contar+insertar:
- `ds_rate_limit_ip()`: lock por `(acción+IP)`, liberado explícitamente tras insertar (o
  antes del `ds_json_error` si el límite ya se alcanzó).
- `ds_login_throttle_check()`: lock por `(tipo+email)`, **sin liberar explícitamente** —
  se libera solo cuando la conexión de esa petición se cierra (fin de la petición), que
  es justo después de que `ds_login_record()` (llamada más adelante, tras el
  `password_verify`) ya insertó el intento. Cubre el hueco real: check → bcrypt → record
  son tres pasos separados en el tiempo dentro de la misma petición.
- Timeout de 5s en el lock: si hay contención extrema, se deja pasar la petición antes que
  colgarla — el límite es mitigación de abuso, no una garantía dura.

**Verificado funcionalmente** (no bajo carga, por la limitación de entorno explicada
arriba): login normal y password-forgot normal siguen funcionando exactamente igual.

### D11 — "Olvidé mi contraseña" es un no-op silencioso con `MAIL_TRANSPORT=none`

Con el valor por defecto (`env.example.php:18`), `password-forgot.php` no genera el token
(`ds_email_enabled()` es `false`) pero responde el mismo mensaje genérico de siempre —
correcto para no revelar si el correo existe, pero un cliente real que olvide su
contraseña en un deploy sin correo configurado queda bloqueado **para siempre**, creyendo
que le llegó un correo.

**Arreglo (no cambia la respuesta al usuario, sigue siendo anti-enumeración a propósito):**
- `password-forgot.php` deja constancia en `error_log` cuando esto ocurre.
- `scripts/setup-local.php` avisa (no bloquea) si `MAIL_TRANSPORT=none` al terminar.
- `docs/project-context.md` — checklist de release: dos ítems nuevos, configurar
  `MAIL_TRANSPORT` y confirmar 2FA en todos los admins antes de exponer el panel.

### A-4 — "Perdí mi teléfono" era un candado sin llave de repuesto (encontrado en la verificación final)

**Verificado en vivo, con un admin de prueba dedicado, tras dar por cerrada la Fase 4:**
`2fa-disable.php` solo aceptaba un código TOTP vigente (`ds_totp_verify`), nunca un código
de recuperación — a diferencia de `login.php`, que sí acepta cualquiera de los dos. Un
admin que pierde su autenticador **puede iniciar sesión** con un código de recuperación
(`login.php:56-58`), pero no podía **desactivar** el 2FA perdido para enrolar uno nuevo:
`2fa-setup.php:22-24` rechaza generar un secreto mientras `totp_enabled=1`, y la única
puerta para apagarlo exigía justo el TOTP que ya no tiene. Resultado: cuenta
permanentemente inaccesible salvo por CLI (`scripts/create-admin.php` para crear otra, o
edición directa de la BD). No es una vulnerabilidad de acceso — es lo opuesto, un
bloqueo de disponibilidad para el dueño legítimo, con el mismo patrón que D11.

**Arreglo:** `2fa-disable.php` ahora acepta también un código de recuperación válido
(`ds_consume_recovery_code`), igual que `login.php`. Verificado en vivo: código inválido →
401; código de recuperación real → 2FA desactivado y admin puede re-enrolar; el mismo
código reutilizado → 401 (un solo uso, ya lo garantizaba `Recovery.php`); TOTP normal
sigue funcionando exactamente igual que antes; el rate limit de A-1 (10/15min) sigue
aplicando sobre esta ruta sin cambios.

---

## 🟡 Quick wins aplicados (coste marginal, riesgo nulo)

| Arreglo | Dónde |
|---|---|
| `esc()` en `o.estado`, `o.created_at`, `err.message` (ver corrección de arriba) | `admin/dashboard.js:38-41,46` |
| CSV injection: prefijo `'` a celdas que empiezan por `= + - @ tab CR` | `admin/products/export.php` |
| Bomba de descompresión de imagen: `getimagesize()` + techo de 40M px antes de decodificar | `lib/Image.php` |
| `.vercelignore` (`api/`, `*.php`, `*.md`, `*.sql`, `graphify-out/`) — no existía | `site/.vercelignore` (nuevo) |
| `Cache-Control: no-store` en toda respuesta de la API (`me.php` devuelve el token CSRF) | `lib/Response.php` |
| Comentarios obsoletos sobre el CDN de Tailwind del admin (ya no existe desde S5) | `.htaccess`, `site/.htaccess` |

---

## 🛡️ Prevención estructural (decisión del usuario: las tres)

### Centralización de `esc()`/`escAttr()`/`safeHref()`

Estaban copiadas **13 veces** (`esc`: 12 archivos, `escAttr`: 1, `safeHref`: 2) — código
idéntico hoy, pero 13 oportunidades de que una copia futura diverja sin que se note en el
resto. Se creó `site/assets/js/security-utils.js` (`window.DSSec.{esc,escAttr,safeHref}`),
cargado **primero** en las 20 páginas HTML (11 públicas + 9 admin); las 13 copias se
reemplazaron por `var esc = window.DSSec.esc;`.

**Se encontró y arregló un fallo real al centralizar `safeHref`**: el regex
`/^([a-z][a-z0-9+.\-]*)\s*:/i` no filtraba caracteres de control antes de mirar el
esquema, así que `"java" + TAB + "script:alert(1)"` no calzaba con el patrón y se
devolvía tal cual — el navegador ignora los caracteres de control al interpretar un
esquema, así que sí lo ejecutaría. **Verificado con un caso mínimo antes y después**:
antes, `safeHref("java\tscript:alert(1)")` devolvía la cadena intacta; ahora devuelve
`""`. Mismo endurecimiento aplicado en el servidor: `ds_clean_url()`
(`lib/Validate.php`), que alimenta banners/marcas, ahora también quita caracteres de
control y rechaza `//host` (protocolo-relativo). **No es explotable hoy** — la CSP
`script-src 'self'` ya bloquea `javascript:` — pero esa CSP depende al 100% de que el
`.htaccess` se aplique en el servidor real; esto es la segunda capa, independiente.

**Regresión verificada en vivo:** 0 errores de consola en las 15 páginas públicas y 8
páginas admin recorridas con Playwright tras el cambio.

### Escáner automático (`semgrep`)

`pip install semgrep` (verificado disponible, sin tocar el despliegue). `.semgrep.yml`
con 4 reglas, todas probadas contra casos positivos y negativos:
- `php-funcion-peligrosa` — `eval/exec/shell_exec/passthru/system/proc_open/unserialize/
  create_function/assert`.
- `php-sql-concatenado` — `$pdo->query()`/`->exec()` con concatenación en vez de
  preparado.
- `php-header-location-abierto` — `header("Location: " . $_GET/...)` (open redirect).
- `js-eval-new-function` — `eval()`/`new Function()` en JS.

Se intentó una quinta regla ("`innerHTML` sin `esc()`") con `metavariable-pattern` +
`pattern-not-regex`, pero esa combinación **no evalúa de forma fiable expresiones
multilínea** en esta versión de semgrep — un caso mínimo con `esc()` presente en el mismo
bloque igual disparaba la regla. Se descartó antes de incluirla en vez de dejar una regla
que se ignoraría por ruidosa (44 falsos positivos en el primer intento, sobre 30 líneas
reales). Queda como checklist manual en `docs/guia-seguridad.md`.

`scripts/scan-seguridad.sh` corre semgrep **más** dos comprobaciones estructurales que un
patrón AST no expresa bien (existencia de una llamada en cualquier parte del archivo):
todo endpoint bajo `site/api/admin/` llama a `ds_require_admin()` (con las 3 excepciones
deliberadas: `login.php`, `logout.php`, `me.php`), y todo endpoint que acepta POST valida
CSRF. **Verificado: pasa en verde sobre el estado final del código.**

### Guía anti-regresión

`docs/guia-seguridad.md` — 9 reglas de oro derivadas directamente de los hallazgos reales
de esta auditoría (no genéricas), con el patrón de código a copiar y el porqué, más un
checklist de PR.

---

## Recomendaciones NO implementadas (fuera del alcance aprobado: solo Crítico/Alto)

Documentadas con severidad, `ruta:línea` y arreglo propuesto para una futura sesión.

### Criptografía y cuentas
- **Códigos de recuperación 2FA: 40 bits, SHA-256 sin sal** (`lib/Recovery.php:24,27`).
  Offline (dump filtrado) se agotan en horas con GPU. Subir a `random_bytes(10)` (80 bits)
  y usar `password_hash()` en vez de `hash('sha256')`.
- **`totp_secret` en claro en BD** (`sql/schema.sql:13`). Un dump filtrado entrega el
  secreto TOTP completo de cada admin. Cifrar con `openssl_encrypt` usando una clave en
  `env.php` (fuera de la BD).
- **Política de contraseñas: solo `>= 8` caracteres**, sin lista de comunes
  (`register.php:31`, `password-reset.php:31`). Agregar un array de ~200 contraseñas
  comunes es barato y sube el piso real.
- **Sin bloqueo real de cuenta**: ventana deslizante permite ~1440 intentos/día contra
  una cuenta (`RateLimit.php:16`). Backoff exponencial o aviso por correo tras N fallos.
- **`password_hash` con coste por defecto (bcrypt 10), sin `password_needs_rehash`**
  (`register.php:46`, `password-reset.php:53`, `scripts/create-admin.php:41`). Subir a
  coste 12 y re-hashear en el próximo login exitoso.
- **TOTP reusable ~90s** (sin anti-replay) — `lib/Totp.php:65-79`. Guardar el último paso
  consumido por admin.
- **Sin cambio de contraseña admin por HTTP**, y `admins` no tiene `password_changed_at`
  — a diferencia de `users`, rotar la contraseña de un admin por CLI no expulsa sus
  sesiones activas.

### Exposición y despliegue
- **`env.php` depende 100% del `.htaccess`** para no servirse en claro. Moverlo fuera del
  docroot (p. ej. `../private/env.php`) elimina el punto único de fallo por completo.
- **`graphify-out/` dentro del docroot**, protegido solo por una `RewriteRule` (el
  `FilesMatch` de `site/.htaccess` no cubre `.json`). Sacarlo del docroot.
- **`php_flag engine off` es de mod_php**: bajo LiteSpeed (el stack real de Hostinger) o
  no hace nada o devuelve 500. La protección real ahí es el `Require all denied`, que sí
  funciona en cualquier servidor Apache.
- **Guard `defined('DS_BOOTSTRAPPED')` ausente** al tope de `lib/*.php` — segunda capa por
  si el `.htaccess` de `lib/` no se aplica.
- **`AdminDS2026` versionada** en `iniciar-tienda.bat:13` (contraseña de MySQL local) y
  como default en `setup-local.php:30-31`. Generar aleatoria y no dejar default.

### Auditoría (`admin_audit_log`)
Es **write-only**: nadie la lee (no hay pantalla ni endpoint), solo registra POST (los
GET, incluido el volcado de BD antes de este fix, no quedaban registrados), `detalle` casi
siempre `null`, registra peticiones rechazadas como si fueran acciones exitosas, y usa
`REMOTE_ADDR` en vez de `ds_client_ip()` (tras proxy, todo queda con la IP del proxy).
Construir al menos una pantalla de solo-lectura en el panel sería el mayor retorno.

### Rendimiento / DoS
- `products/list.php` público sin `LIMIT` ni rate limit, `LIKE '%…%'` sin techo de
  longitud de búsqueda.
- Exports admin sin `LIMIT`; `backup/export.php` con N+1 y todo en memoria antes de
  responder.
- `$page` sin techo en los listados admin (`products/list.php`, `orders/list.php`).

### Legal — LFPDPPP (la recomendación con más urgencia de negocio de esta lista)
- **No existe página de aviso de privacidad** — `registro.html:82` enlaza a `href="#"`.
- El checkout de invitado captura nombre, teléfono y domicilio completo **sin ningún
  texto de privacidad ni casilla de consentimiento** (`pedido.html`).
- Sin borrado de cuenta, sin vía para ejercer derechos ARCO, retención infinita de PII.

### Resiliencia
`scripts/backup-db.sh` es sólido pero **opcional y manual de activar** (nada verifica que
el cron corra), **no sale del servidor** por defecto, y **no incluye las imágenes
subidas** (`assets/img/{productos,brands,banners}/`).

---

## Confirmado seguro — no se tocó

Verificado por los 3 agentes de descubrimiento y por mí mismo al leer el código:

- **Mass assignment: cero casos** en los 61 endpoints. Todo extrae campo por campo con
  listas de columnas fijas; los únicos bucles sobre datos del cliente mapean encabezados
  de CSV a índices, nunca construyen SQL.
- **Path traversal, SSRF, ejecución de código, redirección abierta (fuera del patrón
  ahora cubierto por semgrep), inyección de cabeceras de correo, ReDoS: limpio.** Cero
  `eval/exec/curl/unserialize` fuera de lo ya inventariado; el único `header('Location:')`
  preexistente usa `ds_app_url()`; `mail()` valida destinatario con
  `FILTER_VALIDATE_EMAIL`.
- **Subida de archivos: sólida.** El nombre del archivo del usuario nunca toca el
  filesystem (nombre aleatorio `random_bytes(12)`), MIME real triple-verificado,
  re-encode GD obligatorio con rechazo 422 si falla.
- **Secretos: `env.php` nunca se commiteó** (verificado sobre todo el historial de git).
- **Aleatoriedad correcta en todo lo sensible**: cero `mt_rand`/`uniqid`/`md5`/`sha1` en
  la API, `hash_equals` donde toca, anti-oráculo de timing en login.
- **CSP idéntica** en storefront, admin y `vercel.json` — sin `unsafe-eval`, sin
  `unsafe-inline` en `script-src`.
- **Sin gestión de admins por HTTP** (solo CLI) → sin escalada de privilegios entre
  admins, no hay roles que asignar.
- **Sin scripts de terceros por CDN** en ninguna de las 20 páginas.

## Fuera de alcance

Rediseño visual. Redacción del aviso de privacidad (se señala el riesgo, el texto es
decisión del negocio).
