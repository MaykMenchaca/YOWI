-- DS Sports Supplements — esquema de base de datos (MySQL/MariaDB)
-- Importar una sola vez vía phpMyAdmin en Hostinger. NUNCA se sube por FTP a public_html.
-- v2: agrega admins, categories, products y migra order_items.producto_id a FK INT.

SET NAMES utf8mb4;

-- ── Administradores (tabla separada de users, sesión $_SESSION['admin_id']) ──────
-- rol: 'dueno' | 'operador' | 'lectura' (whitelist real en PHP, ds_rol_nivel() en
-- AdminSession.php). Default 'lectura' a propósito — es el rol MENOS privilegiado, para
-- que un INSERT que olvide asignar rol cree la cuenta más inofensiva, nunca un dueño.
-- scripts/create-admin.php inserta 'dueno' explícito (es el único camino para crear el
-- primer admin de una instalación nueva).
CREATE TABLE IF NOT EXISTS admins (
    id                   INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    nombre               VARCHAR(150)   NOT NULL,
    email                VARCHAR(190)   NOT NULL,
    rol                  VARCHAR(20)    NOT NULL DEFAULT 'lectura',
    activo               TINYINT(1)     NOT NULL DEFAULT 1,
    password_hash        VARCHAR(255)   NOT NULL,
    password_changed_at  DATETIME       NULL,
    totp_secret          VARCHAR(64)    NULL,
    totp_enabled         TINYINT(1)     NOT NULL DEFAULT 0,
    created_at           DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_admins_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Códigos de recuperación del 2FA de admin (respaldo del autenticador) ──────────
CREATE TABLE IF NOT EXISTS admin_recovery_codes (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id   INT UNSIGNED NOT NULL,
    code_hash  CHAR(64)     NOT NULL,
    used_at    DATETIME     NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_recovery_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE,
    KEY idx_recovery_admin (admin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Log de auditoría de acciones del administrador ────────────────────────────────
CREATE TABLE IF NOT EXISTS admin_audit_log (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id   INT UNSIGNED NULL,
    accion     VARCHAR(80)  NOT NULL,
    detalle    VARCHAR(255) NULL,
    ip         VARCHAR(45)  NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL,
    KEY idx_audit_admin (admin_id),
    KEY idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Categorías ───────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS categories (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre     VARCHAR(100) NOT NULL,
    slug       VARCHAR(110) NOT NULL,
    orden      SMALLINT     NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Productos (reemplaza al catálogo JSON) ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS products (
    id               INT UNSIGNED      AUTO_INCREMENT PRIMARY KEY,
    -- Identidad estable del producto para reimportar el CSV sin duplicar (NULL permitido:
    -- varios NULL no colisionan en un UNIQUE, así conviven los aún sin SKU).
    sku              VARCHAR(64)       NULL,
    nombre           VARCHAR(255)      NOT NULL,
    marca            VARCHAR(120)      NOT NULL,
    category_id      INT UNSIGNED      NOT NULL,
    cantidad         VARCHAR(80)       NOT NULL DEFAULT '',
    unidad           VARCHAR(20)       NULL,
    descripcion      TEXT              NULL,
    precio           DECIMAL(10,2)     NOT NULL DEFAULT 0.00,
    precio_original  DECIMAL(10,2)     NULL,
    stock            SMALLINT UNSIGNED NULL DEFAULT NULL,
    imagen           VARCHAR(255)      NOT NULL DEFAULT 'assets/img/producto-placeholder.svg',
    badge            VARCHAR(30)       NULL,
    destacado        TINYINT(1)        NOT NULL DEFAULT 0,
    activo           TINYINT(1)        NOT NULL DEFAULT 1,
    created_at       DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_products_category FOREIGN KEY (category_id)
        REFERENCES categories(id) ON DELETE RESTRICT,
    KEY idx_products_category (category_id),
    KEY idx_products_activo   (activo),
    KEY idx_products_destacado(destacado),
    -- Varios NULL conviven sin colisionar; un SKU repetido con valor sí choca, que es lo
    -- que garantiza que el importador CSV empareje siempre el mismo producto.
    UNIQUE KEY uq_products_sku (sku)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Sabores por producto: nombre + stock propio + precio propio ───────────────────
-- stock NULL = sin control de inventario; precio NULL = usa el precio del producto.
CREATE TABLE IF NOT EXISTS product_flavors (
    id         INT UNSIGNED      AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED      NOT NULL,
    nombre     VARCHAR(80)       NOT NULL,
    slug       VARCHAR(90)       NOT NULL,
    stock      SMALLINT UNSIGNED NULL DEFAULT NULL,
    precio     DECIMAL(10,2)     NULL,
    orden      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    activo     TINYINT(1)        NOT NULL DEFAULT 1,
    created_at DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_flavors_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY uq_flavors_product_slug (product_id, slug),
    KEY idx_flavors_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Galería de imágenes por producto (products.imagen sigue siendo la principal) ──
CREATE TABLE IF NOT EXISTS product_images (
    id         INT UNSIGNED      AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED      NOT NULL,
    url        VARCHAR(255)      NOT NULL,
    orden      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_images_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    KEY idx_images_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Banners / promociones del hero (carrusel de la portada) ───────────────────────
CREATE TABLE IF NOT EXISTS banners (
    id          INT UNSIGNED      AUTO_INCREMENT PRIMARY KEY,
    titulo      VARCHAR(150)      NULL,
    imagen      VARCHAR(255)      NOT NULL,
    enlace      VARCHAR(500)      NULL,
    orden       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    activo      TINYINT(1)        NOT NULL DEFAULT 1,
    created_at  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_banners_activo (activo),
    KEY idx_banners_orden  (orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Marcas (brands) gestionables desde el admin ───────────────────────────────────
CREATE TABLE IF NOT EXISTS brands (
    id          INT UNSIGNED      AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(120)      NOT NULL,
    slug        VARCHAR(140)      NOT NULL,
    imagen      VARCHAR(255)      NULL,
    enlace      VARCHAR(500)      NULL,
    orden       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    activo      TINYINT(1)        NOT NULL DEFAULT 1,
    created_at  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_brands_slug (slug),
    KEY idx_brands_activo (activo),
    KEY idx_brands_orden  (orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Settings (contenido editable desde el admin, ej. la página "Nosotros") ────────
CREATE TABLE IF NOT EXISTS settings (
    clave      VARCHAR(60)  NOT NULL PRIMARY KEY,
    valor      TEXT         NULL,
    updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Valores por defecto: el contenido REAL del negocio (misión, visión, quiénes somos, políticas
-- de compra/envío y términos que el negocio ya usaba en su documento de mayo 2025, más el
-- aviso de privacidad redactado en la Fase 4 del plan de info-negocio-editable). INSERT IGNORE
-- = no pisa lo que el admin ya haya editado si esta sentencia se re-ejecuta. Las claves
-- val1_*/val2_*/val3_* se retiraron: el contenido real no tiene esa forma.
INSERT IGNORE INTO settings (clave, valor) VALUES
('contacto_direccion', 'Av. Hidalgo 4320
Zona Centro, Tampico, Tamaulipas'),
('contacto_email', 'contacto@dssports.com'),
('contacto_horario', 'Lunes a Viernes: 09:00 - 19:00
Sábados: 10:00 - 14:00'),
('contacto_mapa_url', 'https://maps.app.goo.gl/mQ4ZW9D7ZdNMQb2K7'),
('contacto_telefono', '833 164 5172'),
('contacto_whatsapp', '5218331645172'),
('legal_compra', 'Antes de realizar una compra, favor de leer las siguientes políticas. Al efectuar el pago, el cliente acepta todas las condiciones aquí descritas.

1. COTIZACIONES Y PRECIOS
Todas las cotizaciones se realizan en tiempo real.
Los precios pueden cambiar sin previo aviso por disponibilidad y cualquier otro factor.
Una cotización tiene validez solo al momento en que es enviada.
Si el pago no se realiza de inmediato, será necesario volver a confirmar el precio.
Los precios pueden variar de un día a otro.
Los precios publicados en cualquier medio o red social (anuncios, publicaciones, historias, catálogos, etc.) son dinámicos y pueden cambiar o quedar desactualizados.
Las promociones publicadas también son dinámicas y están sujetas a disponibilidad. Pueden finalizar sin previo aviso.

2. FORMAS DE PAGO
Todos los precios aplican únicamente pagando por transferencia bancaria.
Es indispensable que, antes de realizar el pago, el cliente solicite la cuenta a la cual se hará la transferencia.
El cliente es responsable de enviar el pago a la cuenta correcta proporcionada por DS Distribuidor de Suplementos.
Si el cliente se equivoca al realizar la transferencia, DS Distribuidor de Suplementos no se hace responsable.
La confirmación del pago solo acredita la recepción del importe y el registro del pedido.

3. EXISTENCIAS
Toda la mercancía está sujeta a disponibilidad.
Puede agotarse en cualquier momento, incluso después de haber sido cotizada.
La disponibilidad se confirma al momento de registrar el pedido.
En caso de falta de existencia, se ofrecerá: esperar reabastecimiento, sustituir por otro producto de valor similar, o reembolso del producto no disponible.

4. CONFIRMACIÓN DEL PEDIDO
Antes de registrar el pedido, el cliente debe verificar: productos solicitados, cantidades, presentaciones, sabores, tamaños y promociones aplicables.
Una vez registrado el pedido, cualquier modificación estará sujeta a revisión y disponibilidad.

5. PROMOCIONES
Todas las promociones están sujetas a disponibilidad.
Pueden finalizar sin previo aviso.
No son acumulables, salvo que se indique expresamente.
Aplican únicamente durante la vigencia anunciada.

6. PRESENTACIONES DEL FABRICANTE
Las marcas pueden modificar sin previo aviso: diseño del envase, etiqueta, colores, tapa, imagen comercial y presentación.
Estos cambios no afectan la autenticidad del producto.

7. FOTOGRAFÍAS
Las imágenes usadas en cotizaciones, catálogos y publicaciones son ilustrativas.
El producto recibido puede presentar ligeras diferencias en diseño o presentación.

8. ERRORES INVOLUNTARIOS
En caso de error tipográfico o humano en precios publicados o cotizados, DS Distribuidor de Suplementos se reserva el derecho de corregirlo antes de confirmar la venta.
Si el precio correcto es mayor, se informará al cliente antes de continuar.

9. RESERVA DEL DERECHO DE VENTA
DS Distribuidor de Suplementos se reserva el derecho de cancelar o rechazar cualquier pedido por errores de sistema, precio, falta de disponibilidad, datos incompletos o cualquier situación que impida operar correctamente.

10. USO RESPONSABLE DE LOS PRODUCTOS
Es responsabilidad del cliente informarse sobre el uso adecuado de cada suplemento.
Recomendamos seguir las indicaciones del fabricante y, en caso de duda, consultar a un profesional de la salud.
DS Distribuidor de Suplementos no se hace responsable por el uso inadecuado de los productos adquiridos.

11. ACEPTACIÓN
Al realizar el pago, el cliente declara haber leído, entendido y aceptado todas las políticas de compra de DS Distribuidor de Suplementos.

NOTAS IMPORTANTES
No apartamos mercancía sin pago.
No respetamos precios de cotizaciones anteriores.
Todos los productos son originales y sellados de fábrica.
Estas políticas pueden ser modificadas sin previo aviso.'),
('legal_envio', 'Una vez realizado el pago, el cliente acepta las siguientes políticas de envío.

1. CONFIRMACIÓN DEL PEDIDO
La confirmación del pago acredita la recepción del importe y el registro del pedido.
La confirmación NO significa que el pedido será preparado, empacado o enviado el mismo día.

2. ORDEN DE PREPARACIÓN
Los pedidos se preparan conforme al orden cronológico en que fueron confirmados.
Manejamos alto volumen de pedidos, por lo que existe fila de preparación.
No es posible adelantar pedidos, salvo autorización expresa de DS.

3. TIEMPO PARA GENERAR LA GUÍA
El tiempo habitual para generar la guía es de 3 días hábiles.
Puede aumentar según: volumen de pedidos, temporadas de alta demanda, días festivos, fines de semana, incidencias operativas y cargas extraordinarias de trabajo.
El tiempo de generación es estimado y no constituye fecha garantizada.

4. TIEMPO DE ENTREGA
Una vez entregado el paquete a la paquetería, los tiempos dependen exclusivamente de ésta.
DS no tiene control sobre: retrasos, reprogramaciones, saturación logística, clima, accidentes, bloqueos carreteros ni fallas operativas.

5. PAQUETERÍAS
Trabajamos con distintas empresas de mensajería.
Usamos la que ofrece mejor relación costo y servicio.
Si el cliente desea una paquetería específica, debe indicarlo antes del pago.
Diferencias en el costo del envío serán cubiertas por el cliente.

6. COSTO DEL ENVÍO
El costo del envío no es fijo.
Puede variar por: peso del paquete, dimensiones de la caja, cantidad de productos, destino, código postal, zona de cobertura y tarifas vigentes.
Cambios en productos o cantidades pueden modificar el costo.

7. ZONAS DE REEXPEDICIÓN
Algunas zonas tienen cargos adicionales por reexpedición.
En estos casos, la paquetería lo informa después del envío.
Si genera cargo adicional, el cliente deberá cubrirlo, ya que DS no controla estas tarifas ni puede absorber dichos costos.

8. SEGURO DE ENVÍO
Todos los clientes pueden contratar seguro de envío con costo adicional.
Es ofrecido por la plataforma logística utilizada, NO por DS.
Recomendamos contratarlo para proteger el pedido durante su transporte.

9. DAÑOS OCASIONADOS POR LA PAQUETERÍA
Los productos salen en perfecto estado físico.
Si el paquete sufre daño durante el traslado, será responsabilidad de la paquetería.
DS no se hace responsable por: cajas golpeadas, cajas abiertas, productos aplastados, envases abollados, tapas quebradas, etiquetas dañadas, maltrato durante transporte, ni cambios de presión, temperatura o manipulación.

10. RECEPCIÓN DEL PAQUETE
El cliente debe inspeccionar el paquete antes de aceptarlo.
Si presenta daños visibles, perforaciones, humedad, ruptura o cualquier alteración, debe rechazar la entrega y reportarlo inmediatamente.
Solicita grabar evidencia en video desde antes de abrir el paquete hasta revisar el contenido.
La falta de evidencia audiovisual puede limitar reclamaciones.

11. ACEPTACIÓN
Al realizar el pago, el cliente acepta todas las políticas de envío aquí descritas.

NOTAS IMPORTANTES
Es responsabilidad del cliente proporcionar correctamente sus datos de envío.
Los tiempos de entrega son estimados y dependen de factores ajenos a DS.
Recomendamos contratar seguro para proteger tu pedido.'),
('legal_privacidad', 'Este aviso de privacidad describe qué datos personales recaba DS Distribuidor de Suplementos, para qué los usa y cómo puedes ejercer tus derechos, conforme a la Ley Federal de Protección de Datos Personales en Posesión de los Particulares (LFPDPPP). El responsable del tratamiento de tus datos es el que se indica al inicio de esta página.

1. DATOS QUE RECABAMOS
Al crear una cuenta: nombre, correo electrónico y contraseña (esta última se guarda cifrada, nunca en texto plano).
Al hacer un pedido: nombre, teléfono, calle, colonia, código postal, ciudad, estado y, si los agregas, referencias del domicilio y notas del pedido.

2. PARA QUÉ LOS USAMOS
Para registrar y entregar tu pedido, contactarte sobre su estado, y darte acceso a tu cuenta: historial de pedidos, direcciones guardadas y favoritos.

3. CON QUIÉN COMPARTIMOS TU INFORMACIÓN
Tu pedido se envía como mensaje de WhatsApp (servicio operado por Meta) con tu nombre, teléfono y domicilio, para poder confirmarlo y coordinarlo contigo.
Tu domicilio se comparte con la paquetería que realiza el envío, únicamente para poder entregarte el pedido.
No vendemos tus datos personales ni los compartimos con nadie más.

4. CONSERVACIÓN DE TUS DATOS
Conservamos tus datos mientras tu cuenta exista. Si eliminas tu cuenta, tus pedidos anteriores se conservan de forma anónima —sin tu nombre, teléfono ni domicilio— porque los necesitamos para nuestra contabilidad.

5. TUS DERECHOS (ARCO)
Puedes acceder, rectificar y cancelar tus datos personales, así como oponerte a su tratamiento, en cualquier momento. Para eliminar tu cuenta y tus datos, usa el botón correspondiente en tu perfil ("Mi cuenta"), o contáctanos con el correo indicado en esta página.

6. SEGURIDAD
Tu contraseña se guarda cifrada y nunca la compartimos. El acceso al panel de administración está protegido con doble factor de autenticación.

7. CAMBIOS A ESTE AVISO
Este aviso puede actualizarse para reflejar cambios en cómo operamos. Los cambios se publican en esta misma página.

Este es un aviso de privacidad base; se recomienda que sea revisado por un profesional legal antes de tratarlo como definitivo.'),
('legal_terminos', 'Al realizar un pago, el cliente acepta todos los términos y condiciones generales aquí descritos, además de nuestras Políticas de Compra y Políticas de Envío.

1. DATOS DEL CLIENTE
Es responsabilidad del cliente proporcionar correctamente su nombre, dirección, código postal, teléfono y demás datos necesarios para el envío.
Cualquier gasto generado por información incorrecta será cubierto por el cliente.

2. PRODUCTOS NO RECLAMADOS
Si el paquete es devuelto por domicilio incorrecto, ausencia del destinatario o falta de recolección, el costo del nuevo envío será cubierto por el cliente.

3. MODIFICACIONES
Una vez registrado el pedido, no se garantiza que pueda modificarse.
Toda modificación estará sujeta a disponibilidad y revisión.

4. CASOS AJENOS A DS
DS Distribuidor de Suplementos no será responsable por retrasos ocasionados por casos de fuerza mayor o situaciones ajenas a nuestra operación, tales como fenómenos naturales, bloqueos, accidentes, fallas en servicios, manifestaciones o cualquier otra situación externa.

5. DIFERENCIAS ENTRE LOTES
Un mismo producto puede presentar variaciones en sabor, aroma, color, textura, diseño o presentación entre distintos lotes, sin que esto afecte su autenticidad, calidad o efectividad.

6. PRODUCTOS ABIERTOS
No se aceptarán reclamaciones ni solicitudes de cambio sobre productos abiertos, manipulados o con sellos violados.

7. PROMOCIONES
Todas las promociones están sujetas a disponibilidad y pueden finalizar sin previo aviso.
No son acumulables, salvo que se indique expresamente.
Las promociones no aplican de forma retroactiva sobre compras realizadas con anterioridad.

8. COMUNICACIÓN OFICIAL
Únicamente serán válidos los precios, promociones, acuerdos e instrucciones enviados por los canales oficiales de DS Distribuidor de Suplementos.

9. ATENCIÓN
Los tiempos de respuesta pueden variar según el volumen de mensajes.
La atención se realiza únicamente en horarios laborales.

10. ACTUALIZACIÓN DE POLÍTICAS
DS Distribuidor de Suplementos podrá modificar estos términos y condiciones en cualquier momento y sin previo aviso.
Se recomienda revisarlos periódicamente.

NOTAS IMPORTANTES
Todos nuestros productos son 100% originales y sellados de fábrica.
Le recomendamos leer completamente nuestras políticas antes de realizar su compra.
Los tiempos de entrega son estimados y dependen de factores ajenos a DS y a la empresa de paquetería.'),
('negocio_razon_social', 'DS Distribuidor de Suplementos'),
('negocio_rfc', ''),
('nosotros_beneficios', 'Productos 100% originales
Precio de distribuidor
Compra desde 1 pieza
Sin compra mínima
Envíos rápidos a todo México
Atención personalizada antes y después de la compra'),
('nosotros_mision', 'Acercar las mejores marcas de suplementación deportiva a todo México mediante un modelo de distribución eficiente, transparente y accesible, ofreciendo productos 100% originales, precios de distribuidor y un servicio excepcional que impulse el crecimiento de nuestros clientes y socios comerciales.'),
('nosotros_que_hacemos', 'Trabajamos directamente con las principales marcas y fabricantes para ofrecer un catálogo amplio y actualizado de proteínas, creatinas, preentrenamientos, vitaminas, aminoácidos y cientos de suplementos deportivos originales. Atendemos tanto a consumidores finales como a distribuidores, gimnasios, coaches, tiendas de suplementos y emprendedores que buscan crecer con un proveedor confiable.'),
('nosotros_quienes', 'En DS Distribuidor de Suplementos conectamos a miles de personas, emprendedores, gimnasios, tiendas y profesionales de la salud con las marcas líderes de suplementación deportiva en México.

Somos una empresa especializada en la distribución de suplementos 100% originales, ofreciendo acceso a un amplio catálogo de productos nacionales e internacionales con precios de distribuidor, compra desde una pieza y envíos a todo México.

Más que vender suplementos, nuestro objetivo es facilitar el acceso a productos auténticos mediante un servicio profesional, atención personalizada y una logística eficiente que permita a nuestros clientes comprar con confianza.'),
('nosotros_representa', 'DS representa confianza, transparencia y compromiso.

Creemos que acceder a suplementos originales no debe ser complicado ni costoso. Por eso hemos construido un modelo de distribución enfocado en eliminar intermediarios, optimizar la logística y ofrecer un servicio que genere relaciones de largo plazo con nuestros clientes.

Cada pedido que sale de nuestro almacén refleja nuestro compromiso con la calidad, la autenticidad y la satisfacción de quienes confían en nosotros.'),
('nosotros_vision', 'Ser el referente nacional en distribución de suplementos deportivos, distinguiéndonos por nuestra innovación, confianza, liderazgo comercial y compromiso con ofrecer siempre las mejores marcas, la mayor disponibilidad y una experiencia de compra de clase mundial.'),
('social_facebook', ''),
('social_instagram', ''),
('social_tiktok', '');

-- ── Usuarios (sin cambios) ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre         VARCHAR(150) NOT NULL,
    email          VARCHAR(190) NOT NULL,
    telefono       VARCHAR(30)  NULL,
    password_hash  VARCHAR(255) NOT NULL,
    password_changed_at DATETIME NULL,
    email_verified TINYINT(1)   NOT NULL DEFAULT 0,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Tokens de verificación de email y recuperación de contraseña ──────────────
CREATE TABLE IF NOT EXISTS email_verifications (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    token_hash CHAR(64)     NOT NULL,
    expires_at DATETIME     NOT NULL,
    used_at    DATETIME     NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_emailver_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_emailver_token (token_hash),
    KEY idx_emailver_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    token_hash CHAR(64)     NOT NULL,
    expires_at DATETIME     NOT NULL,
    used_at    DATETIME     NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pwreset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_pwreset_token (token_hash),
    KEY idx_pwreset_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Favoritos por usuario (wishlist) ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS favorites (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_favorites_user    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    CONSTRAINT fk_favorites_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY uq_favorites_user_product (user_id, product_id),
    KEY idx_favorites_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Direcciones de envío guardadas por usuario ────────────────────────────────────
CREATE TABLE IF NOT EXISTS user_addresses (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id          INT UNSIGNED NOT NULL,
    etiqueta         VARCHAR(60)  NULL,
    nombre           VARCHAR(150) NOT NULL,
    telefono         VARCHAR(30)  NULL,
    calle            VARCHAR(255) NOT NULL,
    colonia          VARCHAR(150) NULL,
    cp               VARCHAR(10)  NULL,
    ciudad           VARCHAR(120) NULL,
    estado           VARCHAR(120) NULL,
    referencias      VARCHAR(255) NULL,
    es_predeterminada TINYINT(1)  NOT NULL DEFAULT 0,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_addresses_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    KEY idx_addresses_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Pedidos (sin cambios funcionales) ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS orders (
    id               INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    user_id          INT UNSIGNED  NULL,
    nombre_cliente   VARCHAR(150)  NOT NULL,
    ciudad           VARCHAR(120)  NULL,
    telefono         VARCHAR(30)   NULL,
    direccion_envio  TEXT          NULL,
    total            DECIMAL(10,2) NOT NULL,
    estado           ENUM('pendiente','confirmado','enviado','entregado','cancelado')
                                   NOT NULL DEFAULT 'pendiente',
    stock_repuesto   TINYINT(1)    NOT NULL DEFAULT 0,
    mensaje_whatsapp TEXT          NULL,
    created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    KEY idx_orders_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Items de pedido (producto_id ahora FK a products, NULL si producto eliminado) ─
CREATE TABLE IF NOT EXISTS order_items (
    id              INT UNSIGNED      AUTO_INCREMENT PRIMARY KEY,
    order_id        INT UNSIGNED      NOT NULL,
    producto_id     INT UNSIGNED      NULL,
    sabor_id        INT UNSIGNED      NULL,
    nombre_producto VARCHAR(255)      NOT NULL,
    sabor           VARCHAR(80)       NULL,
    precio_unitario DECIMAL(10,2)     NOT NULL,
    cantidad        SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    subtotal        DECIMAL(10,2)     NOT NULL,
    CONSTRAINT fk_items_order   FOREIGN KEY (order_id)    REFERENCES orders(id)   ON DELETE CASCADE,
    CONSTRAINT fk_items_product FOREIGN KEY (producto_id) REFERENCES products(id) ON DELETE SET NULL,
    CONSTRAINT fk_items_flavor  FOREIGN KEY (sabor_id)    REFERENCES product_flavors(id) ON DELETE SET NULL,
    KEY idx_items_order (order_id),
    KEY idx_items_sabor (sabor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Rate limiting de logins (anti-fuerza bruta, cliente y admin) ──────────────────
CREATE TABLE IF NOT EXISTS login_attempts (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo       ENUM('cliente','admin') NOT NULL,
    email      VARCHAR(190) NOT NULL,
    ip         VARCHAR(45)  NOT NULL,
    exitoso    TINYINT(1)   NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_attempts_lookup (tipo, email, ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
