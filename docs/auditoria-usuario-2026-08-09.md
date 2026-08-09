# Auditoría del lado de usuario — YOWI/DS (2026-08-09)

Alcance: código, seguridad y flujo de compra del **lado público** (`site/*.html`,
`site/assets/js/*` excluyendo `admin/`, `site/api/*` excluyendo `api/admin/`). El panel
admin ya se auditó por separado el mismo día (34/34 pruebas, 0 bugs, botones unificados) y
no se re-audita aquí, salvo `admin/orders/update-status.php`, que se tocó porque el
hallazgo principal lo exigía.

Método: 3 agentes en paralelo reproduciendo en vivo (peticiones HTTP reales + Playwright
contra el servidor real, no solo lectura de código) cada hallazgo antes de tocar nada,
sobre un grafo de conocimiento actualizado (`graphify update`) y datos de prueba sembrados
a propósito (`[AUDIT-U]`: producto con 4 sabores de stock conocido, 2 usuarios reales).
Se corrigió lo Crítico/Alto/Medio; lo Bajo/Informativo quedó documentado.

Resumen de estados:

| # | Sev | Estado | Núcleo del arreglo |
|---|-----|--------|--------------------|
| H1 | **CRÍTICO** | CONFIRMADO Y CERRADO | Tope de cantidad + cancelar repone stock (recuperable, no irreversible) |
| H2 | ALTO | CONFIRMADO Y CERRADO | CORS: dominio placeholder de Vercel comentado |
| H3 | ALTO | CONFIRMADO Y CERRADO | Servidor devuelve ajustes; front reconstruye el WhatsApp desde ahí |
| H4 | ALTO | CONFIRMADO (matizado) Y CERRADO | Botón deshabilitado durante el POST |
| M1 | MEDIO | CONFIRMADO Y CERRADO | Validación de servidor del checkout (teléfono/dirección/ciudad) |
| M2 | MEDIO | CONFIRMADO Y CERRADO | Orden determinista de bloqueo (`producto_id`, `sabor_id`) |
| M3 | MEDIO | CONFIRMADO Y CERRADO | `UNIX_TIMESTAMP()` en vez de comparar `NOW()` de MySQL con `time()` de PHP |
| M4 | MEDIO | CONFIRMADO Y CERRADO | Cookie `Secure` también mira `X-Forwarded-Proto` |
| M5 | MEDIO | CONFIRMADO Y CERRADO | `settings/get.php` con allowlist explícita |
| M6 | MEDIO | CONFIRMADO Y CERRADO | `site/api/lib/.htaccess` (denegar todo, igual que `config/`) |
| M7 | MEDIO | CONFIRMADO Y CERRADO | `enhance.js` respeta un `href` de WhatsApp ya armado |
| M8 | MEDIO | CONFIRMADO Y CERRADO | `vercel.json` con la misma CSP/HSTS que Hostinger |
| M9 | MEDIO | CONFIRMADO — no se tocó (decisión razonada) | Enumeración de correo en `register.php` |
| B1–B3 | BAJO | CONFIRMADO Y CERRADO | Rate limit en `password-reset.php`, `favorites/toggle.php`, `addresses/delete.php` |
| B4 | BAJO | CONFIRMADO Y CERRADO | `graphify-out/` bloqueado también en `site/.htaccess` |
| B5 | BAJO | CONFIRMADO Y CERRADO | `localStorage` bloqueado: se avisa en vez de mentir con el badge |
| — | — | **CONFIRMADO SEGURO** | IDOR (direcciones, pedidos, favoritos), CSRF, inyección SQL, XSS |

---

## [H1] CRÍTICO — Un anónimo podía vaciar TODO el inventario con una sola petición

**Reproducido y confirmado con evidencia real** (no teórico): con un cookie jar limpio
(sin cuenta), un `GET /api/products/list.php` para leer los ids de sabor y su stock, y
**un solo** `POST /api/orders/create.php` pidiendo `cantidad: 999999` de cada sabor del
catálogo:

```
Chocolate Alto Stock (stock=10)  → HTTP 201, cantidad final: 10
Vainilla Stock Uno   (stock=1)   → HTTP 201, cantidad final: 1
Fresa Agotada        (stock=0)   → descartada en silencio, pedido igual con 201
Mango Precio Propio  (stock=20)  → HTTP 201, cantidad final: 20
```

Stock después: **0/0/0/0** en los 4 sabores. El servidor nunca rechazó nada: topaba la
cantidad absurda al stock disponible y aceptaba el pedido igual. El rate limit de 20
pedidos/hora no ayudaba — **basta 1 petición**.

