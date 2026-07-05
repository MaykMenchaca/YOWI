# Plan de rediseño para Gemini — DS Sports Supplements (dirección "gimnasio")

> **Para quien ejecuta (Gemini):** este documento es tu guía completa para aplicar una
> nueva apariencia visual a un e-commerce ya funcional. Léelo entero antes de tocar código.
> La **regla de oro** está en la sección 2 y es innegociable: **es un rediseño VISUAL. No
> debes romper la lógica (JavaScript/PHP) que ya funciona.**

---

## 0. Los 3 insumos que tienes en esta carpeta (`SISTEMA FOTOS/`)

| Archivo | Qué es | Cómo usarlo |
|---|---|---|
| `DS Sports Rediseño.dc.html` | **Mockup visual** de las 21 pantallas del nuevo diseño (home, catálogo, detalle, ofertas, perfil, admin, estados de error, móvil). Ábrelo en un navegador. | Es la **referencia de cómo debe verse** cada pantalla. |
| `DS Sports Handoff.dc.html` | **Guía técnica**: tokens de color en hex, reglas de tipografía y recetas de componentes en Tailwind. | Es la **fuente de verdad** de tokens y clases. |
| `01-inicio.png` … `13-admin-*.png` | **Capturas del sitio ACTUAL** (antes del rediseño). | Te muestran el punto de partida y la estructura existente. |

Trabaja con el mockup y el handoff abiertos en paralelo mientras editas.

> **Ojo:** los `.dc.html` son **lienzos de diseño** (traen envoltura `<x-dc>`, `CDATA`,
> `support.js`, colores en `oklch` y basura de Cloudflare como `__cf_email__` y `/cdn-cgi/…`).
> Son **solo referencia visual**: **nunca** copies ese marcado ni esos `oklch` al sitio real.
> La fuente de verdad de colores es el **Handoff en hex** (sección 4).

---

## 0.5. ⚠️ Errores que rompen el sitio — léelos antes de tocar nada

Estos son los fallos más probables al ejecutar este rediseño. Evítalos activamente:

1. **NO borres los `<script>` ni `<link>` de las páginas.** Al reescribir el marcado de una
   página, es fácil tirar sin querer los `<script src="assets/js/…">`, el de Tailwind CDN, las
   fuentes de Google o `experience.js`/`product3d.js`. Si eso pasa, **el JavaScript deja de
   cargar y todo se rompe sin ningún error visible**. Regla: cambia solo el marcado y las clases
   **entre** esos tags; consérvalos todos tal cual.
2. **NO reemplaces el `tailwind.config` existente — AMPLÍALO.** Las páginas ya usan muchos
   tokens semánticos (`on-surface`, `surface-container-lowest`, `outline-variant`,
   `primary-container`, etc.). Si borras el config actual, todas esas clases quedan sin definir
   y los elementos pierden su estilo. **Agrega** `ink/brand/lime/paper` y las fuentes al objeto
   `colors`/`fontFamily` **sin eliminar** lo que ya está.
3. **NO copies del mockup `.dc.html`** (ver el aviso de arriba). Usa los hex del Handoff.
4. **Haz una copia de seguridad ANTES de empezar.** Un commit de git (`git add -A && git commit
   -m "pre-rediseño"`) o una copia de la carpeta `site/` a `site_backup/`. Así puedes revertir
   si algo se rompe.
5. **Verifica de a poco, no al final.** Empieza por **una sola página piloto** (recomendado:
   `catalogo.html`), confirma que TODOS sus ganchos del Contrato (sección 3) siguen vivos y que
   funciona, y **recién entonces** sigue con las demás. Verifica **después de cada página**.
6. **Para verificar de verdad necesitas el backend corriendo** (PHP + MySQL). Si tu entorno no
   puede ejecutar PHP, como mínimo verifica **por búsqueda de texto** que cada `id`, `name` y
   `data-*` del Contrato (sección 3) sigue presente en el HTML tras tus cambios.
