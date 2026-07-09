# Plan de reparaciones — Distribuidor de Suplementos (DS)
> **Para el ejecutor (Sonnet):** este documento es autónomo. Repara los bugs de la auditoría
> `docs/auditoria-2026-07-05.md`. Lee TODO antes de empezar. Ejecuta por fases en orden.
> Verifica cada fase antes de pasar a la siguiente. **No rompas los ganchos JS** (sección "Contrato").

---

## 0. Contexto del proyecto
- **Stack:** HTML estático + **Tailwind CDN** (config `tailwind.config` inline en cada página) + **JS vanilla** (IIFE, `var`). **Sin build.** Backend **PHP plano + PDO + MySQL/MariaDB**. Destino: **Hostinger** (Apache+PHP+MySQL). Raíz: `C:\Users\maikg\Downloads\YOWI`.
- **Marca:** "Distribuidor de Suplementos (DS)". Paleta gimnasio: `ink #0B0F1A`, `lime #8FD11F`, `brand #1F5FD9`, `paper #F7F8FA`. Tipografía: Barlow Condensed (display) + Barlow (body).
- **14 páginas:** públicas `site/{index,catalogo,producto,pedido,marcas,nosotros,login,registro,cuenta}.html`; admin `site/admin/{index,productos,categorias,pedidos,login}.html`.

## 0.1 Entorno para probar (levantar antes de verificar)
```bash
# MariaDB (puerto 3307, si no está corriendo):
C:\Users\maikg\dev-tools\mariadb\bin\mariadbd.exe --datadir="C:\Users\maikg\dev-tools\mariadb\data" --port=3307 --bind-address=127.0.0.1 --console
# Servidor PHP:
C:\Users\maikg\dev-tools\php\php.exe -S localhost:8000 -t "C:\Users\maikg\Downloads\YOWI\site"
```
Sitio en `http://localhost:8000`. **Admin:** `admin@ds.com` / `admin2026DS`. Cliente MariaDB: `...\mariadb\bin\mariadb.exe -h 127.0.0.1 -P 3307 -u root ds_sports_supplements`.
Para capturas/inspección usa las herramientas de preview (`preview_start` config `ds-php`, `preview_inspect`, `preview_resize` mobile).

## 0.2 ⛔ Contrato — NO renombres/elimines estos ganchos que el JS usa
- **Público:** `#product-grid`, `#product-count`, `#search-input`, `<aside>` con inputs `name="categoria|precio_max|solo_disponibles"`, `#clear-filters-btn`, `#featured-products[data-limit]`, `#product-detail`, `[data-field="nombre|marca|precio|imagen|descripcion|cantidad"]`, `.add-to-cart-btn[data-product-id]`, `[data-cart-badge]`, `#cart-items`, `#cart-summary`, `[data-summary="count|total"]`, `.cart-qty-btn[data-id][data-delta]`, `.cart-remove-btn[data-id]`, `#checkout-form`, `#submit-order-btn`, forms `#login-form`/`#registro-form` con `name="email|password|nombre|telefono|confirm_password"`.
- **Admin:** `#admin-name`, `#logout-btn`, `#alert-banner`, `#pagination`, `#products-tbody`, `#categories-tbody`, `#orders-tbody`, `#stats-grid`, `#recent-orders`, `#search-q`, `#filter-cat`, `#filter-estado`, `#new-product-btn`, `#new-category-btn`, `#product-modal`, `#cat-modal`, `#modal-title`, `#modal-cancel`, `#product-form`/`#cat-form` con sus `name=...`, `#image-preview`, `#current-imagen`, `.edit-btn/.delete-btn/.status-select` con `data-id/data-page/data-nombre/data-orden/data-count`.
- **NUNCA** borres los `<script src=...>` ni `<link>` al reescribir markup. **NO** toques `site/api/**` salvo lo indicado. Preserva `id`/`name`/`data-*`.

---

## FASE 1 — Rápidos y de alto impacto

