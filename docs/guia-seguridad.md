# Guía de seguridad — reglas de oro para no reabrir lo que ya se cerró

Este documento existe porque el proyecto ya tuvo tres rondas de hallazgos serios
(`docs/seguridad-fixes-2026-08-02.md`, `docs/auditoria-usuario-2026-08-09.md`,
`docs/auditoria-seguridad-2026-08-09.md`) y **el mismo tipo de error reapareció más de
una vez** con formas distintas. Esto no es teoría: son las reglas concretas que, de
haberse seguido desde el principio, habrían evitado esos hallazgos.

Antes de escribir un endpoint nuevo o tocar uno existente, lee esta lista. Antes de dar
por terminado un cambio, corre `scripts/scan-seguridad.sh`.

---

## 1. Todo endpoint que toque datos de admin usa el guardián único

```php
$adminId = ds_require_admin();          // exige sesión + 2FA activo — solo self-service (auth/)
$adminId = ds_require_admin(true);      // exige SOLO sesión (2FA aún no enrolado)
$adminId = ds_require_rol(DS_ROL_LECTURA);   // exige sesión + 2FA + rol >= lectura
$adminId = ds_require_rol(DS_ROL_OPERADOR);  // exige sesión + 2FA + rol >= operador
$adminId = ds_require_rol(DS_ROL_DUENO);     // exige sesión + 2FA + rol >= dueno
```

**Nunca** compruebes `$_SESSION['admin_id']` a mano, y **nunca** repliques la lógica de
"¿tiene 2FA?" en un endpoint nuevo. La razón de que esto sea una regla y no una
sugerencia: el hallazgo más grave de la auditoría de seguridad (2026-08-09) fue
exactamente esto — el enforcement de 2FA vivía escondido dentro de `ds_admin_csrf_check`,
que solo corría en POST, así que **ningún GET** (incluido el volcado completo de la BD)
pasaba por ahí. Un admin sin 2FA enrolado bastaba con su contraseña para leerlo todo y
tomar la cuenta. Ahora `ds_require_admin()` es el ÚNICO lugar que decide esto — un
cambio ahí arregla los ~40 endpoints a la vez, y un endpoint nuevo lo hereda gratis con
solo llamar la función.

`$permitirSinEnrolar = true` es **solo** para los endpoints que un admin necesita antes
de tener 2FA (enrolarlo, ver su estado, regenerar códigos de recuperación). Si dudas si tu
endpoint nuevo necesita esa excepción: no la necesita.

**Todo endpoint de negocio (fuera de `site/api/admin/auth/`) usa `ds_require_rol()`, no
`ds_require_admin()` a secas.** `ds_require_admin()` sin rol solo es correcto en
`auth/*.php` (login, logout, me, los `2fa-*`, `change-password`) — son self-service,
cualquier rol los puede usar. Cualquier otro endpoint nuevo (un CRUD de algo, un reporte,
lo que sea) **debe** declarar su rol mínimo explícitamente, aunque creas que "cualquiera
debería poder verlo": si de verdad cualquiera debe poder, eso es `DS_ROL_LECTURA`, dicho a
propósito, no un guardián sin rol. `scripts/scan-seguridad.sh` falla el build si un
endpoint de negocio nuevo no declara rol — es la misma idea que el punto 1: que un olvido
no abra un hueco por defecto.

## 1b. Los 3 roles son jerárquicos y viven en un solo lugar

`dueno` (3) > `operador` (2) > `lectura` (1), comparados con `ds_rol_nivel()` en
`AdminSession.php`. Un valor de rol que no esté en la whitelist (dato corrupto, un typo,
un rol futuro que el código todavía no reconoce) da nivel **0** — el efecto es bloqueo,
nunca acceso. No inventes una comparación de string (`$rol === 'dueno' || $rol ===
'operador'`) en un endpoint nuevo: usa `ds_require_rol()`, que ya hace la comparación
correcta y además exige sesión + 2FA.

Si un endpoint nuevo hace una operación destructiva (borrado masivo, exportar todo,
tocar la cuenta de otro admin), el rol mínimo es `DS_ROL_DUENO` salvo que tengas una razón
concreta para no serlo — ver `docs/roles-y-permisos.md` para qué zona le toca a cada rol.

## 2. Todo POST de escritura valida CSRF con el token correcto

```php
$body = ds_read_json_body();
ds_csrf_check($body['csrf_token'] ?? null);          // lado usuario
ds_admin_csrf_check($body['csrf_token'] ?? null);     // lado admin
```

Son tokens **distintos** por diseño (sesión de cliente vs. sesión de admin pueden
coexistir en el mismo navegador). No mezclarlos.

## 3. Todo campo que el cliente puede mandar tiene una allowlist, nunca un bucle sobre `$_POST`

El proyecto tiene cero casos de mass assignment porque cada endpoint extrae campo por
campo a variables tipadas (`ds_to_positive_int`, `ds_clean_string`, `ds_validate_email`,
`ds_clean_url`...) y arma el SQL con columnas fijas. Si tu endpoint nuevo hace
`foreach ($_POST as $k => $v)` para construir un `UPDATE`, algo salió mal — vuelve a la
lista explícita.

## 4. Cuidado con "aplicar el límite por línea" cuando el cliente controla cuántas líneas manda

Este es el segundo hallazgo grave de la auditoría de seguridad: el tope de 100 unidades
por línea de `orders/create.php` (agregado para cerrar H1 el mismo día) se evadió
repitiendo la misma línea 5 veces — el tope se aplicaba **por elemento del array**, no
por producto, y nada limitaba cuántos elementos podía traer el array. La regla concreta:

