# 📋 Funciones de la página — Distribuidor de Suplementos (YOWI)

Documento de referencia con **todo lo que hace la tienda**, dividido en dos partes:
lo que ve y usa el **cliente** (tienda pública) y lo que puedes hacer tú desde el
**panel de administrador**.

> Última actualización: 2026-07-26

---

## 🛒 PARTE 1 — La tienda (lo que ve el cliente)

### Inicio (`index.html`)
- **Carrusel de promociones** en la parte de arriba (hero): muestra las imágenes de
  promoción que subes desde el admin, rotando solas cada 5 segundos. Si no hay
  promociones cargadas, muestra la portada por defecto (nunca se ve vacío).
- **Productos destacados**: los productos que marcas como "destacado" en el admin.
- **Accesos a categorías** y navegación a todo el catálogo.

### Catálogo (`catalogo.html`)
- **Listado de todos los productos** activos, con imagen, marca, precio y precio
  tachado (si tiene oferta).
- **Buscador inteligente (tolerante a errores)**: si escribes mal el nombre —por
  ejemplo "protena" en vez de "proteína" o sin acentos— igual encuentra el producto.
  Ignora acentos y perdona letras cambiadas o faltantes.
- **Filtro por categoría**.
- **Mensaje claro** cuando una búsqueda no arroja resultados.

### Detalle de producto (`producto.html`)
- Ficha completa: imagen, nombre, marca, presentación (ej. "2 lb"), descripción,
  precio y precio original.
- Botón para **agregar al carrito** eligiendo cantidad.

### Marcas (`marcas.html`)
- Página con las **marcas** que maneja la tienda.

### Nosotros (`nosotros.html`)
- Página de **información de la tienda** (quiénes somos).

### Carrito y pedido (`pedido.html`)
- **Carrito de compras** guardado en el navegador del cliente (no se pierde al
  recargar). Puede subir/bajar cantidades y eliminar productos.
- **Checkout de solo envío a domicilio** con datos de entrega: nombre, teléfono,
  calle, colonia, código postal, ciudad, estado, referencias y notas.
- **Pago por transferencia (SPEI)**: el pedido se arma para pagarse por transferencia.
- Al confirmar:
  1. **Registra el pedido** en la base de datos (queda en tu panel de admin).
  2. **Arma un mensaje de WhatsApp** con el resumen del pedido para enviarlo a la
     tienda.
- **Validación del formulario**: avisa si falta algún dato antes de enviar.
- Los **precios se recalculan en el servidor** al registrar el pedido (el cliente no
  puede alterar montos).

### Cuenta de cliente (`registro.html`, `login.html`, `cuenta.html`)
- **Registro** de clientes: nombre, email, teléfono y contraseña (con confirmación y
  aceptación de términos).
- **Inicio de sesión** de cliente.
- **"Mis pedidos"**: el cliente ve el historial de sus pedidos con folio, fecha, total
  y estado.

---

## 🔐 PARTE 2 — El panel de administrador

Se entra en `admin/login.html` con tu correo y contraseña de administrador. Todo el
panel está protegido: si no hay sesión de admin, no se puede acceder a ningún dato.

### Inicio / Dashboard (`admin/index.html`)
- Vista rápida con los **últimos pedidos** (cliente, total, estado, fecha).

### Productos (`admin/productos.html`)
- **Ver todos los productos** en una tabla con imagen, nombre, marca, categoría,
  precio, stock, si está destacado y si está visible.
- **Buscar** por nombre o marca, y **filtrar por categoría**.
- **Crear producto** con todos sus campos: nombre, marca, categoría, presentación
  (cantidad), descripción, precio, precio original (tachado), stock, imagen, badge
  (etiqueta tipo "MÁS VENDIDO"), destacado y visible.