### T1 [P0] Arreglar subida de imágenes (`fileinfo` desactivada) — SISTEMA
**Causa:** `extension=fileinfo` está comentada en `C:\Users\maikg\dev-tools\php\php.ini` (línea ~940). `upload-image.php:24` usa `new finfo()` → Fatal 500 → el admin ve error.
**Pasos:**
1. En `php.ini`, cambiar `;extension=fileinfo` → `extension=fileinfo`. Reiniciar el servidor PHP.
2. **Hardening en código** (`site/api/admin/products/upload-image.php`): envolver la detección de MIME para no depender solo de `finfo`. Reemplazar el bloque que hace `new finfo(FILEINFO_MIME_TYPE); $mime=$finfo->file(...)` por una función que intente `finfo` y, si no existe la clase, use `mime_content_type()` o `getimagesize()[ 'mime']` como fallback. Mantener la whitelist (`image/jpeg|png|webp`), el límite 2 MB, el nombre aleatorio y el `.htaccess`.
**Verificar:** login admin → Productos → Nuevo → subir un JPG/PNG → guarda sin error y la imagen aparece. Probar también con la extensión desactivada (que el fallback funcione).

### T2 [P0] Aplicar Barlow Condensed en las 9 públicas — DISEÑO
**Causa:** el HTML usa clases `font-display`/`font-body` (13 usos en index, 9 páginas) pero el `tailwind.config` de las públicas define `headline-*` y **no** `display`/`body` → cae a fuente del sistema. El admin sí las define (referencia correcta).
**Pasos:** en el bloque `tailwind.config` → `theme.extend.fontFamily` de las **9 páginas públicas**, AÑADIR (sin borrar las llaves existentes):
```js
"display": ['"Barlow Condensed"', 'sans-serif'],
"body":    ['"Barlow"', 'sans-serif'],
```
**Verificar:** con `preview_inspect` sobre un elemento con clase `font-display` (ej. el `<h2>` "ELIGE TU ARMA" o el logo del header) en index/catalogo/nosotros → `font-family` debe ser `"Barlow Condensed"`. Repetir en las 9.

---

## FASE 2 — Funcionalidad de pedidos + rendimiento

### T3 [P0] Los pedidos deben guardarse en la BD (bug CSRF checkout) — SISTEMA
**Causa (verificada):** `pedido.html` carga `api-client.js, cart.js, catalog-engine.js, mobile-nav.js` pero **no `auth.js`**; `cart.js` nunca llama `DSApi.setCsrfToken()`. `api-client.js` solo adjunta `csrf_token` si se fijó antes → queda `null` → `orders/create.php` responde **403** y el pedido no se registra (y el carrito no se vacía). El fallo es silencioso (`console.warn`).
**Fix (en `site/assets/js/cart.js`):**
1. Al iniciar (dentro del `DOMContentLoaded` existente), obtener el token del cliente y fijarlo:
   ```js
   if (global.DSApi) {
     fetch("api/auth/me.php", { credentials: "same-origin" })
       .then(function(r){ return r.json(); })
       .then(function(j){ if (j && j.ok && j.data && j.data.csrf_token) global.DSApi.setCsrfToken(j.data.csrf_token); })
       .catch(function(){});
   }
   ```
   (`api/auth/me.php` devuelve `{ok:true,data:{user,csrf_token}}` — confirmado.)
2. En `submitOrder`, tras el `apiFetch` de `orders/create.php`: en `.then` ya hace `saveCart([])` (correcto); en `.catch`, **mostrar un aviso visible** al usuario (no solo `console.warn`) — p. ej. un pequeño banner/alert "No pudimos registrar tu pedido, pero tu mensaje de WhatsApp se abrió".
**No romper:** el `window.open(waUrl)` que abre WhatsApp primero, ni el `stopImmediatePropagation` del `#submit-order-btn`.
**Verificar:** en `pedido.html` con un producto en el carrito → enviar → (a) WhatsApp abre, (b) `SELECT * FROM orders` muestra el pedido nuevo, (c) el carrito queda vacío. Revisar Network: el POST a `create.php` responde **201**, no 403.

