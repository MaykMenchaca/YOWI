# Auditoría multi-agente #2 — Distribuidor de Suplementos (DS)
**Fecha:** 2026-07-05 · **Método:** 3 agentes en paralelo (diseño/UX, backend/seguridad, arquitectura/proyecto)
Post rediseño gimnasio + logos. Hallazgos NUEVOS (los previos ya estaban corregidos).

---

## 🖥️ SISTEMA (backend / arquitectura / despliegue)

### P0 — bloquean uso o producción
| # | Hallazgo | Causa / ubicación | Fix |
|---|----------|-------------------|-----|
| S1 | **Error al subir imágenes** en el admin | `fileinfo` DESACTIVADA en `php.ini` local → `new finfo()` en `upload-image.php:24` lanza Fatal 500 | Descomentar `extension=fileinfo` en `C:\...\dev-tools\php\php.ini` + reiniciar PHP. Verificar en Hostinger + fallback defensivo (`getimagesize`/extensión). **NO es bug de código.** |
| S2 | **Los pedidos NUNCA se guardan en la BD** (403 CSRF) ✅verificado | `pedido.html` no carga `auth.js` y `cart.js` no obtiene el token → `csrf_token=null` → `orders/create.php` responde 403. WhatsApp abre, pero el admin de pedidos queda vacío y el carrito no se vacía | En `cart.js`, hacer `GET api/auth/me.php` al iniciar y `DSApi.setCsrfToken()` antes del checkout (o cargar `auth.js` en `pedido.html`). Mostrar error visible si el POST falla (hoy solo `console.warn`). |
| S3 | **WhatsApp placeholder** `5218330000000` | 9 archivos / 10 sitios: `cart.js:5`, `enhance.js:2`, y footers de registro/producto(x2)/cuenta/pedido/nosotros/marcas/login | Reemplazar por el número real. Idealmente centralizar en una constante única. |

### P1 — importantes
| # | Hallazgo | Fix |
|---|----------|-----|
| S4 | **Logos gigantes**: `logo-ds-clean.png` = **3.4 MB** cargado en cada página (22 usos); `logo-ds-blue.png` = 2.3 MB | Comprimir a <50 KB (WebP/PNG optimizado). Es el mayor problema de rendimiento. |
| S5 | **Contradicción de despliegue**: `vercel.json` existe pero Vercel **no corre PHP** → sin backend, login ni catálogo | Destino real = **Hostinger (Apache+PHP+MySQL)**. Eliminar `vercel.json` o documentar arquitectura. |
| S6 | **~7.5 MB de assets muertos** versionados | Borrar: `productos-demo.json` (0 refs), `logo-ds-white.png` (0 refs), `logo-ds-full.jpg` (0 refs), y sacar `LOGO_1/2.jpeg` del repo. |

### P3 — menores
- `admin/auth/login.php:10` header CORS inválido `Access-Control-Allow-Origin: same-origin` → eliminar.
- Mensaje confuso si el archivo supera `post_max_size` (dice "Token inválido" en vez de "muy grande").
- N+1 en `admin/orders/list.php` (tolerable con paginación 20).
- Verificar en Hostinger/LiteSpeed que `.htaccess` con `php_flag engine off` no rompa.

### ✅ Seguridad: SÓLIDA
Sin vulnerabilidades P0/P1. Prepared statements, precio de BD, autorización `ds_require_admin()` en los 12 endpoints, sin IDOR, CSRF con tokens separados, rate limiting, uploads validados, secretos protegidos. Las correcciones previas se mantienen.

### Checklist para desplegar en Hostinger
1. WhatsApp real (S3) · 2. Fix CSRF checkout (S2) · 3. `env.php` real · 4. Importar `sql/schema.sql` · 5. Cargar 336 productos reales (hoy 12 demo) · 6. Crear admin con `create-admin.php` · 7. Comprimir logos + borrar muertos · 8. HTTPS + verificar `.htaccess` · 9. Quitar `vercel.json`.

---

## 🎨 DISEÑO (frontend / UX)

### P0 — rompen la identidad
| # | Hallazgo | Fix |
|---|----------|-----|
| D1 | **Barlow Condensed NO se aplica en las 9 públicas** ✅verificado. El config define `headline-*` pero el HTML usa `font-display`/`font-body` (llaves inexistentes) → cae a fuente del sistema. El admin sí las define y sí se ve bien. Solo los títulos con `style` inline muestran Barlow. | Añadir `display`/`body` a los 9 configs públicos (como ya tiene el admin). Recupera todo el ADN tipográfico. |
| D2 | **Nav móvil rota** (375px): el drawer se inserta dentro del `<header>` flex → sale como **caja blanca angosta sobre el logo**, con paleta vieja (`bg-white`, links #424751). Icono hamburguesa `text-primary` #004782 sobre header ink = **2.1:1**, falla | En `mobile-nav.js`: insertar el drawer full-width **debajo** del header, `bg-ink` + links lima/blanco mayúsculas; hamburguesa `text-white`/`text-lime`. |

### P1
| # | Hallazgo | Fix |
|---|----------|-----|
| D3 | **`login.html` sin migrar** al estilo gimnasio (sin header gym, tarjeta blanca vieja, botón azul viejo) — choca con `registro.html` que sí está migrada | Aplicar el mismo header + tarjeta ink/lima afilada de registro. |

### P2
- **Tokens de paleta vieja en JS**: estados vacíos/error del catálogo (`catalog-engine.js`) y el drawer usan `text-on-surface-variant`/`text-primary` (azules viejos). Clases inexistentes como `text-brand-container` en `nosotros.html`.
- **Sin fallback de imagen** en tarjetas de producto (`productCardHTML`): si `imagen` viene vacío se ve rota. Añadir placeholder.
- **La API filtra stack trace PHP** crudo cuando la BD está caída → devolver JSON `{ok:false}` y mostrar el estado de error ya diseñado.
- **CSS muerto** de paleta vieja en `index.html` (`body{background:#F0F4FF}`, `.product-card border #B5D4F4`).

### P3
- `<link>` duplicado de Material Symbols en varias páginas.
- Links de estados vacíos ("Ver productos", "reintenta") en azul subrayado viejo.
- Revisar `:focus-visible` (#378ADD) sobre ink en experience.css.

### ✅ Lo que quedó BIEN
Header/footer consistentes en las públicas migradas; admin totalmente migrado y pulido (sí usa Barlow); botón WhatsApp 5.4:1 (pasa AA); sin overflow horizontal en 375px (home); touch targets 44px; logo correcto en sus 3 contextos.

---

## Orden de ataque recomendado (rápido → alto impacto)
1. **S1 fileinfo** (1 línea, arregla subida de imágenes) + **D1 tipografía** (añadir 2 llaves × 9 páginas, recupera el look) — ambos rápidos y de altísimo impacto.
2. **S2 CSRF checkout** (que los pedidos lleguen a la BD) + **S4 comprimir logos**.
3. **D2 nav móvil** + **D3 login.html** (consistencia visual).
4. **S3 WhatsApp real** (cuando tengas el número) + **S5 vercel.json** + **S6 borrar muertos**.
5. P2/P3 de pulido (tokens viejos, fallback de imagen, stack trace, CORS header).
</content>