- Si vas a limitar "cantidad de X por Y", **agrega primero por Y** (aquí:
  `producto_id|sabor_id`) y aplica el límite al total agregado, no a cada entrada cruda
  del array del cliente.
- Si el cliente manda un array, ponle un techo al número de elementos (aquí: 50 líneas)
  **antes** de procesarlo. Sin techo, también es una forma barata de mantener una
  transacción abierta con locks sobre medio catálogo durante minutos.

## 5. Todo límite de tasa (`ds_rate_limit_ip`, `ds_login_throttle_check`) asume que puede llegar en paralelo

Un `SELECT COUNT(*)` seguido de un `INSERT`, sin nada entre medio, dejaba pasar tantas
peticiones como llegaran simultáneamente por debajo del umbral — 50 peticiones en
paralelo hacían ~50 intentos de contraseña en vez de 5. Los dos helpers de
`lib/RateLimit.php` ya usan `GET_LOCK()` para serializar peticiones que compiten por la
misma clave; si escribes un límite nuevo que NO reutiliza esos helpers, necesita la misma
protección.

## 6. `esc()` / `escAttr()` / `safeHref()` viven en UN solo lugar: `window.DSSec`

```html
<script src="assets/js/security-utils.js" defer></script>  <!-- SIEMPRE primero -->
```
```js
var esc = window.DSSec.esc;
var safeHref = window.DSSec.safeHref;
```

Antes estaban copiadas en 13 archivos. Todas eran iguales — hasta que una no lo fue:
`safeHref` no filtraba caracteres de control, así que `java` + TAB + `script:...` la
esquivaba. Si esa copia hubiera estado en un solo archivo, arreglarla habría cerrado el
hueco en los 3 sitios que la usaban a la vez, en vez de exigir encontrar cada copia. **No
copies `esc()` a un archivo nuevo** — importa `window.DSSec.esc`.

Todo `innerHTML` que incluya cualquier dato que no sea un literal fijo en el código
(nombre de producto, cliente, dirección, lo que sea que venga de una respuesta de la API o
de `localStorage`) debe pasar por `esc()`. Esto **no está cubierto por semgrep** — se
intentó una regla automática y no fue confiable (ver la nota en `.semgrep.yml`), así que
es responsabilidad de code review manual.

## 7. Ninguna URL que el navegador vaya a usar como `href`/`src` sale directo de la BD

```php
ds_clean_url($valor)   // servidor — banners, marcas
```
```js
window.DSSec.safeHref(valor)   // cliente
```

Bloquean cualquier esquema que no sea `http`/`https` (nunca `javascript:`, `data:`,
protocolo-relativo `//host`). Redundante con la CSP (`script-src 'self'`), a propósito:
la CSP depende al 100% de que el `.htaccess` se aplique en el servidor real, así que esto
es la segunda capa, independiente de esa dependencia.

## 8. Antes de dar por cerrado un hallazgo, reprodúcelo en vivo — no te fíes del código a simple vista

La razón de que el hallazgo del punto 4 exista: el arreglo original del tope de 100 se
verificó probando "una línea con cantidad 999999" (bloqueaba correctamente) pero nunca se
probó "muchas líneas del mismo producto". Un fix que se ve correcto leyendo el diff puede
tener un camino que nadie probó. Antes de marcar un hallazgo como cerrado:
1. Reproduce el ataque tal cual antes del fix (confirma que de verdad estaba roto).
2. Repite el MISMO ataque después del fix (confirma que ahora falla/se limita).
3. Piensa en la variante obvia del ataque (¿y si repito la petición? ¿y si cambio el
   orden? ¿y si mando 2 en vez de 1?) y pruébala también.

## 9. Checklist antes de un PR que toca `site/api/`

- [ ] `php -l` en cada archivo tocado.
- [ ] `scripts/scan-seguridad.sh` en verde.
- [ ] Si es un endpoint admin nuevo fuera de `auth/`: ¿llama a `ds_require_rol()` con el
      rol mínimo correcto (no `ds_require_admin()` a secas)? ¿el POST valida CSRF?
- [ ] Si es una acción destructiva o expone datos de todos los clientes: ¿el rol mínimo es
      `DS_ROL_DUENO`?
- [ ] Si acepta un array del cliente: ¿tiene techo de elementos? ¿el límite se aplica al
      total agregado o a cada elemento crudo?
- [ ] Si construye una URL/HTML dinámico: ¿pasa por `ds_clean_url`/`esc()`/`safeHref`?
- [ ] Si agrega un límite de tasa nuevo: ¿reutiliza `ds_rate_limit_ip`/
      `ds_login_throttle_check`, o si no, tiene su propio lock?
- [ ] Si toca una tabla con una invariante tipo "siempre debe quedar al menos uno" (como
      "al menos un dueño activo"): ¿el chequeo + la escritura van en la MISMA transacción
      con `FOR UPDATE`? Un `SELECT COUNT` sin lock antes de un `UPDATE` es una condición
      de carrera — mismo error que ya se cometió una vez en esta misma sesión.
- [ ] Regresión rápida: ¿las páginas que tocaste cargan sin errores de consola?

## Qué NO cubre esta guía

- Diseño visual / UX — no es su alcance.
- Endurecimiento de infraestructura (Hostinger, `.htaccess`, backups) — ver
  `docs/seguridad-operativa.md` y `docs/seguridad-bd.md`.
- Hallazgos Medios/Bajos ya documentados pero no cerrados — ver la sección de
  recomendaciones no implementadas en `docs/auditoria-seguridad-2026-08-09.md`.
