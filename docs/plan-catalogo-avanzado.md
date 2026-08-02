# Plan de ejecución — Catálogo avanzado (SKU · Import diff · Sabores · Galería · Respaldos)

> **Para quién es este documento:** es la guía de trabajo de un **orquestador** que coordina
> **agentes en paralelo**. Cada subfase está pensada para ser asignable a un agente, con sus
> archivos, su criterio de terminado y sus dependencias explícitas.
> **Regla de coordinación:** dos agentes NUNCA editan el mismo archivo a la vez. Los carriles
> paralelos de cada fase están diseñados para no colisionar; si una tarea necesita un archivo
> de otro carril, es dependencia y va secuencial.

---

## 1. Contexto

Tienda de suplementos (DS/YOWI): PHP 8 + MariaDB en Hostinger, storefront HTML + Tailwind
compilado (`app.css`), panel admin con Tailwind auto-hospedado (`admin.css`) bajo **CSP
estricta `script-src 'self'`**. El catálogo (335 productos) se administra por CSV.

**Objetivo:** que el CSV pueda traer descripción y sabores, que cada producto tenga un **SKU**
estable para que al reimportar **solo cambie lo que cambió**, que cada producto acepte **varias
imágenes**, y que cada apartado del panel tenga **respaldo descargable y restaurable**.

### Decisiones ya tomadas por el usuario (no re-preguntar)
| Tema | Decisión |
|---|---|
| SKU | **Lo escribe el usuario en el CSV**. La primera importación se lo asigna a los 335 actuales emparejando por el método antiguo (sin duplicar). |
| Sabores | **Nombre + stock + precio**, formato CSV `Chocolate:10:899\|Vainilla:5:949\|Fresa`. |
| Elección de sabor | **El cliente lo elige desde la página** (chips en la ficha; el precio se actualiza al instante). |
| Galería | **Por CSV (rutas/URLs separadas por `\|`) + subida desde el panel.** |
| Respaldos | **Descargar y restaurar** por apartado. |

---

## 2. Reglas de oro (invariantes — ningún agente puede romperlas)

1. **Celda vacía en el CSV = "no tocar".** Aplica a descripción, imagen, imágenes, sabores y
   badge. Es lo que garantiza que reimportar nunca borre trabajo hecho a mano.
2. **La galería y los sabores viven en tablas aparte** ligadas al producto; el importador solo
   las modifica si la columna correspondiente trae contenido.
3. **"Reemplazar todo" ya no borra**: desactiva (`activo = 0`) los productos ausentes del CSV.
4. **El precio SIEMPRE se recalcula en el servidor** (nunca se confía en el cliente). Si el
   sabor tiene precio, ese manda; si no, el del producto.
5. **Semántica de inventario** (ya establecida en el proyecto): `NULL` = sin control,
   `0` = agotado, `>0` = disponible. Aplica igual a `product_flavors.stock`.
6. **CSP estricta**: todo el JS en archivos externos. **Prohibido** `<script>` inline,
   `onclick=`, `onerror=` (usar `data-*` + listeners, como ya hace `img-fallback.js`).
7. **Migraciones idempotentes** en `sql/migrations/` (se re-ejecutan en cada arranque) y
   reflejadas en `sql/schema.sql`. **Nunca** poner sentencias destructivas ahí.
8. **Seguridad de endpoints admin**: `ds_require_admin()` + `ds_admin_csrf_check()` (que ya
   enforza 2FA y registra auditoría). Validar entradas con los helpers `ds_*` existentes.
9. **No romper lo existente**: catálogo, buscador difuso, favoritos, direcciones, carrito,
   checkout, pedidos y panel deben seguir funcionando en cada entrega.

---

## 3. Mapa de dependencias

```
F0 Preparación
   └─► F1 SKU ──► F2 Import diff ──┬─► F3 Sabores ──┐
                                   └─► F4 Galería ──┴─► F6 Cierre
   └─────────────────► F5 Respaldos (paralelo desde F1) ──────┘
```

- **F1 → F2**: el diff se apoya en el SKU como clave.
- **F2 → F3/F4**: sabores y galería se importan sobre el motor de diff.
- **F3 ∥ F4**: pueden ir en paralelo **solo con reparto estricto de archivos** (ver §6).
- **F5**: independiente; puede correr en paralelo desde que F1 esté cerrada.

---

## 4. Fases y subfases

Cada subfase indica: **objetivo · archivos · dependencia · terminado (DoD)**.

### F0 — Preparación (secuencial, bloqueante)