7. **`index.html` YA EXISTE** — reestilízalo, no crees un archivo nuevo. (El Handoff dice "la
   home nueva no existía"; ignóralo, el archivo está.)
8. **Usa solo pesos de fuente cargados.** El config carga Barlow Condensed hasta **800**
   (`font-extrabold`). No uses `font-black` (900): no cargará y se verá mal.
9. **En `producto.html`, respeta los tipos de elemento de `data-field`:** `data-field="imagen"`
   es un `<img>` (no lo conviertas en `<div>`), `data-field="precio"` es texto. El JS los rellena.

---

## 1. Stack actual (dónde vas a editar)

- **Frontend:** HTML estático + **Tailwind CSS por CDN** (config incrustada en cada `.html`) + **JavaScript vanilla** (sin React/Vue). **No hay proceso de build.** Editas HTML y clases directamente.
- **Backend:** PHP plano + PDO + MySQL/MariaDB. **No lo tocas** (ver sección 2).
- **Fuentes:** Barlow + Barlow Condensed (Google Fonts). **Iconos:** Material Symbols (SVG).
- **Raíz del sitio:** `site/` — las páginas públicas están en `site/*.html`, el panel admin en `site/admin/*.html`, el JS en `site/assets/js/`, la API en `site/api/`.

### Las 14 páginas a rediseñar
Públicas (9): `index.html`, `catalogo.html`, `producto.html`, `pedido.html`, `marcas.html`, `nosotros.html`, `login.html`, `registro.html`, `cuenta.html`
Admin (5): `admin/login.html`, `admin/index.html`, `admin/productos.html`, `admin/categorias.html`, `admin/pedidos.html`

---

## 2. ⛔ REGLA DE ORO — rediseño SOLO visual

Este sitio pasó por una auditoría de seguridad y accesibilidad de 5 etapas. La lógica funciona
y está probada. **Tu trabajo es cambiar la apariencia, no el comportamiento.**

### Archivos que NO debes modificar (déjalos intactos)
- **Toda la carpeta `site/api/`** (PHP: autenticación, pedidos, CRUD, subida de imágenes).
- **La lógica interna de los `.js`** en `site/assets/js/` y `site/assets/js/admin/`.
  Puedes **cambiar las clases CSS que estos JS generan** (por consistencia visual), pero
  **NO cambies los nombres de IDs, clases-gancho, atributos `data-*` ni `name` de formularios**
  que el JS consulta (ver el **Contrato** en la sección 3).
- `site/assets/experience.css`, `experience.js`, `product3d.js` (capa de scroll/3D — opcional dejarla).
- `sql/`, `scripts/`.

### Solo editas
- El **HTML** de las 14 páginas (marcado + clases Tailwind).
- El bloque `tailwind.config` incrustado en cada página (para agregar los tokens nuevos).
- Opcionalmente, pequeños `<style>` incrustados para efectos (líneas de velocidad, clip-path).

### Funciones que el mockup insinúa pero que NO existen en el backend → NO las implementes
El mockup muestra elementos aspiracionales que **no tienen soporte en la base de datos**. Trátalos
como decoración visual o **omítelos** — **no construyas funcionalidad nueva**:
- ⭐ Reseñas / estrellas / "128 reseñas" (no hay tabla de reseñas).
- 🎽 Selector de sabores/variantes (Chocolate/Vainilla…) (los productos no tienen variantes).
- 📍 Libreta de direcciones, "Datos personales", pestañas del perfil (solo existe historial de pedidos).
- 🚚 Estados "En camino/Entregado" con fechas en el perfil del cliente (el cliente solo ve sus pedidos y su estado real).
- 🏷️ Chips de dato tipo "24G PROTEÍNA / 150MG CAFEÍNA" (no hay ese campo; puedes usar el campo real `cantidad`, ej. "2 kg", o quitarlo).

Si dudas si algo necesita backend, **no lo implementes** y déjalo como estaba funcionalmente.

---

## 3. 📎 Contrato HTML ↔ JS (lo más importante — no rompas estos ganchos)

Antes de reescribir el marcado de una página, **identifica estos selectores y consérvalos**
(mismo `id`, mismo `name`, mismo `data-*`, misma clase-gancho). Puedes cambiarles el aspecto
con clases Tailwind, pero **el gancho debe seguir existiendo**.

### Páginas públicas
**`catalogo.html`** (usa `catalog-engine.js`):
- Contenedor de resultados: `id="product-grid"`
- Contador: `id="product-count"`
- Buscador: `id="search-input"`
- Filtros dentro de un `<aside>`: inputs `name="categoria"`, `name="precio_max"`, `name="solo_disponibles"`
- Botón limpiar: `id="clear-filters-btn"`

**`index.html`** (destacados, vía `catalog-engine.js`):
- Contenedor: `id="featured-products"` con atributo `data-limit="3"`

**`producto.html`** (detalle, vía `catalog-engine.js`):
- Contenedor: `id="product-detail"`
- Campos que se rellenan solos: elementos con `data-field="nombre"`, `data-field="marca"`, `data-field="precio"`, `data-field="imagen"` (es un `<img>`), `data-field="descripcion"`, `data-field="cantidad"`
- Botón agregar: clase `add-to-cart-btn` con `data-product-id`

**`pedido.html`** (carrito, vía `cart.js`):
- Lista de artículos: `id="cart-items"`
- Resumen: `id="cart-summary"` con elementos `data-summary="count"` y `data-summary="total"`
- Formulario: `id="checkout-form"` con inputs `name="nombre"`, `name="ciudad"`, `name="telefono"`
- Botón enviar: `id="submit-order-btn"`

**Badge del carrito (en TODAS las páginas públicas, en el header):**
- El span del contador debe tener `data-cart-badge`

**`login.html` / `registro.html` / `cuenta.html`** (vía `auth.js`):
- Login: `id="login-form"` con inputs `name="email"`, `name="password"`
- Registro: `id="registro-form"` con inputs `name="nombre"`, `name="email"`, `name="telefono"`, `name="password"`, `name="confirm_password"`
- Cuenta: `data-user-profile` (contenedor perfil) con `data-field` interno; `id="orders-list"` (historial); `data-logout-btn`
- Errores de formulario: elemento con `data-form-error`

**Nav móvil (todas las públicas):** `mobile-nav.js` genera el menú a partir de `<header> <nav> <a>…`. Mantén el `<nav>` de escritorio con sus enlaces `<a>` y un grupo de iconos en el header (clase `flex items-center gap-4` o `gap-2`).

### Panel admin
**`admin/login.html`** (`admin/auth.js`): `id="login-form"`, inputs `name="email"` / `name="password"`, `id="login-error"`
**Todas las de admin:** `id="admin-name"` (nombre del admin en el sidebar), `id="logout-btn"`
**`admin/index.html`** (dashboard): `id="stats-grid"`, `id="recent-orders"`
**`admin/productos.html`** (`admin/products.js`):
- Tabla: `id="products-tbody"` · Paginación: `id="pagination"` · Alertas: `id="alert-banner"`
- Filtros: `id="search-q"`, `id="filter-cat"`
- Botón nuevo: `id="new-product-btn"` · Modal: `id="product-modal"`, `id="modal-title"`, `id="modal-cancel"`
- Formulario: `id="product-form"` con inputs `name="nombre"`, `name="marca"`, `name="category_id"`, `name="cantidad"`, `name="descripcion"`, `name="precio"`, `name="precio_original"`, `name="stock"`, `name="badge"`, `name="destacado"` (checkbox), `name="activo"` (checkbox), `name="imagen_file"` (file)
- Imagen: `id="image-preview"`, `id="current-imagen"` (hidden)
**`admin/categorias.html`** (`admin/categories.js`):
- Tabla: `id="categories-tbody"` · Botón: `id="new-category-btn"` · Modal: `id="cat-modal"`, `id="cat-form"`, `id="modal-title"`, `id="modal-cancel"` · Inputs `name="nombre"`, `name="orden"` · `id="alert-banner"`
**`admin/pedidos.html`** (`admin/orders.js`): `id="orders-tbody"`, `id="filter-estado"`, `id="pagination"`

> **Regla práctica:** antes de editar una página, abre el/los `.js` que carga (mira los
> `<script src="assets/js/…">` al final del HTML) y busca cada `getElementById`, `querySelector`
> y `name="…"`. Todo eso debe seguir existiendo tras tu rediseño.

---

## 4. Sistema de diseño nuevo (del Handoff)

### 4.1 Tokens de Tailwind — AMPLÍA el bloque `tailwind.config` de cada página (no lo reemplaces)
**Agrega** estos tokens **dentro** del `theme.extend.colors` / `fontFamily` que ya existe en cada
página, **sin borrar** los tokens semánticos actuales (`on-surface`, `surface-*`, `outline-*`,
`primary-*`, etc.) — si los borras, romperás el estilo de todo lo que aún no hayas rediseñado:

```html
<script>
  tailwind.config = {
    theme: {
      extend: {
        colors: {
          ink:   '#0B0F1A',   // negro/hero (fondos oscuros, nav)
          brand: '#1F5FD9',   // azul primario (texto, CTA, precios)
          lime:  '#8FD11F',   // acento energía — SOLO sobre fondos oscuros o como fondo de botón
          paper: '#F7F8FA'    // fondo claro de contenido
        },
        fontFamily: {
          display: ['"Barlow Condensed"', 'sans-serif'],
          body:    ['"Barlow"', 'sans-serif']
        }
      }
    }
  }
</script>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Barlow:wght@400;500;600;700&display=swap" rel="stylesheet">
```

### 4.2 Tipografía
- **Titulares (h1/h2/h3):** `font-display font-extrabold uppercase tracking-tight`
- **Cuerpo:** `font-body` (peso 400–600)
- **Datos clave (gramos, %, stock):** `font-mono text-lime font-extrabold uppercase text-xs tracking-wide` — **solo sobre fondo oscuro** (ver 5, contraste)
- **Todo titular grande lleva una barra de acento debajo:** `<div class="w-14 h-1.5 bg-lime mb-6"></div>`
- Los titulares del rediseño usan tamaños grandes y dramáticos con `clamp()` en el hero (hasta ~88px en escritorio).

---

## 5. ♿ Accesibilidad — mantener lo que ya ganamos (obligatorio)

El sitio cumple WCAG AA. El nuevo diseño **debe seguir cumpliéndolo**. Reglas:

1. **El lima `#8FD11F` NO se usa como texto sobre blanco/paper** (contraste insuficiente). Úsalo:
   - como **fondo de botón** con texto `text-ink` (oscuro) → alto contraste ✓
   - como **texto/dato sobre fondo oscuro** (`ink`) ✓
   - como barra/borde de acento decorativo ✓
2. **Texto normal:** contraste ≥ 4.5:1. Sobre fondo claro usa `text-ink` o `text-brand`; nunca gris claro sobre blanco.
3. **Botón de WhatsApp:** mantén el verde accesible actual — fondo `#0F7A3A`, hover `#0B6E34`, texto blanco (ya validado ≥ 4.5:1). **No lo aclares en hover.**
4. **Objetivos táctiles ≥ 44px** en móvil: todos los botones/enlaces de acción llevan `min-h-[44px]` (o padding equivalente).
5. **Iconos = SVG / Material Symbols, NUNCA emojis.** El mockup usa emojis (🛒 ☰ 📲 📊 📦 🏷️ 🧾 ✓) **solo como abreviatura visual**. En el código real reemplázalos por Material Symbols (ya está cargada la fuente) o SVG. Ejemplos de mapeo: 🛒→`shopping_cart`, ☰→`menu`, 📲→ícono de WhatsApp SVG, 📊→`dashboard`, 📦→`inventory_2`, 🏷️→`sell`, 🧾→`receipt_long`, ✓→`check`.
6. **Foco visible** en enlaces/inputs (no lo quites) y respeta `prefers-reduced-motion`.

---

## 6. Recetas de componentes (del Handoff — cópialas y adáptalas)

**Nav / header:**
```html
<header class="flex items-center justify-between px-12 py-4 bg-ink border-b-2 border-lime">
  <div class="font-display font-extrabold text-2xl uppercase text-white">DS <span class="text-lime">SPORTS</span></div>
  <!-- nav de escritorio (conservar los <a>) + grupo de iconos (carrito con data-cart-badge, cuenta) -->
</header>
```

**Botón primario / CTA energía:**
```html
<button class="bg-brand text-white font-extrabold px-8 py-4 rounded-none">Ver catálogo</button>
<button class="bg-lime text-ink font-extrabold uppercase tracking-wide px-8 py-4 min-h-[44px]">Agregar al carrito</button>
```

**Card de producto** (respeta la clase `add-to-cart-btn` y `data-product-id` donde aplique):
```html
<div class="bg-white border border-gray-200 p-4 border-b-[3px] border-b-transparent hover:border-b-lime transition">
  <div class="h-36 bg-gray-100 mb-3"><!-- <img> del producto --></div>
  <div class="text-[11px] uppercase font-extrabold text-gray-500 tracking-wide">Marca</div>
  <div class="font-bold text-base">Nombre del producto</div>
  <div class="flex justify-between items-baseline mt-2">
    <div class="text-brand font-extrabold text-lg">$999.00</div>
  </div>
</div>
```

**Hero con líneas de velocidad (CSS puro, sin imágenes):**
```html
<div class="relative bg-ink py-24 px-12 overflow-hidden">
  <div class="absolute inset-0 opacity-50" style="background:repeating-linear-gradient(-8deg,transparent,transparent 60px,#1a2030 60px,#1a2030 62px)"></div>
  <div class="relative z-10"><!-- contenido del hero --></div>
</div>
```

**Franja angulada (trust bar):**
```html
<div class="bg-brand py-6 px-12" style="clip-path:polygon(0 12px,100% 0,100% 100%,0 calc(100% - 12px))"><!-- … --></div>
```

**Panel admin — solo 3 cambios (es un cambio menor y rápido):**
1. Títulos de sección: `uppercase font-display border-l-4 border-lime pl-3`
2. Botones "+ Nuevo": de azul a `bg-lime text-ink uppercase font-extrabold`
3. Cards del dashboard: `rounded-none` con `border-l-[3px]` de color por métrica (ya lo tenían; solo quita el radio). **Mantén los colores de texto claros y legibles sobre el fondo oscuro** (no uses azul/verde oscuros sobre `ink`).

---

## 7. Página por página (mapeo mockup → archivo real)

| Pantalla del mockup | Archivo a editar | Ganchos JS a preservar (ver sección 3) |
|---|---|---|
| `1b` Home v2 | `index.html` | `#featured-products[data-limit]`, `data-cart-badge`, header/nav |
| `1m` Catálogo v2 | `catalogo.html` | `#product-grid`, `#product-count`, `#search-input`, `<aside>` con inputs de filtro, `#clear-filters-btn` |
| `1n` Detalle producto | `producto.html` | `#product-detail`, `[data-field=…]`, `.add-to-cart-btn[data-product-id]` |
| `1k` Tu pedido | `pedido.html` | `#cart-items`, `#cart-summary`+`[data-summary]`, `#checkout-form`, `#submit-order-btn` |
| `1i` Marcas | `marcas.html` | header/nav, badge |
| `1j` Nosotros | `nosotros.html` | header/nav, badge |
| `1g` Login cliente | `login.html` | `#login-form`, `name=email/password` |
| `1h` Registro | `registro.html` | `#registro-form`, inputs `name=…` |
| `1q` Perfil cliente | `cuenta.html` | `data-user-profile`, `#orders-list`, `data-logout-btn` (solo restyle; NO agregar direcciones/pestañas) |
| `1l` Admin login | `admin/login.html` | `#login-form`, `#login-error` |
| `1c` Admin dashboard | `admin/index.html` | `#stats-grid`, `#recent-orders`, `#admin-name`, `#logout-btn` |
| `1d`/`1r` Admin productos (+ nuevo) | `admin/productos.html` | `#products-tbody`, modal `#product-form` con todos los `name=…`, `#new-product-btn`, filtros, `#image-preview` |
| `1e`/`1s` Admin categorías (+ modal) | `admin/categorias.html` | `#categories-tbody`, `#cat-form`, `name=nombre/orden` |
| `1f` Admin pedidos | `admin/pedidos.html` | `#orders-tbody`, `#filter-estado` |
| `1p` Ofertas | (opcional) enlace "Ofertas" hoy va a `catalogo.html` | si se crea página nueva, reusar el motor de catálogo |
| `1t`/`1o`/404 | estados de error/confirmación | solo si el flujo ya los produce; no inventar rutas nuevas |
| `1u` Móvil | responsive de las anteriores | probar a 375px; el menú lo genera `mobile-nav.js` |

---

## 8. Orden de implementación sugerido

0. **Copia de seguridad** (commit de git o copiar `site/` a `site_backup/`). No empieces sin esto.
1. **Amplía el `tailwind.config`** (agregando `ink/brand/lime/paper` + fuentes, sin borrar los
   tokens existentes — sección 4.1) en las 14 páginas. Verifica que siguen cargando bien.
2. **Página piloto: `catalogo.html`.** Rediséñala por completo, confirma que **todos sus ganchos
   del Contrato (sección 3) siguen vivos** y que funciona (productos, filtros, agregar al carrito).
   No sigas hasta que el piloto esté 100%. Esto valida tu método antes de repetirlo 13 veces.
3. **Header/nav** unificado (dark + borde lima) en todas — respetando el badge `data-cart-badge`
   y el `<nav>` para el menú móvil. **Verifica después de cada página.**
4. **Home** (`index.html`, ya existe — reestilizar) — la de mayor impacto (hero dark con líneas
   de velocidad, trust bar angulada, "Elige tu arma", "Más vendidos").
5. **Detalle de producto** (respetando los `data-field` y sus tipos de elemento).
6. **Resto de públicas** (Marcas, Nosotros, Login, Registro, Pedido).
7. **Admin** (los 3 cambios de la sección 6).
8. **Fotos reales:** reemplaza los placeholders `bg-gray-100` por `<img>` (o deja el
   `data-field="imagen"` que ya llena el JS en el detalle).

---

## 9. ✅ Checklist de verificación antes de dar por terminado

Levanta el sitio en local y comprueba, página por página:

- [ ] **Catálogo:** cargan los productos, el buscador y los filtros funcionan, "Agregar al carrito" mete el producto (el badge sube).
- [ ] **Detalle:** al abrir `producto.html?id=…` se rellenan nombre/marca/precio/imagen/descripción.
- [ ] **Pedido:** se ven las líneas, el subtotal, los botones +/−/eliminar funcionan, "Enviar por WhatsApp" abre WhatsApp y registra el pedido.
- [ ] **Login/Registro/Cuenta cliente:** iniciar sesión funciona; el historial de pedidos carga.
- [ ] **Admin:** login entra al dashboard; crear/editar/ocultar producto funciona; **subir imagen funciona**; CRUD de categorías y cambio de estado de pedidos funcionan.
- [ ] **Menú móvil (375px):** el botón hamburguesa abre el menú con los enlaces.
- [ ] **Contraste:** ningún texto lima sobre fondo claro; botones legibles; WhatsApp verde oscuro.
- [ ] **Iconos:** ningún emoji como icono (todo Material Symbols/SVG).
- [ ] **Consola del navegador sin errores** en ninguna página.

> Si algo de esta lista deja de funcionar tras un cambio de marcado, es casi seguro que se
> renombró o eliminó un gancho del **Contrato (sección 3)**. Restáuralo.

---

## 10. Resumen para Gemini en una frase
Aplica la piel visual "gimnasio" (negro `#0B0F1A` + lima `#8FD11F` + azul `#1F5FD9`, Barlow
Condensed en mayúsculas, cortes angulares y hero con líneas de velocidad) a las 14 páginas
HTML, **conservando intactos todos los ganchos `id`/`name`/`data-*`/clase que el JavaScript
usa, sin tocar la API PHP, sin inventar funciones nuevas, y sin romper el contraste ni la
accesibilidad.**