### T4 [P1] Comprimir logos + borrar assets muertos — SISTEMA
**Causa:** `logo-ds-clean.png` = **3.4 MB**, `logo-ds-blue.png` = 2.3 MB, cargados en cada página (se muestran a 36–64px). ~7.5 MB de imágenes muertas versionadas.
**Pasos (Python + PIL, ya disponible):**
1. Redimensionar y optimizar los 2 logos EN USO a ~200px de alto (sobra para retina) conservando transparencia:
   ```python
   from PIL import Image
   for n in ["logo-ds-clean","logo-ds-blue"]:
       im=Image.open("site/assets/img/"+n+".png").convert("RGBA")
       im.thumbnail((600,200), Image.LANCZOS)
       im.save("site/assets/img/"+n+".png", optimize=True)
   # favicon.png regenerar a 64x64 si hace falta (ya está)
   ```
   Objetivo: cada logo < 50 KB.
2. Borrar assets sin referencias: `site/assets/data/productos-demo.json`, `site/assets/img/logo-ds-white.png`, `site/assets/img/logo-ds-full.jpg`. Sacar del repo `LOGO_1.jpeg` y `LOGO_2.jpeg` (guardar copia fuera del repo antes; opcional moverlos a una carpeta ignorada).
**Verificar:** el logo sigue nítido en header/footer/tarjetas (revisar en preview a tamaño real y 2x); el peso de `logo-ds-clean.png` bajó a decenas de KB; ninguna página referencia los archivos borrados (grep).

---

## FASE 3 — Consistencia visual