| ID | Objetivo | Archivos | Dep. | Terminado |
|---|---|---|---|---|
| F0.1 | Respaldo completo de la BD actual antes de migrar | `scripts/backup-db.sh` (ejecutar) | — | Dump generado y **restauración probada** en BD temporal |
| F0.2 | Levantar entorno: MariaDB + `php -S` + `setup-local.php` | — | F0.1 | `curl /api/products/list.php` responde 200 |

### F1 — SKU: identidad estable

| ID | Objetivo | Archivos | Dep. | Terminado |
|---|---|---|---|---|
| F1.1 | Columna `sku VARCHAR(64) NULL` + índice UNIQUE | `sql/migrations/2026-08-03-add-sku.sql`, `sql/schema.sql` | F0 | `SHOW COLUMNS` muestra `sku`; migración re-ejecutable sin error |
| F1.2 | Emparejamiento por SKU con **fallback legacy** en el importador | `site/api/admin/products/import.php` | F1.1 | CSV con SKU sobre los 335 → **0 duplicados** y todos quedan con SKU |
| F1.3 | SKU en alta/edición y en la API de lectura | `admin/products/{create,update,list}.php`, `api/products/list.php` | F1.1 | Crear/editar con SKU funciona; SKU duplicado → error claro |
| F1.4 | **Exportar catálogo a CSV** (con SKU, formato del importador) | `site/api/admin/products/export.php` (nuevo) | F1.1 | Descarga CSV de 335 filas que el importador acepta tal cual |
| F1.5 | Panel: campo SKU en el modal, columna en la tabla, búsqueda por SKU, botón "Exportar CSV" | `site/admin/productos.html`, `site/assets/js/admin/products.js` | F1.3, F1.4 | Se ve y funciona; sin JS inline; `npm run build:css:admin` corrido |

> **Paralelo dentro de F1:** tras F1.1 → `F1.2`, `F1.3` y `F1.4` pueden ir en 3 carriles
> (archivos distintos). `F1.5` espera a F1.3 y F1.4.

### F2 — Importación inteligente (solo lo que cambió)

| ID | Objetivo | Archivos | Dep. | Terminado |
|---|---|---|---|---|
| F2.1 | Motor de **diff campo por campo**: `creado` / `actualizado(campos)` / `sin cambios` / `omitido(motivo)`; **celda vacía = no tocar** | `site/api/admin/products/import.php` | F1.2 | Reimportar el mismo CSV → todas "sin cambios"; cambiar 1 precio → solo esa fila "actualizado: precio" |
| F2.2 | Modo **vista previa** (`preview=1`): calcula el diff **sin escribir** | `import.php` | F2.1 | Con `preview=1` la BD no cambia y devuelve el mismo reporte |
| F2.3 | **"Reemplazar todo" deja de borrar** → desactiva ausentes (`activo=0`) | `import.php` | F2.1 | Tras usarlo, los ausentes quedan ocultos **conservando imágenes**; reversible |
| F2.4 | UI de importación en 2 pasos: *Analizar* → tabla con colores (verde nuevo · azul cambios · gris sin cambios · rojo omitido) + contadores → *Aplicar* | `site/admin/productos.html`, `site/assets/js/admin/products.js` | F2.2 | Flujo completo en navegador; sin JS inline |

### F3 — Sabores (nombre + stock + precio)

| ID | Objetivo | Archivos | Dep. | Terminado |
|---|---|---|---|---|
| F3.1 | Tabla `product_flavors` (`product_id` FK CASCADE, `nombre`, `slug`, `stock NULL`, `precio NULL`, `orden`, `activo`, `UNIQUE(product_id,slug)`) + `order_items.sabor` | `sql/migrations/2026-08-03-add-flavors.sql`, `sql/schema.sql` | F2 | Tablas creadas; migración idempotente |
| F3.2 | Parser CSV `nombre:stock:precio` y sincronización de sabores en el import | `site/api/admin/products/import.php` | F3.1, F2.1 | `Chocolate:10:899\|Mocha::999\|Fresa` se guarda correcto; columna vacía no toca sabores |
| F3.3 | API de sabores para el panel (listar/guardar/borrar) | `site/api/admin/products/flavors.php` (nuevo) | F3.1 | CRUD funciona con auth+CSRF |
| F3.4 | **Checkout**: precio del sabor + descuento transaccional del stock del sabor (`FOR UPDATE`, guardia `stock>=cantidad`) | `site/api/orders/create.php` | F3.1 | Comprar 3 de `Chocolate:2:899` → registra 2 a $899 y agota solo Chocolate |
| F3.5 | Lectura pública: sabores en la API + "desde $X" cuando los precios difieren | `site/api/products/list.php` | F3.1 | La ficha recibe los sabores con stock y precio |
| F3.6 | **Tienda**: chips de sabor, precio que cambia al elegir, agotados tachados, bloqueo de "Agregar" sin sabor, línea de carrito por producto+sabor, sabor en WhatsApp | `site/producto.html`, `site/assets/js/catalog-engine.js`, `site/assets/js/cart.js` | F3.5 | Recorrido completo en navegador; `npm run build:css` |
| F3.7 | **Panel**: editor de sabores en el modal (nombre + stock + precio, agregar/quitar/reordenar) | `site/admin/productos.html`, `site/assets/js/admin/products.js` | F3.3 | Alta/edición de sabores desde el panel |