**Por qué era crítico, no solo molesto**: `admin/orders/update-status.php` (antes de este
arreglo) solo hacía `UPDATE orders SET estado = ?` al cancelar — **nunca reponía el
stock**. El daño era permanente: el admin tenía que corregir los números de inventario a
mano, producto por producto.

### Arreglo (3 piezas, coordinadas)

1. **Tope duro de 100 unidades por línea** (`orders/create.php`, constante
   `DS_MAX_QTY_PER_LINE`). Sinceridad sobre su alcance: con inventarios chicos (≤100,
   típico en este catálogo) el tope **por sí solo no impide** vaciar un producto en una
   petición — sigue drenando lo que haya. Lo que de verdad cambia el resultado es la
   pieza 2.
2. **Cancelar un pedido repone el stock**, de forma **idempotente**
   (`admin/orders/update-status.php`): cancelar transforma el daño de *irreversible* a
   *recuperable en un clic*. Verificado el ciclo completo:
   - Cancelar la primera vez → stock vuelve exacto a como estaba.
   - Cancelar de nuevo (ya estaba cancelado) → **no** repone doble.
   - Reactivar (cancelado → pendiente) → vuelve a descontar, sin dejar unidades
     "flotando" duplicadas.
   - Requirió una migración nueva: `order_items.sabor_id` (antes solo se guardaba el
     **nombre** del sabor como texto — sin el id no hay forma de saber a qué fila de
     `product_flavors` reponerle) y `orders.stock_repuesto` (marca si esta cancelación
     concreta ya repuso, para la idempotencia).
3. **El servidor ya no oculta lo que ajustó** (ver H3 abajo) — el admin ve exactamente
   qué se vendió y puede decidir con esa información.

### Verificación final (re-ejecutando el ataque original tal cual, después del arreglo)

```
POST idéntico (cantidad:999999 × 4 sabores) → sigue drenando el stock a 0/0/0/0
                                                (el tope de 100 no lo impide solo)
Admin cancela el pedido resultante           → stock vuelve EXACTO: 10/1/0/20
```

Confirmado: el ataque sigue siendo posible (limitación reconocida, ver nota de alcance
arriba), pero **deja de ser destructivo**. Cerrar el vaciado del todo requeriría exigir
cuenta para comprar — el usuario decidió explícitamente no meter esa fricción a la venta.

---

## [H2] ALTO — CORS con un dominio placeholder activo y reclamable

`site/api/.htaccess:22` dejaba **sin comentar** `SetEnvIf Origin
"^https://tu-tienda\.vercel\.app$" DS_CORS_ORIGIN=$0`, junto con
`Access-Control-Allow-Credentials: true`. `tu-tienda.vercel.app` no le pertenece al
operador — cualquiera puede registrar ese subdominio exacto en Vercel. Si algún día se
activa el modo cross-site (`DS_CROSS_SITE=1`), ese dominio ajeno quedaría autorizado a
leer `me.php` (datos de usuario + token CSRF) de cualquier visitante logueado, incluido un
admin — y la regla cubre también `/api/admin/*`.

**Arreglo**: se comenta, igual que los otros ejemplos del mismo archivo (que ya estaban
comentados correctamente). Inerte hasta que el operador lo configure con su dominio real.

---

## [H3] ALTO — El WhatsApp que llega al vendedor podía no coincidir con el pedido guardado

`orders/create.php` descartaba items agotados y recortaba cantidades **en silencio**
(sin decirle nada al cliente), y el mensaje de WhatsApp lo armaba el **navegador del
cliente** con lo que había en su carrito local — no con lo que el servidor realmente
guardó. Un cliente podía pedir 5, ver "pedido registrado", y el WhatsApp que llegaba decía
5 mientras la base de datos tenía 1 (o el producto ni aparecía).

**Arreglo** (decisión del usuario: *"ajustar e informar"*, no *"todo o nada"*):
`orders/create.php` ahora devuelve, junto con `order_id`, los **`items`** realmente
guardados y los **`ajustes`** hechos (motivo + cantidad pedida vs. final, con nombre
legible). `cart.js` reconstruye el mensaje de WhatsApp **desde esa respuesta**, no desde
el carrito local, y muestra un aviso claro: *"Ajustamos tu pedido: Fresa Agotada está
agotado y se quitó de tu pedido."* Verificado en vivo con Playwright: el aviso aparece
correctamente y el mensaje reconstruido coincide con lo guardado.

También se corrigió el caso "todo el carrito se descartó": antes el error era el genérico
`"El carrito está vacío"` mientras el cliente veía su producto en pantalla (contradictorio
— hallazgo de UX de uno de los agentes). Ahora el error trae el detalle de por qué (ej.
*"Chocolate está agotado y se quitó de tu pedido."*).

