# 📋 Funciones de la página — Distribuidor de Suplementos (YOWI)

Documento de referencia con **todo lo que hace la tienda**, dividido en dos partes:
lo que ve y usa el **cliente** (tienda pública) y lo que puedes hacer tú desde el
**panel de administrador**.

> Última actualización: 2026-08-03

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
- **Filtro por categoría, marca, rango de precio y "solo disponibles"**.
- **Favoritos**: marca productos con el corazón (pide iniciar sesión).
- **Mensaje claro** cuando una búsqueda no arroja resultados.
- Si un producto tiene **sabores**, la tarjeta muestra **"Desde $X"** cuando los
  precios de los sabores no son todos iguales, y el botón dice **"Elegir sabor"** en
  vez de agregar directo (lleva a la ficha a elegir). Un producto con sabores solo se
  ve "Agotado" si **todos** sus sabores lo están.

### Detalle de producto (`producto.html`)
- Ficha completa: imagen, nombre, marca, presentación (ej. "2 lb"), descripción,
  precio y precio original.
- **Galería de imágenes**: si el producto tiene fotos adicionales (además de la
  principal), aparece una tira de miniaturas debajo de la imagen grande; al pulsar
  una cambia la foto principal. Navegable con teclado. Con una sola imagen se ve
  igual que antes (sin tira).
- **Sabores** (si el producto los tiene): chips seleccionables — el elegido resalta,
  los agotados se ven tachados y no se pueden elegir. Al elegir uno, **el precio en
  pantalla cambia al instante** (usa el precio propio del sabor, o el del producto si
  no tiene uno). El botón "Agregar al carrito" queda deshabilitado con el aviso
  "Elige un sabor" hasta que se elige alguno.
- Botón para **agregar al carrito** eligiendo cantidad.

### Marcas (`marcas.html`)
- Página con las **marcas** que maneja la tienda.

### Nosotros (`nosotros.html`)
- Página de **información de la tienda** (quiénes somos).

### Carrito y pedido (`pedido.html`)
- **Carrito de compras** guardado en el navegador del cliente (no se pierde al
  recargar). Puede subir/bajar cantidades y eliminar productos.
- **Producto + sabor son líneas distintas**: si eliges Chocolate y luego Vainilla del
  mismo producto, quedan como dos renglones separados en el carrito, cada uno con su
  cantidad y su precio.
- **Checkout de solo envío a domicilio** con datos de entrega: nombre, teléfono,
  calle, colonia, código postal, ciudad, estado, referencias y notas.
- **Pago por transferencia (SPEI)**: el pedido se arma para pagarse por transferencia.
- Al confirmar:
  1. **Registra el pedido** en la base de datos (queda en tu panel de admin), con el
     sabor de cada línea si aplica.
  2. **Arma un mensaje de WhatsApp** con el resumen del pedido (incluye el sabor de
     cada línea) para enviarlo a la tienda.
- **Validación del formulario**: avisa si falta algún dato antes de enviar.
- Los **precios y el stock se recalculan y descuentan en el servidor** al registrar el
  pedido (el cliente no puede alterar montos ni sabor); si el producto tiene sabores,
  el descuento de inventario es sobre el sabor comprado, no sobre el producto.

### Cuenta de cliente (`registro.html`, `login.html`, `cuenta.html`)
- **Registro** de clientes: nombre, email, teléfono y contraseña (con confirmación y
  aceptación de términos).
- **Inicio de sesión** de cliente, con recuperación de contraseña por correo.
- **"Mis pedidos"**: el cliente ve el historial de sus pedidos con folio, fecha, total,
  estado y el sabor de cada producto si aplica.
- **Direcciones guardadas**: el cliente puede guardar varias direcciones de envío y
  elegir una al hacer checkout, en vez de escribirla cada vez.
- **Favoritos**: lista de productos marcados con el corazón.

---

## 🔐 PARTE 2 — El panel de administrador

Se entra en `admin/login.html` con tu correo y contraseña de administrador. Todo el
panel está protegido: si no hay sesión de admin, no se puede acceder a ningún dato.

### Inicio / Dashboard (`admin/index.html`)
- Vista rápida con los **últimos pedidos** (cliente, total, estado, fecha).
- **Tarjeta "Respaldo completo"**: descarga o restaura de un solo archivo todas las
  secciones (productos, categorías, marcas, pedidos, promociones y Nosotros).

### Productos (`admin/productos.html`)
- **Ver todos los productos** en una tabla con imagen, nombre, marca, categoría, SKU,
  precio, stock, si está destacado y si está visible.
