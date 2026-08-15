# Seguridad operativa — panel de administración (Hostinger)

Medidas de blindaje del panel `/panel-4x9qz` que se configuran en Hostinger (no en código),
más las que ya están en el código.

## 1. 2FA obligatorio (ya en código)

Todo administrador debe activar la verificación en dos pasos. Al entrar sin 2FA, el panel
**redirige a `panel-4x9qz/seguridad.html`** y no deja operar hasta activarlo. Si se pierde el
autenticador, hay **códigos de recuperación** (se muestran al activar; se pueden regenerar).

## 2. Segundo candado con "Password Protect Directories" (hPanel — recomendado)

Añade una autenticación HTTP básica **antes** del panel, sin requerir IP fija. Así, aunque
alguien conociera la contraseña de admin, primero choca con este candado.

Pasos en hPanel:
1. hPanel → **Sitios web** → *Administrar* del dominio.
2. Buscar **"Password Protect Directories"** (Directorios protegidos con contraseña).
3. Seleccionar la carpeta del panel: la que contiene `panel-4x9qz/` (p. ej.
   `public_html/panel-4x9qz` si el docroot es la raíz, o `panel-4x9qz` si el docroot es `site/`).
4. Asignar un **usuario y contraseña** propios (distintos de los del panel) y guardar.

> Nota: protege solo las **páginas** del panel (`panel-4x9qz/`). La API (`api/admin/`) sigue
> protegida por sesión + CSRF + 2FA; si además quieres Basic Auth en la API, protege
> también la carpeta `api/admin`, pero entonces el navegador pedirá la clave en cada
> llamada AJAX (peor experiencia). Lo habitual es proteger solo `panel-4x9qz/`.

## 3. Restringir por IP (opcional — SOLO si tienes IP fija)

Si tu conexión tiene **IP fija** (oficina, VPN con IP estática), puedes limitar el panel a
esas IPs. ⚠️ Con IP **dinámica** (internet residencial) te arriesgas a **bloquearte a ti
mismo** cuando cambie tu IP.

Crea un `.htaccess` **dentro de `site/panel-4x9qz/`** (y otro en `site/api/admin/`) con:

```apache
# Solo estas IPs pueden abrir el panel. Sustituye por las tuyas.
<RequireAny>
    Require ip 200.0.0.0          # tu IP pública fija
    Require ip 190.1.2.0/24       # o un rango, en CIDR
</RequireAny>
```

Para saber tu IP pública: abre `https://ifconfig.me` desde tu red. Si cambia, actualiza el
archivo. Si te bloqueas, borra ese `.htaccess` desde el Administrador de archivos de hPanel.

## 4. Log de auditoría (ya en código)

Cada acción de escritura del admin (crear/editar/borrar productos, marcas, categorías,
banners, cambiar estado de pedidos, editar "Nosotros", cambios de 2FA, borrados masivos)
queda registrada en la tabla **`admin_audit_log`** con: quién (`admin_id`), qué (`accion`),
IP y fecha. Consulta rápida:

```sql
SELECT a.created_at, ad.email, a.accion, a.ip
FROM admin_audit_log a LEFT JOIN admins ad ON ad.id = a.admin_id
ORDER BY a.created_at DESC LIMIT 50;
```

## 5. Cloudflare (opcional, desde hPanel)

Hostinger ya trae **ModSecurity WAF** activo. Si quieres una capa extra a nivel DNS
(firewall de aplicación, mitigación de bots/DDoS, caché), actívala en:
hPanel → **Dominios** → **Cloudflare** → estado *Enabled* + SSL *Full*.

## Checklist
- [ ] Cada admin activó su 2FA y **guardó** sus códigos de recuperación.
- [ ] "Password Protect Directories" activo sobre `panel-4x9qz/`.
- [ ] (Opcional) IP allowlist si tienes IP fija.
- [ ] Revisar `admin_audit_log` periódicamente.
- [ ] (Opcional) Cloudflare activado desde hPanel.