---

## [H4] ALTO — Doble clic creaba pedidos duplicados con doble descuento de stock

**Confirmado con matiz real** (reproducido con dos peticiones concurrentes reales):
- Cuando el stock alcanzaba para ambos clics (ej. stock=10, 2 unidades cada uno): **sí**
  se crearon dos pedidos separados, con doble descuento (10→6).
- Cuando el stock estaba justo en el límite (stock=1, 2 unidades cada clic): la
  transacción (`FOR UPDATE` + guardia `stock >= ?`) protegió correctamente — una orden
  se creó, la otra fue rechazada con `"El carrito está vacío"` — pero ese mensaje era
  confuso para el segundo cliente.

**Arreglo**: `cart.js` deshabilita `#submit-order-btn` en cuanto se dispara el envío
(antes del `fetch`), y lo reactiva solo si el POST falla (para poder reintentar). Probado
con dos `.click()` síncronos en el mismo tick de JS (el peor caso real): **solo 1**
petición llegó al servidor.

---

## Hallazgos medios cerrados

- **[M1] Validación de servidor del checkout** — antes solo `nombre_cliente` era
  obligatorio; un POST directo podía crear pedidos sin teléfono ni dirección, imposibles
  de contactar o surtir. Ahora `orders/create.php` exige teléfono (10 dígitos),
  `direccion_envio` y `ciudad`, igual que ya exigía el cliente.
- **[M2] Deadlock por orden de bloqueo variable** — dos pedidos concurrentes con el mismo
  producto/sabor en distinto orden en el carrito (`[P5,P9]` vs `[P9,P5]`) podían
  deadlockear entre sí en MariaDB. Se ordena `$requested` por `(producto_id, sabor_id)`
  antes de adquirir los `FOR UPDATE`, garantizando el mismo orden siempre.
- **[M3] Zona horaria en la invalidación de sesión tras reset de contraseña** —
  `ds_session_check_password_change()` comparaba un string `NOW()` de MySQL (interpretado
  con la zona horaria de **PHP** vía `strtotime()`) contra `time()` de PHP (siempre UTC).
  En hosting compartido con PHP y MySQL en zonas distintas, una sesión robada podía
  sobrevivir horas después de que la víctima cambiara su contraseña. Se cambió a
  `SELECT UNIX_TIMESTAMP(password_changed_at)`, resuelto **dentro** de MySQL con su propia
  zona de sesión — comparación ahora exacta (verificado: MySQL y PHP epoch coinciden al
  segundo en este entorno).
- **[M4] Cookie de sesión sin `Secure` garantizado tras proxy TLS** — `Session.php` solo
  miraba `$_SERVER['HTTPS']`, que puede llegar vacío detrás de un proxy/CDN con TLS
  terminado antes (Cloudflare, etc.), aunque los `.htaccess` del proyecto ya asumen ese
  escenario para el redirect a HTTPS. Ahora también mira `X-Forwarded-Proto`.
- **[M5] `settings/get.php` publicaba toda la tabla sin allowlist** — hoy son 12 claves de
  contenido inocuo, pero cualquier clave futura insertada directo en la tabla (fuera del
  panel admin) se habría publicado sola. Se agregó la misma allowlist de 12 claves que ya
  tiene `admin/settings/save.php` del lado de escritura.
- **[M6] `site/api/lib/` sin `.htaccess`** — a diferencia de `api/config/`, era alcanzable
  por HTTP directo pese a ser solo librerías internas (`require`, nunca pensadas para
  pedirse solas). Se agregó el mismo `Require all denied` que ya usa `config/`.
- **[M7] `enhance.js` secuestraba cualquier enlace de WhatsApp** — el listener genérico
  interceptaba **cualquier** botón/enlace con "WhatsApp" en el texto, incluso uno que ya
  traía su propio mensaje armado (el "Comprar por WhatsApp" de la ficha de producto,
  `producto.html:108`, que además nunca tuvo datos del producto — era un `href` estático
  sin `text=`). Ahora respeta un `href="https://wa.me/...?text=..."` ya armado, y la ficha
  de producto arma ese mensaje dinámicamente con el producto/sabor/precio real
  (verificado: cambia correctamente al elegir un sabor distinto).
- **[M8] `vercel.json` sin CSP ni HSTS** — la ruta de despliegue en Vercel (frontend
  separado del backend) solo traía 3 cabeceras; se igualó al mismo juego que ya tenía
  Hostinger (`site/.htaccess`).