> **Paralelo dentro de F3:** tras F3.1 → carriles `F3.2` · `F3.3` · `F3.4` · `F3.5`
> (4 archivos distintos). Luego `F3.6` y `F3.7` en paralelo (tienda vs. panel).

### F4 — Galería de imágenes

| ID | Objetivo | Archivos | Dep. | Terminado |
|---|---|---|---|---|
| F4.1 | Tabla `product_images` (`product_id` FK CASCADE, `url`, `orden`) | `sql/migrations/2026-08-03-add-product-images.sql`, `sql/schema.sql` | F2 | Tabla creada; `products.imagen` **se conserva** como principal |
| F4.2 | Columna `imagenes` en el import (rutas/URLs `\|`, validadas con `ds_clean_url`) | `site/api/admin/products/import.php` | F4.1, F2.1 | Con la columna **vacía**, la galería queda **intacta** (prueba clave) |
| F4.3 | API de galería para el panel (subir/listar/ordenar/borrar/principal) | `site/api/admin/products/images.php` (nuevo) | F4.1 | Reusa `upload-image.php` (ya endurecido con GD) |
| F4.4 | **Panel**: sección de galería en el modal (miniaturas, orden, eliminar, marcar principal) | `site/admin/productos.html`, `site/assets/js/admin/products.js` | F4.3 | Gestión completa desde el panel |
| F4.5 | **Tienda**: imagen grande + tira de miniaturas, accesible por teclado | `site/producto.html`, `site/assets/js/catalog-engine.js` | F4.1 | Cambia la principal al pulsar miniatura; con 1 sola imagen se ve como hoy |

> **⚠ Colisión con F3:** F4.2 toca `import.php` (igual que F3.2); F4.4 toca los mismos
> archivos de panel que F3.7; F4.5 los mismos de tienda que F3.6.
> **Regla:** F3 y F4 solo van en paralelo si se asignan por **archivo exclusivo** (ver §6).
> Si hay duda, ejecutar F3 completa y luego F4.

### F5 — Respaldos por apartado (paralelo desde F1)

| ID | Objetivo | Archivos | Dep. | Terminado |
|---|---|---|---|---|
| F5.1 | **Exportar** `?tipo=productos\|categorias\|marcas\|pedidos\|promociones\|nosotros\|todo` (JSON con fecha) | `site/api/admin/backup/export.php` (nuevo) | F1.1 | Descarga cada tipo; productos incluye SKU (y sabores/galería cuando existan) |
| F5.2 | **Restaurar** con vista previa + confirmación explícita | `site/api/admin/backup/import.php` (nuevo) | F5.1 | Borrar un producto → restaurar → vuelve completo |
| F5.3 | **Panel**: botón "Respaldar" en cada sección + "Restaurar" con diálogo; tarjeta "Respaldo completo" en Dashboard | 7 páginas `site/admin/*.html`, `site/assets/js/admin/backup.js` (nuevo) | F5.2 | Botones en las 7 secciones; sin JS inline |

### F6 — Cierre

| ID | Objetivo | Dep. |
|---|---|---|
| F6.1 | Regresión completa (catálogo, buscador, favoritos, direcciones, compra, panel, CSP sin violaciones) | F3, F4, F5 |
| F6.2 | Actualizar `docs/FUNCIONES.md` con lo nuevo y la plantilla CSV | F6.1 |
| F6.3 | Verificación final del orquestador + push a la rama y `main` | F6.2 |

---

## 5. Protocolo de trabajo (obligatorio para todos los agentes)

- **Rama:** `claude/last-push-timing-yul4fb`. **Los agentes NO hacen `git push`** — solo el
  orquestador, tras verificar.