- **Buscar** por nombre, marca o SKU, y **filtrar por categoría**.
- **Crear producto** con todos sus campos: SKU (opcional), nombre, marca, categoría,
  presentación (cantidad + unidad), descripción, precio, precio original (tachado),
  stock, imagen, badge (etiqueta tipo "MÁS VENDIDO"), destacado y visible.
- **Subir la imagen del producto** (valida que sea imagen real JPG/PNG/WebP).
- **Sabores**: agrega/quita sabores del producto (nombre + stock propio + precio
  propio, ambos opcionales — vacío = "sin control de inventario" / "usa el precio del
  producto"). Se guardan junto con el resto del formulario.
- **Galería de imágenes**: sube varias fotos adicionales, bórralas, reordénalas, o
  marca cualquiera como la imagen principal. Se guarda al instante (no espera al
  botón "Guardar"). Disponible solo al editar un producto ya guardado.
- **Editar** cualquier producto.
- **Ocultar** un producto del catálogo público (sin borrarlo).
- **⭐ Importar productos por CSV (carga masiva), con vista previa antes de aplicar:**
  - Botón **"Importar CSV"** → subes un archivo exportado de Excel o Google Sheets.
  - **Descarga una plantilla** de ejemplo con las columnas correctas.
  - **"Analizar" primero**: muestra una tabla fila por fila (verde = nuevo, azul =
    cambia — con qué campos, gris = sin cambios, rojo = omitido — con el motivo) y
    contadores arriba, **sin guardar nada todavía**. Cuando se ve bien, **"Aplicar
    cambios"** lo escribe de verdad.
  - Cada producto se identifica por **SKU** (si lo traes en el CSV) o, si no, por
    nombre+marca+cantidad+unidad — así puedes corregir una falta de ortografía en el
    nombre y **no se duplica** el producto.
  - **Celda vacía = no se toca ese dato** (descripción, imagen, badge, sabores,
    galería) — reimportar el mismo CSV nunca borra nada que no traigas.
  - Las **categorías y marcas nuevas se crean solas** si no existen.
  - **"Reemplazar todo el catálogo"** ya no borra: **desactiva** (oculta) los
    productos que no vengan en el archivo, conservando sus fotos e historial —
    revertible reactivándolos.
  - Columnas `sabores` (`nombre:stock:precio` separados por `|`) e `imagenes` (URLs
    separadas por `|`) para cargar sabores y galería desde el mismo CSV.
  - Tolera separador `,` o `;`, precios con coma decimal ("199,90") y acentos de Excel.

  **Columnas del CSV** (mismo orden que descarga "Exportar CSV" o la plantilla):
  `sku, nombre, marca, categoria, cantidad, unidad, descripcion, precio,
  precio_original, stock, imagen, imagenes, badge, sabores, destacado, activo`.
  Obligatorias: `nombre`, `marca`, `categoria`, `precio`. Todo lo demás es opcional
  y, si va vacío, **no se toca** lo que ya había (excepto `stock`: vacío ahí significa
  explícitamente "sin control de inventario", no "no tocar").
  - `stock`: número = inventario real (0 = agotado); vacío = sin control (siempre
    disponible).
  - `imagenes`: rutas o URLs de la galería adicional, separadas por `|` — ej.
    `assets/img/productos/whey-1.jpg|assets/img/productos/whey-2.jpg`.
  - `sabores`: `nombre:stock:precio` por sabor, separados por `|` — los dos últimos
    opcionales. Ej.: `Chocolate:10:899|Mocha::999|Fresa` → Chocolate con 10 piezas a
    $899; Mocha sin control de inventario a $999; Fresa sin control de inventario, al
    precio del producto.
- **Exportar catálogo a CSV**: descarga todos los productos (con SKU, sabores y
  galería) en el mismo formato que acepta el importador — útil para editar en Excel y
  reimportar, o como respaldo rápido.

### Categorías (`admin/categorias.html`)
- **Crear, editar y eliminar** categorías (con su orden de aparición).

### Marcas (`admin/marcas.html`)
- **Crear, editar y eliminar** marcas, con logo. **Importar marcas por CSV**.

### Pedidos (`admin/pedidos.html`)
- **Ver todos los pedidos** de la tienda, con el sabor de cada línea si aplica.
- **Cambiar el estado** de cada pedido: `pendiente → confirmado → enviado →
  entregado`, o `cancelado`.

### Promociones / Carrusel (`admin/promociones.html`)
- **Subir imágenes de promoción** (ofertas, "Feliz Navidad", etc.) que aparecen
  rotando en el carrusel de la portada.
- Por cada promoción: imagen, título (opcional), enlace al hacer clic (opcional),
  orden y si está visible.
- **Crear, editar y eliminar** promociones. Límite de imagen: 8 MB.

### Nosotros (`admin/nosotros.html`)
- Edita el contenido de la página pública "Nosotros" (misión, visión, historia, etc.)
  sin tocar código.

### Seguridad (`admin/seguridad.html`)
- **Verificación en dos pasos (2FA)** para la cuenta de administrador, con códigos de
  recuperación de respaldo.
- **Registro de auditoría**: qué acción hizo cada administrador y cuándo.

### Respaldos (botón "Respaldar" / "Restaurar" en cada sección)
- **Descargar** un archivo con fecha de cualquier sección (productos —con SKU,
  sabores y galería—, categorías, marcas, pedidos, promociones o Nosotros), o todo
  junto desde el Dashboard.
- **Restaurar** desde un archivo descargado: primero muestra una **vista previa** de
  qué se va a crear o actualizar, y solo aplica tras confirmar. Los **pedidos nunca se
  duplican ni se modifican** si ya existen — un respaldo jamás altera un pedido real.
- Complementa al respaldo automático por cron del servidor (`scripts/backup-db.sh`);
  este se guarda en tu propia computadora.

---

## ⚙️ PARTE 3 — Funciones "detrás de cámaras" (técnicas)

Cosas que el cliente no ve directamente pero que hacen que la tienda funcione bien:

### Rendimiento
- **CSS compilado con Tailwind** (minificado) en las páginas públicas → cargan más
  rápido que usando la versión de desarrollo.

### Seguridad (revisado en auditoría, última ronda 2026-08-03)
- **Contraseñas cifradas** (hash), nunca en texto plano.
- **Verificación en dos pasos (2FA)** opcional para administradores, con códigos de
  recuperación.
- **Protección CSRF** en todas las acciones del admin.
- **Consultas a la base de datos con prepared statements** → no hay inyección SQL.
- **Subida de imágenes endurecida**: valida el tipo real del archivo, re-encoda con
  GD (elimina payloads escondidos) y bloquea la ejecución de scripts en la carpeta de
  subidas.
- **Límite de intentos de login** (rate limiting) para frenar ataques de fuerza bruta.
- **Enlaces y URLs saneados**: se bloquean esquemas peligrosos (`javascript:`,
  `data:`) en promociones, imágenes de galería y CSV.
- **CORS restringido** a dominios exactos.
- **CSP estricta en el panel** (`script-src 'self'`): cero JavaScript inline, todo en
  archivos externos — bloquea inyección de scripts aunque un campo de texto se vea
  comprometido.
- **Precio y stock siempre recalculados en el servidor** al registrar un pedido
  (incluido el precio/stock por sabor): el cliente nunca puede alterar montos.
- **Registro de auditoría** de acciones del administrador.

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
| Tienda | `catalogo.html` | Catálogo con buscador inteligente, filtros y favoritos |
| Tienda | `producto.html` | Ficha de un producto: sabores, galería, agregar al carrito |
| Tienda | `marcas.html` | Marcas |
| Tienda | `nosotros.html` | Información de la tienda |
| Tienda | `pedido.html` | Carrito (líneas por producto+sabor) + checkout (envío + transferencia) |
| Cliente | `registro.html` / `login.html` / `cuenta.html` | Registro, login, direcciones, favoritos y "mis pedidos" |
| Admin | `admin/login.html` | Entrada al panel |
| Admin | `admin/index.html` | Dashboard (últimos pedidos + respaldo completo) |
| Admin | `admin/productos.html` | Productos: CRUD + SKU + sabores + galería + **importar/exportar CSV con vista previa** + respaldo |
| Admin | `admin/categorias.html` | Categorías: CRUD + respaldo |
| Admin | `admin/marcas.html` | Marcas: CRUD + importar CSV + respaldo |
| Admin | `admin/pedidos.html` | Pedidos: ver, cambiar estado, sabor por línea + respaldo |
| Admin | `admin/promociones.html` | Promociones del carrusel: CRUD + respaldo |
| Admin | `admin/nosotros.html` | Editar la página pública "Nosotros" + respaldo |
| Admin | `admin/seguridad.html` | 2FA del administrador + auditoría |
