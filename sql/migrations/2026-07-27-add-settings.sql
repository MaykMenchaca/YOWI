-- Migración: tabla settings (contenido editable desde el admin, ej. la página "Nosotros").
-- Ejecutar en bases ya desplegadas; en instalaciones nuevas ya viene en schema.sql.
CREATE TABLE IF NOT EXISTS settings (
    clave      VARCHAR(60)  NOT NULL PRIMARY KEY,
    valor      TEXT         NULL,
    updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Valores por defecto (los textos actuales de nosotros.html). INSERT IGNORE = no pisa
-- lo que el admin ya haya editado.
INSERT IGNORE INTO settings (clave, valor) VALUES
('nosotros_mision', 'En DS vendemos suplementos deportivos originales de marcas importadas y nacionales. Cada producto viene de distribuidores autorizados, así que entrenas con la seguridad de que tomas exactamente lo que dice la etiqueta. Y si no sabes qué elegir, te asesoramos por WhatsApp antes de que compres.'),
('val1_titulo', 'Autenticidad garantizada'),
('val1_texto', 'Todos nuestros suplementos provienen directamente de los fabricantes oficiales, asegurando fórmulas 100% originales sin adulteraciones.'),
('val2_titulo', 'Mejores precios'),
('val2_texto', 'Optimizamos nuestra cadena de suministro para ofrecerte tarifas competitivas en marcas premium sin comprometer el servicio.'),
('val3_titulo', 'Asesoría personalizada'),
('val3_texto', 'Nuestro equipo de expertos en nutrición deportiva está disponible para guiarte hacia el suplemento ideal para tus objetivos.'),
('contacto_direccion', 'Av. Hidalgo 4320\nZona Centro, Tampico, Tamaulipas'),
('contacto_telefono', '+52 833 424 1599'),
('contacto_email', 'contacto@dssports.com'),
('contacto_horario', 'Lunes a Viernes: 09:00 - 19:00\nSábados: 10:00 - 14:00'),
('contacto_whatsapp', '5218344241599');