- **Commits:** uno por subfase, mensaje en español (`F3.4: descuento de stock por sabor…`),
  terminando **exactamente** con:
  ```
  Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
  Claude-Session: https://claude.ai/code/session_016dP4AV7UDvxYB77LHi8GTS
  ```
- **Nunca** commitear `site/api/config/env.php` (gitignored). Claves nuevas → `env.example.php`.
- **Verificar antes de entregar:** `php -l` en cada PHP, `node --check` en cada JS, y la prueba
  funcional del DoD contra la MariaDB real.
- **Recompilar CSS** si se tocaron clases: `npm run build:css` (tienda) / `npm run build:css:admin` (panel).
- **Reusar** helpers existentes: `ds_get_pdo`, `ds_json_success/error`, `ds_require_admin`,
  `ds_admin_csrf_check`, `ds_clean_string/url`, `ds_to_positive_int/float`, `DSFuzzy`, `DSApi`.
- **Entregable de cada agente:** qué implementó, commits (hash corto), qué verificó con qué
  resultado, y lo que quedó dudoso.

## 6. Orquestación: quién trabaja en paralelo

**Rol del orquestador (Opus):** define el alcance de cada tanda, mantiene MariaDB + `php -S`
arriba, reparte archivos exclusivos, revisa los entregables, resuelve conflictos y hace el push.

**Reparto por archivo (evita colisiones):**

| Carril | Dueño de estos archivos |
|---|---|
| **A — Import/CSV** | `site/api/admin/products/import.php`, `export.php` |
| **B — API/backend** | `orders/create.php`, `products/list.php`, `admin/products/{create,update,list,flavors,images}.php` |
| **C — Panel** | `site/admin/*.html`, `site/assets/js/admin/*.js` |
| **D — Tienda** | `site/producto.html`, `site/assets/js/{catalog-engine,cart}.js` |
| **E — BD** | `sql/migrations/*`, `sql/schema.sql` |

**Tandas sugeridas** (cada tanda = agentes en paralelo; el orquestador cierra y verifica entre tandas):

| Tanda | En paralelo | Carriles |
|---|---|---|
| 1 | F1.1 (bloqueante, la hace el orquestador o 1 agente) | E |
| 2 | **F1.2** ∥ **F1.3 + F1.4** ∥ **F5.1** | A ∥ B ∥ backup |
| 3 | **F1.5** ∥ **F5.2** | C ∥ backup |
| 4 | **F2.1 + F2.2 + F2.3** (mismo archivo → 1 agente) ∥ **F5.3** | A ∥ C-backup |
| 5 | **F2.4** | C |
| 6 | F3.1 + F4.1 (migraciones, 1 agente) | E |
| 7 | **F3.2 + F4.2** (mismo archivo → 1 agente) ∥ **F3.3 + F3.4 + F3.5** ∥ **F4.3** | A ∥ B ∥ B2 |
| 8 | **F3.6 + F4.5** (tienda, 1 agente) ∥ **F3.7 + F4.4** (panel, 1 agente) | D ∥ C |
| 9 | F6 cierre (revisor + orquestador) | — |

> **Nota honesta:** el paralelismo real está limitado por los archivos compartidos
> (`import.php`, `products.js`, `producto.html`). Por eso varias subfases se agrupan en un
> mismo agente: **es más rápido que resolver conflictos de edición**.

## 7. Verificación por fase (criterios de aceptación)

| Fase | Prueba clave |
|---|---|
| F1 | Importar el CSV con SKU sobre los 335 → **0 duplicados**; cambiar el nombre de un producto en el CSV y reimportar → **actualiza**, no duplica |
| F2 | Reimportar sin cambios → todas "sin cambios"; cambiar 1 precio → solo esa fila reporta "precio"; `preview=1` no escribe |
| F3 | `Chocolate:2:899\|Vainilla:5:949`: elegir Chocolate cambia el precio a $899; comprar 3 → registra **2 a $899**, Chocolate agotado, Vainilla intacta; el sabor sale en el pedido y en WhatsApp |
| F4 | **Con la columna `imagenes` vacía, reimportar → la galería sigue intacta** (la prueba que responde la duda del usuario) |
| F5 | Descargar productos → borrar uno → restaurar → vuelve completo (con sabores y galería) |
| F6 | Sin regresiones y **0 violaciones de CSP** en consola (tienda y panel) |

## 8. Estado

- [x] **F1.1** — migración `2026-08-03-add-sku.sql` + `sql/schema.sql` (hecho al iniciar)
- [ ] Resto de subfases pendientes
