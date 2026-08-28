# Resumen del proyecto — Distribuidor de Suplementos (DS)

> Documento de referencia rápida: qué es este proyecto y todo lo que se ha hecho en él, de
> principio a fin. Los documentos más detallados de cada tema (`docs/project-context.md`,
> `docs/manual-usuario.md`, `docs/despliegue-hostinger.md`, etc.) siguen siendo la fuente completa
> — este archivo es el mapa para encontrar el resto.

---

## Qué es este proyecto

**Distribuidor de Suplementos (DS)** — tienda en línea de suplementos deportivos, en producción en
**`distribuidordesuplementos.com.mx`** (Hostinger). Catálogo público con búsqueda tolerante a
errores, carrito y checkout que se coordina por **WhatsApp** (el pedido también queda registrado en
la base de datos), cuentas de cliente (favoritos, direcciones, historial de pedidos), y un **panel
de administración completo** para manejar productos, categorías, marcas, pedidos, promociones,
contenido de la página "Nosotros", clientes, otros administradores y seguridad — todo sin tocar
código.

**Dueño del negocio:** Edwin Robles (cuenta admin `Edwinshaiel1@hotmail.com`, rol Dueño).

### Stack técnico

- **Frontend:** HTML estático + Tailwind CSS **compilado y versionado** (sin CDN en producción) +
  JavaScript vanilla (sin framework). Fuentes Barlow Condensed/Barlow, íconos Material Symbols.
- **Backend:** PHP plano + PDO (sin framework, sin Composer). API JSON consistente (`{ok,data}` /
  `{ok,error}`).
- **Base de datos:** MySQL/MariaDB — `sql/schema.sql` + 21 migraciones idempotentes en
  `sql/migrations/`.
- **Hosting:** Hostinger (plan Business), sin pasarela de pago — el cobro se coordina por WhatsApp
  (transferencia SPEI).

### Repositorio

Este repositorio (`YOWI` en GitHub, cuenta `MaykMenchaca`) contiene dos cosas superpuestas — vale
la pena distinguirlas para no confundirse:

1. **La tienda DS en sí**, dentro de `site/` (lo único que se sube al hosting), más `sql/`,
   `scripts/` y `docs/`.
2. **FLUJO**, la metodología/plantilla multi-agente de Claude Code descrita en el `README.md` y
   `CLAUDE.md` de la raíz — un sistema genérico reutilizable en cualquier proyecto, no específico de
   DS. Sus skills viven en `.claude/skills/` y están **excluidas** del grafo de conocimiento
   (`.graphifyignore`) para no mezclarse con el código real de la tienda.

Rama de trabajo habitual: `claude/last-push-timing-yul4fb`, mergeada seguido a `main`.

---

## Todo lo que se ha hecho (en orden)

### 1. Información del negocio editable, contenido real y páginas legales (8 fases)
- Una sola fuente de verdad para el contenido editable (`site/api/lib/Settings.php`) — teléfono,
  dirección, mapa, redes sociales, misión/visión, todo editable desde **Panel → Nosotros**, sin
  tocar código ni repetido en 8 archivos como antes.
- Páginas legales nuevas: `terminos.html` (Políticas de compra/envío) y `privacidad.html` (Aviso de
  Privacidad — declara con precisión la transferencia de datos a WhatsApp/Meta y a la paquetería).
- **Consentimiento que sí queda registrado**: `register.php` y `orders/create.php` exigen el
  checkbox del lado del servidor y guardan la fecha real de aceptación.
- **Borrado de cuenta y datos**: tanto el cliente (`cuenta.html`) como el dueño
  (`panel-4x9qz/clientes.html`) pueden eliminar una cuenta — anonimiza sus pedidos (se conservan
  para contabilidad) en vez de borrarlos.
- Corrección de enlaces rotos y detalles menores de UI.

### 2. Cifrado del 2FA + pantalla de auditoría
- El secreto TOTP de cada administrador (`admins.totp_secret`) ya no se guarda en texto plano —
  se cifra con `sodium_crypto_secretbox` (`site/api/lib/Crypto.php`), clave en
  `TOTP_ENCRYPTION_KEY` (solo en `env.php` del servidor).
- Nueva pantalla **Panel → Auditoría** (solo Dueño): lee `admin_audit_log` (que ya se llenaba sola)
  con filtros por administrador, acción y fecha — antes solo se podía consultar directo en la base.

### 3. Despliegue completo a producción (Hostinger)
Guiado paso a paso en vivo, documentado en `docs/despliegue-hostinger.md` y en el PDF **"Histórico
del primer despliegue"**:
- Base de datos creada e importada (`schema.sql` + 21 migraciones).
- `env.php` configurado (credenciales, correo `no-reply@distribuidordesuplementos.com.mx`, clave
  del 2FA).
