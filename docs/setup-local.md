# Correr el sitio en local (Windows + Laragon)

Guía para tener la tienda **completa** funcionando en tu PC: catálogo real desde
MySQL, panel de administración y pedidos que se guardan en la base de datos.

> No necesitas XAMPP. Sirve cualquier stack con **PHP + MySQL** (Laragon, XAMPP,
> WAMP, Docker…). Aquí usamos **Laragon** por ser el más simple.

---

## 1. Instalar Laragon (una vez)

1. Descarga **Laragon Full** desde <https://laragon.org> (incluye PHP + MySQL).
2. Instálalo con las opciones por defecto.
3. Abre Laragon y pulsa **Start All** (arranca Apache + MySQL).

## 2. Traer el proyecto

Clona o actualiza el repo, por ejemplo dentro de `C:\laragon\www\`:

```bash
git clone https://github.com/MaykMenchaca/YOWI.git
cd YOWI
git pull            # si ya lo tenías, para traer los scripts de setup
```

## 3. Preparar la base de datos y el admin — UN comando

En Laragon: **Menu → Terminal** (abre una consola con PHP y MySQL en el PATH).
Dentro de la carpeta del proyecto, ejecuta:

```bash
php scripts/setup-local.php
```

Ese comando hace **todo** de una sola vez y es seguro repetirlo:

- crea `site/api/config/env.php` con los valores de Laragon (root, sin contraseña),
- crea la base de datos `ds_sports_supplements`,
- importa la estructura (`sql/schema.sql`),
- siembra los 24 productos de ejemplo,
- crea el usuario administrador.

Al terminar te muestra el correo y la contraseña del admin.

> **¿Tu MySQL usa contraseña o el usuario no es `root`?**
> Abre `site/api/config/env.php` y ajusta `DB_USER` / `DB_PASS`, luego vuelve a
> correr `php scripts/setup-local.php`.

## 4. Levantar el sitio

- **Doble clic** a `start-local.bat` (en la raíz del proyecto), **o** en la terminal:

  ```bash
  php -S localhost:8080 -t site
  ```

Deja esa ventana abierta mientras uses el sitio (Ctrl+C para detener).

## 5. Abrir

- **Tienda:** <http://localhost:8080>
- **Admin:** <http://localhost:8080/admin/login.html>
  - Correo: `admin@ds.com`
  - Contraseña: `AdminDS2026`

Desde el admin puedes crear/editar productos y categorías, y ver los pedidos que
entran (con su dirección de envío).

---

## Comandos sueltos (por si los necesitas)

| Para… | Comando |
|---|---|
| Rehacer setup completo | `php scripts/setup-local.php` |
| Crear otro admin | `php scripts/create-admin.php "Nombre" "correo@x.com" "Password"` |
| Recargar productos demo | `php scripts/seed-products.php` |
| Compilar CSS (si tocas clases Tailwind) | `npm run build:css` |

## Problemas comunes

- **`No se pudo conectar a MySQL`** → MySQL no está encendido. En Laragon: *Start All*.
- **`php no se reconoce`** → usa la **Terminal de Laragon** (ahí PHP ya está en el PATH),
  o abre el sitio con `start-local.bat`.
- **El catálogo sale vacío / con banner "Vista previa"** → el backend PHP no está
  respondiendo; asegúrate de abrir por `http://localhost:8080` (no abriendo el
  `.html` con doble clic) y que `php -S ...` esté corriendo.
