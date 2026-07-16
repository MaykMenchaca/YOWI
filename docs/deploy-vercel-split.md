# Despliegue: frontend en Vercel + API en Hostinger

Este sitio es **PHP + MySQL**. Vercel **no ejecuta PHP**, así que no puede alojar
el backend. Este documento describe el modelo **split**: el frontend estático se
sirve en Vercel y llama al backend PHP alojado en Hostinger.

```
  Navegador
     │
     ├── HTML/CSS/JS  ──►  Vercel (https://tu-tienda.vercel.app)   [estático]
     │
     └── /api/*.php   ──►  Hostinger (https://tienda.tudominio.com) [PHP + MySQL]
```

> ⚠️ **Lo importante primero.** El **catálogo público** (navegar productos) funciona
> perfecto en este modelo porque son peticiones GET sin sesión. Las rutas con
> **sesión/cookies** —login, registro, cuenta y el **panel admin**— dependen de
> *cookies de terceros*, que los navegadores modernos (Safari, Chrome) restringen
> cada vez más. Para esas partes lo más robusto es servir el frontend en el
> **mismo dominio** del backend. El checkout por WhatsApp **sí funciona** aunque
> falle el registro del pedido en la BD (el mensaje de WhatsApp se envía igual).
>
> Recomendación práctica: usa Vercel para el escaparate público y mantén
> `cuenta.html`, `login.html`, `registro.html` y `/admin` accesibles desde el
> dominio de Hostinger.

---

## 1) Backend en Hostinger (una sola vez)

1. Sube la carpeta `site/` a `public_html/` (o a un subdominio, p. ej.
   `tienda.tudominio.com`). Sigue el *Checklist de release* de
   `docs/project-context.md` (env.php, importar `sql/schema.sql`, migraciones,
   admin, productos, número de WhatsApp).
2. Fuerza HTTPS.
3. Edita `site/api/.htaccess` y pon el dominio EXACTO de tu frontend de Vercel en
   la línea `SetEnvIf Origin ...` (ya viene un ejemplo). Escapa los puntos con `\.`.
4. Para que las cookies de sesión viajen cross-site (login/cuenta/admin), añade en
   el `.htaccess` del backend:

   ```apache
   SetEnv DS_CROSS_SITE 1
   ```

   Esto hace que `Session.php` emita la cookie con `SameSite=None; Secure`.
   (Si NO usas login desde Vercel, puedes omitir este paso.)

## 2) Frontend en Vercel

1. Importa el repo en Vercel.
2. **Project Settings → General → Root Directory = `site`.** (El sitio vive en
   `site/`, no en la raíz del repo.) Framework Preset: **Other**. Sin build command.
3. Apunta el frontend al backend: edita `site/assets/js/config.js` y pon la URL de
   Hostinger:

   ```js
   window.DS_CONFIG = { API_BASE: "https://tienda.tudominio.com" };
   ```

   Con `API_BASE: ""` (default) el frontend asume mismo origen (todo en Hostinger).
4. Deploy. Verifica que el catálogo carga productos (Network → `products/list.php`
   debe responder 200 con CORS).

## 3) Verificación

- Home y catálogo cargan y muestran productos en la URL de Vercel.
- En DevTools → Network, la petición a `products/list.php` es cross-origin y
  responde con `Access-Control-Allow-Origin` = tu dominio de Vercel.
- El checkout abre WhatsApp con el pedido y los datos de envío.

## Alternativa más simple

Si no necesitas Vercel específicamente, **servir todo desde Hostinger** (frontend
+ backend en el mismo dominio) evita CORS y las cookies de terceros por completo,
y no requiere ninguno de estos pasos. Es el camino de menor fricción.