- Sitio subido a `public_html/`.
- Acceso SSH activado y usado para crear la cuenta de dueño, generar hashes de contraseña, y
  diagnosticar errores reales (extensión `sodium` deshabilitada por defecto en el PHP de Hostinger
  — se activó desde hPanel).
- Verificaciones de seguridad completas: HTTPS forzado, cabeceras de seguridad, `env.php` y
  `api/lib/` bloqueados (403), límites de subida de archivos.
- Respaldos automáticos diarios por cron (`scripts/backup-db.sh`), con rotación de 7 diarios / 4
  semanales.

### 4. Ruta del panel renombrada (`/admin/` → `/panel-4x9qz/`)
Por seguridad — la ruta obvia invitaba a bots/curiosos a probarla. El login/2FA ya protegían el
acceso real; esto es una capa extra. Cambio de bajo riesgo (todos los enlaces internos del panel
usan rutas relativas). Aplicado tanto en el repositorio como en el servidor en vivo.

### 5. Corrección de bugs reales reportados en móvil
- **Menú de "Mi cuenta" pegado al hacer scroll**: `sticky` sin restringir a pantallas grandes
  aplicaba también en móvil. Ahora solo es `sticky` en `md:` en adelante.
- **Precio "duplicado" en el carrito**: eran dos valores distintos (precio unitario y subtotal) sin
  ninguna etiqueta que los distinguiera — se agregaron las etiquetas "Precio unitario" y "Subtotal".
- **Botón "Cerrar sesión" que no hacía nada**: el único POST del sitio de cliente que no mandaba el
  token de seguridad (CSRF) — el servidor siempre lo rechazaba en silencio. Corregido y verificado
  con un test automatizado.
- **Filtro de "Categoría" desplegable**: con ~20 categorías reales, la lista de casillas siempre
  visible ocupaba demasiado espacio en el celular — ahora es un `<details>/<summary>` cerrado por
  defecto, igual que el filtro de Marca, sin perder la selección múltiple.

### 6. Manual de usuario actualizado + guía de importación de catálogo
- `docs/manual-usuario.md` (y su PDF) actualizado con las 3 pantallas que faltaban (Clientes,
  Usuarios, Auditoría) y las páginas legales/borrado de cuenta que tampoco estaban documentadas.
- Explicación completa del formato del CSV de importación de productos: las 16 columnas, cómo se
  llenan `sabores` e `imagen`, qué significa una celda vacía vs. `activo=0`.
- Guía de cómo subir imágenes de producto a Hostinger (`public_html/assets/img/productos/<categoría>/`)
  y qué ruta poner en la columna `imagen` del CSV.

### 7. Grafo de conocimiento (Graphify)
`graphify` instalado y corrido sobre el repositorio para tener un mapa consultable de dependencias
del código real (`graphify query`, `graphify explain`, `graphify path`). Se agregó
`.graphifyignore` para excluir `.claude/skills/` (tooling de FLUJO, no del negocio) — sin eso, esas
~3000 nodos de infraestructura ahogaban cualquier consulta real sobre la tienda. El grafo en sí
(`graphify-out/`) no se versiona (es un output regenerable); solo la configuración
(`.graphifyignore`) queda en el repositorio.

---

## Dónde encontrar cada cosa

| Pregunta | Documento |
|---|---|
| "¿Cómo se usa el panel/la tienda? ¿Qué hace cada pantalla?" | `docs/manual-usuario.md` |
| "¿Cómo despliego/actualizo el sitio en Hostinger?" | `docs/despliegue-hostinger.md` |
| "¿Qué decisiones se tomaron y por qué? ¿Qué falta a propósito?" | `docs/project-context.md` |
| "¿Cómo protejo más el panel (Basic Auth, restricción por IP)?" | `docs/seguridad-operativa.md` |
| "¿Cómo corro el sitio en mi computadora para probar cambios?" | `docs/setup-local.md` |
| "¿Qué reglas de seguridad debe seguir código nuevo?" | `docs/guia-seguridad.md` |
| "¿Qué es FLUJO / la metodología multi-agente?" | `README.md` + `CLAUDE.md` (raíz) |

## Datos operativos (sin contraseñas)

| Dato | Valor |
|---|---|
| Dominio | `distribuidordesuplementos.com.mx` |
| Ruta del panel de administrador | `/panel-4x9qz/login.html` |
| Cuenta dueño | Edwin Robles — `Edwinshaiel1@hotmail.com` |
| Correo de sistema | `no-reply@distribuidordesuplementos.com.mx` |
| Base de datos / usuario BD | `u996754854_dssupp` / `u996754854_app` |
| Ruta real de `public_html` en el servidor | `~/domains/distribuidordesuplementos.com.mx/public_html` |
| Respaldos automáticos | Diarios 3:15 am, `~/backups/daily/` y `~/backups/weekly/` |

Ninguna contraseña, hash ni clave real vive en este archivo ni en ningún otro del repositorio —
todas están únicamente en `env.php` del servidor (nunca en git) o en hPanel.
