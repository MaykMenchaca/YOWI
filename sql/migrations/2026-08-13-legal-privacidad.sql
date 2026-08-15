-- Migración: aviso de privacidad (Fase 4 del plan de info-negocio-editable).
--
-- legal_privacidad ya existía como clave (Fase 1, sql/migrations/2026-08-12-...), sembrada
-- vacía porque el PDF del dueño no cubre este documento — lo redacta el equipo, no es
-- contenido transcrito. Declara qué datos se recaban en el registro y en el checkout, con
-- quién se comparten (WhatsApp/Meta y la paquetería, a diferencia de lo que decía el PDF
-- de "no compartimos datos con terceros") y cómo ejercer derechos ARCO. Es un texto base:
-- se recomienda revisión legal antes de tratarlo como definitivo (así lo dice la propia
-- página). Idempotente: solo pisa el valor si sigue vacío, para no perder una edición
-- manual del dueño hecha desde el panel.

UPDATE settings SET valor = 'Este aviso de privacidad describe qué datos personales recaba DS Distribuidor de Suplementos, para qué los usa y cómo puedes ejercer tus derechos, conforme a la Ley Federal de Protección de Datos Personales en Posesión de los Particulares (LFPDPPP). El responsable del tratamiento de tus datos es el que se indica al inicio de esta página.

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

Este es un aviso de privacidad base; se recomienda que sea revisado por un profesional legal antes de tratarlo como definitivo.' WHERE clave = 'legal_privacidad' AND valor = '';