- **Subir la imagen del producto** (valida que sea imagen real JPG/PNG/WebP).
- **Editar** cualquier producto.
- **Ocultar** un producto del catálogo público (sin borrarlo).
- **⭐ Importar productos por CSV (carga masiva):**
  - Botón **"Importar CSV"** → subes un archivo exportado de Excel o Google Sheets.
  - **Descarga una plantilla** de ejemplo con las columnas correctas.
  - Las **categorías nuevas se crean solas** si no existen.
  - Si un producto **ya existe** (mismo nombre y marca), se **actualiza**; si no, se
    crea. Al actualizar, **conserva la imagen** si dejas esa columna vacía.
  - Tolera separador `,` o `;`, precios con coma decimal ("199,90") y acentos de Excel.
  - Te da un **resumen**: cuántos creó, cuántos actualizó, categorías nuevas y las
    filas que omitió con el motivo.

### Categorías (`admin/categorias.html`)
- **Crear, editar y eliminar** categorías (con su orden de aparición).

### Pedidos (`admin/pedidos.html`)
- **Ver todos los pedidos** de la tienda.
- **Cambiar el estado** de cada pedido: `pendiente → confirmado → enviado →
  entregado`, o `cancelado`.

### Promociones / Carrusel (`admin/promociones.html`)
- **Subir imágenes de promoción** (ofertas, "Feliz Navidad", etc.) que aparecen
  rotando en el carrusel de la portada.
- Por cada promoción: imagen, título (opcional), enlace al hacer clic (opcional),
  orden y si está visible.
- **Crear, editar y eliminar** promociones. Límite de imagen: 8 MB.

---

## ⚙️ PARTE 3 — Funciones "detrás de cámaras" (técnicas)

Cosas que el cliente no ve directamente pero que hacen que la tienda funcione bien:

### Rendimiento
- **CSS compilado con Tailwind** (minificado) en las páginas públicas → cargan más
  rápido que usando la versión de desarrollo.

### Seguridad (revisado en auditoría, 2026-07-26)
- **Contraseñas cifradas** (hash), nunca en texto plano.
- **Protección CSRF** en todas las acciones del admin.
- **Consultas a la base de datos con prepared statements** → no hay inyección SQL.
- **Subida de imágenes endurecida**: valida el tipo real del archivo y bloquea la
  ejecución de scripts en la carpeta de subidas.
- **Límite de intentos de login** (rate limiting) para frenar ataques de fuerza bruta.
- **Enlaces de promoción saneados**: se bloquean enlaces peligrosos (`javascript:`).
- **CORS restringido** a dominios exactos.

### Disponibilidad / despliegue
- **Modo demo**: si el catálogo no puede conectar con el servidor, muestra productos
  de ejemplo para que la página nunca se vea rota.
- **Despliegue flexible**: puede correr todo en un mismo servidor (Hostinger) o con el
  frontend aparte (Vercel) y la API en otro lado.

### Desarrollo local (tu PC con Windows + MySQL)
- **Un solo archivo `ABRIR-TIENDA.bat`** que: baja los últimos cambios, enciende
  MySQL, descarga PHP si hace falta, prepara la base de datos y abre la tienda.
- Base de datos **MySQL** con datos de ejemplo y usuario admin listos.
- **Migraciones automáticas**: al abrir la tienda, se aplican solas los cambios nuevos
  de base de datos (como la tabla de promociones), sin correr SQL a mano.

---

## 🗂️ Resumen rápido (mapa de páginas)

| Zona | Página | Para qué sirve |
|---|---|---|
| Tienda | `index.html` | Portada + carrusel de promociones + destacados |
| Tienda | `catalogo.html` | Catálogo con buscador inteligente y filtros |
| Tienda | `producto.html` | Ficha de un producto |
| Tienda | `marcas.html` | Marcas |
| Tienda | `nosotros.html` | Información de la tienda |
| Tienda | `pedido.html` | Carrito + checkout (envío + transferencia) |
| Cliente | `registro.html` / `login.html` / `cuenta.html` | Registro, login y "mis pedidos" |
| Admin | `admin/login.html` | Entrada al panel |
| Admin | `admin/index.html` | Dashboard (últimos pedidos) |
| Admin | `admin/productos.html` | Productos: CRUD + subir imagen + **importar CSV** |
| Admin | `admin/categorias.html` | Categorías: CRUD |
| Admin | `admin/pedidos.html` | Pedidos: ver y cambiar estado |
| Admin | `admin/promociones.html` | Promociones del carrusel: CRUD |