- **[M9] Enumeración de correo en el registro — confirmado, no se tocó.** `register.php`
  responde `409 "El correo ya está registrado"` de forma explícita, lo que técnicamente
  contradice el anti-enumeración deliberado de `password-forgot.php` (respuesta genérica
  siempre). **Decisión razonada de no arreglarlo en esta pasada**: revelar que un correo
  ya tiene cuenta al registrarse es una práctica extremadamente común y aceptada en la
  industria (Gmail, Twitter, etc. lo hacen), ya está mitigado por rate limit
  (10 intentos/hora/IP — limita la enumeración a 10 correos/hora), y una corrección
  "correcta" (responder siempre como éxito) dejaría al usuario en un estado de "éxito"
  sin sesión real, una regresión de UX peor que el hallazgo. Documentado para que el
  usuario decida si quiere cerrarlo con más trabajo (ej. notificación al dueño real del
  correo) en el futuro.

## Hallazgos bajos cerrados

- **[B1–B3] Sin rate limit** en `password-reset.php`, `favorites/toggle.php` y
  `addresses/delete.php` (los únicos endpoints de escritura sin ninguno). Se agregó
  `ds_rate_limit_ip`: 20/hora para reset (el token de 256 bits ya hacía inviable la fuerza
  bruta; esto es defensa en profundidad), 120/10min para favoritos (uso normal es
  frecuente), 40/hora para direcciones (simétrico con `save.php`, que ya lo tenía).
- **[B4] `site/graphify-out/`** (mapa completo del código: rutas, dependencias,
  comunidades) sin bloquear en `site/.htaccess` — solo la raíz lo bloqueaba. Relevante si
  se despliega por FTP/zip en vez de git (la carpeta está en `.gitignore`, así que nunca
  llega por git, pero eso no protege un despliegue manual). Se agregó
  `RewriteRule ^graphify-out/ - [F,L]`.
- **[B5] Fallo silencioso cuando `localStorage` está bloqueado** (modo privado, cuota
  llena) — encontrado por el agente de flujo real: el badge del carrito subía a "1" sin
  ningún error, pero nada persistía; al navegar a `pedido.html` el carrito aparecía vacío
  sin explicación. `saveCart()` ahora devuelve si de verdad persistió, y si no, se avisa
  al cliente en el momento de agregar el producto (no genérico ni tardío).

## Confirmado seguro — no se tocó

Verificado con pruebas reales, no solo lectura de código:

- **IDOR: limpio en los 3 frentes probados.** Con 2 usuarios reales (A y B): A creó una
  dirección, un pedido y un favorito; B no pudo verlos, editarlos ni borrarlos
  (`addresses/save.php` y `delete.php` responden "no encontrada" con un id ajeno;
  `orders/list.php` y `favorites/list.php` solo filtran por `user_id` de sesión, sin
  aceptar ningún id del cliente).
- **CSRF: cobertura completa** en los 8 endpoints públicos de escritura.
- **Inyección SQL: limpio.** Sentencias preparadas en las 20 rutas públicas.
- **XSS: bien.** Los ~30 usos de `innerHTML` pasan por `esc()`; cero `<script>` inline.
- **Precio y stock siempre recalculados en el servidor** — el cliente no puede alterar
  importes ni forzar una compra sin el precio real del sabor elegido.
- **`ds_logout_user()` no destruye la sesión si hay `admin_id`** — verificado que es
  deliberado (simétrico con `ds_logout_admin`, cliente y admin comparten navegador sin
  pisarse). No es un bug.

## Verificación end-to-end (después de todos los arreglos)

- Catálogo real cargando (sin banner de "vista previa"/modo demo).
- Ficha de producto: sabores (incluido uno agotado, deshabilitado correctamente) y precio
  por sabor (`$650.00 MXN` al elegir el sabor con precio propio) funcionando.
- Compra completa como invitado y como usuario logueado, con las validaciones nuevas del
  servidor — 0 regresión.
- Registro → auto-login → `cuenta.html`; favoritos; guardar una dirección;
  recuperar contraseña (respuesta genérica) — los 5 pasos verificados con Playwright,
  todos exitosos.
- Panel admin: dashboard, listado de pedidos, cambio de estado vía UI (usa el
  `update-status.php` modificado), listado de productos — sin regresión, 0 errores de
  consola.
- 0 errores de consola reales en ningún recorrido (los únicos "errores" vistos son
  bloqueos de red a `fonts.googleapis.com`, propios de este sandbox de pruebas y ausentes
  en producción).

## Limpieza

Todos los datos `[AUDIT-U]` (1 producto con sabores/galería, pedidos de prueba, usuarios
de prueba, intentos de rate-limit acumulados) fueron eliminados. BD verificada de vuelta
a la línea base: 24 productos, 4 categorías, 0 pedidos, 0 usuarios de prueba.
