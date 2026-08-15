# Correr el sitio en local (Windows, con tu propio MySQL)

Guía para tener la tienda **completa** en tu PC: catálogo real desde MySQL, panel
de administración y pedidos guardados en la base de datos. Es idéntico a como
quedará en producción (Hostinger).

Solo necesitas **PHP** y **MySQL** instalados. **No necesitas Laragon ni XAMPP.**

---

## 0. Requisitos (verifícalos una vez)

Abre una terminal normal (tecla Windows → `cmd`) y comprueba:

```bash
php -v            REM debe mostrar la versión de PHP
git --version     REM debe mostrar la versión de git
```

Y asegúrate de que **tu servicio de MySQL esté encendido**: tecla Windows →
`servicios` → **MySQL80** (o `MySQL`) debe estar **"En ejecución"**. Ponlo en
*Tipo de inicio → Automático* para que arranque solo con Windows.

## 1. Traer el proyecto — UNA sola ruta

Usa **siempre la misma carpeta** para no tener copias regadas. Ruta recomendada:
**`C:\YOWI`** (simple, sin espacios, no atada a ninguna herramienta).

```bash
cd C:\
git clone https://github.com/MaykMenchaca/YOWI.git
cd YOWI
git pull
```

> Si ya lo tenías en otra ruta (p. ej. `C:\laragon\www\YOWI`), **muévelo** a
> `C:\YOWI` y borra la copia vieja. El proyecto es portable: todos los `.bat` y
> scripts se ubican solos, así que funciona desde cualquier carpeta. La base de
> datos vive en MySQL (no en la carpeta), así que mover el proyecto no pierde datos.

## 2. Configurar la conexión a TU MySQL

Crea/edita `site/api/config/env.php` con los datos de tu MySQL:

```bash
notepad site\api\config\env.php
```

Pega esto (ajusta `DB_PASS` a tu contraseña de root; si es vacía, déjala `''`):

```php
<?php
return [
    'DB_HOST'    => '127.0.0.1',
    'DB_NAME'    => 'ds_sports_supplements',
    'DB_USER'    => 'root',
    'DB_PASS'    => 'AdminDS2026',
    'DB_CHARSET' => 'utf8mb4',
];
```

Guarda (Ctrl+S) y cierra.

## 3. Preparar la base de datos — UN comando

```bash
php scripts/setup-local.php
```

Crea la BD, importa el esquema, siembra 24 productos y crea el admin.
Es **idempotente** (puedes repetirlo sin duplicar nada).

## 4. Levantar el sitio

```bash
php -S localhost:8080 -t site
```

Deja esa ventana abierta (Ctrl+C para detener), o usa `start-local.bat`.

## 5. Abrir

- **Tienda:** <http://localhost:8080>
- **Admin:** <http://localhost:8080/panel-4x9qz/login.html> → `admin@ds.com` / `AdminDS2026`

---

## Mapa de carpetas (qué es cada cosa)

```
YOWI/
├─ site/              ← LA TIENDA (esto es lo que se sirve)
│  ├─ *.html          ← páginas públicas (index, catalogo, producto, pedido…)
│  ├─ panel-4x9qz/    ← panel de administración (nombre no obvio a propósito)
│  ├─ assets/         ← css, js, imágenes, datos demo
│  └─ api/            ← backend PHP (auth, products, orders, admin) + config
├─ scripts/           ← utilidades: setup-local, seed-products, create-admin,
│                        reset-mysql-password, provision-php
├─ sql/               ← schema.sql y migraciones de la base de datos
├─ docs/              ← esta guía y notas del proyecto
├─ referencias/       ← mockups y logos fuente (NO los usa la app)
├─ php/               ← PHP portable (se descarga solo; no se versiona)
├─ iniciar-tienda.bat ← doble clic: baja PHP si falta, prepara BD y abre la tienda
├─ start-local.bat    ← solo levanta el servidor (si ya está todo listo)
└─ package.json / tailwind.config.js ← solo para compilar CSS (opcional)
```

**Para correr en local solo necesitas tener MySQL encendido.** PHP lo baja el
propio `iniciar-tienda.bat` la primera vez. No necesitas Laragon ni XAMPP.

## Comandos útiles

| Para… | Comando |
|---|---|
| Rehacer el setup | `php scripts/setup-local.php` |
| Crear otro admin | `php scripts/create-admin.php "Nombre" "correo@x.com" "Password"` |
| Recompilar CSS (si tocas clases Tailwind) | `npm run build:css` |

## Problemas comunes

- **Workbench: `Access denied for user 'root'` / olvidaste la contraseña** →
  ejecuta `scripts/reset-mysql-password.bat` **como administrador** (la
  restablece a `AdminDS2026`), y ponla en `env.php`.
- **MySQL "inicia y se apaga solo"** → suele ser **conflicto de puerto 3306**
  (otro MySQL, p. ej. el de Laragon, ya lo tiene tomado). Cierra/desinstala el
  otro, reinicia la PC y arranca solo tu MySQL. Para ver quién ocupa el puerto:
  `netstat -ano | findstr :3306`.
- **`No se pudo conectar a MySQL`** → el servicio no está encendido, o la
  contraseña de `env.php` no coincide con la de tu root.
- **`php` no se reconoce** → PHP no está en el PATH del sistema; agrega su carpeta
  al PATH o usa la ruta completa a `php.exe`.
- **El catálogo sale con banner "Vista previa"** → el backend no está
  respondiendo; abre por `http://localhost:8080` (no el `.html` con doble clic) y
  confirma que `php -S ...` sigue corriendo.
```