### T5 [P0/P1] Arreglar la navegación móvil — DISEÑO
**Causa:** el drawer se inserta dentro del `<header>` (flex row) → en 375px sale como caja blanca angosta sobre el logo. Usa paleta vieja (`bg-white`, links `text-on-surface-variant`). El ícono hamburguesa es `text-primary` (#004782) sobre header `ink` → contraste **2.1:1** (falla ≥3:1).
**Ojo:** `index.html` tiene el drawer **hardcodeado**; las otras 7 públicas lo generan con `site/assets/js/mobile-nav.js`. **Arreglar AMBOS.**
**Fix:**
1. **Estructura:** el drawer debe quedar **full-width, debajo del header** (no como hijo del flex row). En `mobile-nav.js`, insertarlo como hermano del `<header>` (`header.parentNode.insertBefore(drawer, header.nextSibling)`) o darle `position` que ocupe el ancho completo bajo el header. En `index.html`, mover/ajustar el `#mobile-menu` hardcodeado igual.
2. **Paleta:** drawer `bg-ink`, borde `border-slate-800`, links `text-white hover:text-lime uppercase tracking-wide font-display`, "Ofertas" en lima. Botón hamburguesa: icono `text-white` (o `text-lime`).
3. Mantener `min-h-[44px]` en los links y el `aria-expanded` toggle.
**No romper:** `mobile-nav.js` debe seguir siendo idempotente (guard `if (document.getElementById("mobile-menu")) return;`) y leer los links de la nav de escritorio.
**Verificar:** `preview_resize` mobile 375px en index y en catalogo/producto → el menú abre **full-width bajo el header**, fondo oscuro, links legibles; medir contraste del ícono hamburguesa ≥4.5:1.

### T6 [P1] Migrar `login.html` (cliente) al estilo gimnasio — DISEÑO
**Causa:** `login.html` no tiene header gym; muestra tarjeta blanca vieja y botón azul viejo, mientras `registro.html` ya está migrada → salto visual.
**Fix:** tomar `registro.html` como referencia. Aplicar a `login.html`: el mismo `<header>` gym (`bg-ink border-b-2 border-lime` con logo + nav), la tarjeta con estética ink/lima afilada, botón `Entrar` acorde, y los links en el estilo nuevo. El logo azul dentro de la tarjeta ya está (`logo-ds-blue.png`).
**No romper:** `#login-form`, `name="email"`, `name="password"`, `data-form-error`, y los `<script>` (`api-client.js`, `auth.js`).
**Verificar:** `login.html` y `registro.html` se ven consistentes; iniciar sesión de cliente sigue funcionando.

---

## FASE 4 — WhatsApp real + limpieza de despliegue

### T7 [P0] Reemplazar el número de WhatsApp placeholder — SISTEMA
**Requiere:** el número real (pídeselo al usuario si no está). Placeholder actual: `5218330000000`.
**Ubicaciones exactas (9 archivos / 10 sitios):**
- `site/assets/js/cart.js:5` — `var WA_NUMBER = "5218330000000";` (checkout real)
- `site/assets/enhance.js:2` — URL `wa.me/5218330000000`
- Footers: `site/registro.html:218`, `site/producto.html:201` y `:232`, `site/cuenta.html:372`, `site/pedido.html:216`, `site/nosotros.html:312`, `site/marcas.html:437`, `site/login.html:291`
**Fix:** reemplazar el número en todas. Recomendado además: centralizar en una sola constante (ej. `WA_NUMBER` en `cart.js`) y que `enhance.js`/footers la usen, para no volver a tener 10 copias.
**Verificar:** cada botón/enlace de WhatsApp abre el chat al número correcto.

### T8 [P1] Resolver despliegue: quitar `vercel.json` — SISTEMA
**Causa:** `vercel.json` sugiere Vercel, que **no ejecuta PHP** → rompería backend/login/catálogo. Destino real = Hostinger LAMP.
**Fix:** eliminar `vercel.json` de la raíz. Documentar en `docs/project-context.md` que el despliegue es Hostinger (Apache+PHP+MySQL) con el checklist de release (env.php real, importar schema, seed de 336 productos, crear admin, HTTPS).
**Verificar:** no queda referencia a Vercel; el `docs/project-context.md` refleja el target correcto.

---

## FASE 5 — Pulido (P2/P3)

- **T9 [P2] Tokens de paleta vieja en JS:** en `site/assets/js/catalog-engine.js` (estados vacíos/error, ~líneas 87,98,130,158) y `mobile-nav.js`, cambiar `text-on-surface-variant` (#424751) y `text-primary` (#004782) por tokens gym (`text-slate-400` / `text-lime` / `text-brand` según contexto oscuro o claro). Corregir la clase inexistente `text-brand-container` en `nosotros.html`.
- **T10 [P2] Fallback de imagen de producto:** en `productCardHTML` (`catalog-engine.js`), añadir `onerror` a la `<img>` que ponga un placeholder (`assets/img/producto-placeholder.svg`) para evitar el icono roto.
- **T11 [P2] La API no debe filtrar stack trace PHP:** cuando la BD está caída, `ds_get_pdo()` lanza `PDOException` cruda (fuga de rutas + pantalla rota). Añadir en el bootstrap de la API (p. ej. en `site/api/lib/Response.php` o un `bootstrap.php` incluido por los endpoints) un `set_exception_handler` que responda `ds_json_error('Error del servidor', 500)` en vez del fatal. Así el front muestra su estado de error ya diseñado.
- **T12 [P3] Limpieza:** en `index.html` borrar el `<style>` muerto de paleta vieja (`body{background:#F0F4FF}`, `.product-card border #B5D4F4`); quitar el `<link>` duplicado de Material Symbols (aparece 2 veces) en las páginas afectadas; en `site/api/admin/auth/login.php:10` eliminar `header('Access-Control-Allow-Origin: same-origin')` (valor CORS inválido); mejorar el mensaje de error de `upload-image.php` cuando el archivo supera `post_max_size` (hoy dice "Token inválido").

---

## Verificación final (end-to-end, con servidor + BD arriba)
1. **Imágenes admin:** subir imagen a un producto → sin error, imagen visible en catálogo.
2. **Tipografía:** medir `font-family` de `font-display` en las 9 públicas → Barlow Condensed.
3. **Pedido end-to-end:** carrito → `pedido.html` → enviar → WhatsApp abre + fila en `orders` (POST 201) + carrito vacío.
4. **Móvil 375px:** menú hamburguesa full-width oscuro y legible en index + otra pública; sin overflow horizontal.
5. **login/registro:** visualmente consistentes; login de cliente funciona.
6. **Peso:** `logo-ds-clean.png` < 50 KB; sin archivos muertos referenciados.
7. **WhatsApp:** número real en las 10 ubicaciones.
8. **Consola del navegador sin errores** en las 14 páginas.
9. **Regresión:** el flujo del panel admin (CRUD productos/categorías, cambio de estado de pedidos, login) sigue operando — no se rompió ningún gancho.

## Commit
Al terminar cada fase, commit descriptivo. Al final, push a `origin/main` (repo `github.com/MaykMenchaca/YOWI`). No commitear `site/api/config/env.php` (ya está en `.gitignore`).
