# Usuarios del panel — roles y permisos

Guía para el dueño de la tienda: qué hace cada modo, cómo dar de alta a un empleado y cómo
darlo de baja. Para el detalle técnico de cómo está implementado, ver
`docs/guia-seguridad.md`.

## Los 3 modos

| | **Dueño** | **Operador** | **Solo lectura** |
|---|:--:|:--:|:--:|
| Ver catálogo, pedidos, categorías, marcas, promociones | ✅ | ✅ | ✅ |
| Cambiar el estado de un pedido (confirmar, enviar, cancelar) | ✅ | ✅ | — |
| Crear/editar/borrar productos, categorías, marcas, promociones, sabores, imágenes | ✅ | ✅ | — |
| Importar/exportar CSV del catálogo | ✅ | ✅ | — |
| Descargar el respaldo completo (incluye datos de todos los clientes) | ✅ | — | — |
| Borrado masivo (vaciar catálogo, categorías, marcas) | ✅ | — | — |
| Configuración del sitio ("Nosotros": misión, contacto, WhatsApp) | ✅ | — | — |
| Crear/editar/desactivar/eliminar usuarios del panel | ✅ | — | — |
| Su propio 2FA y su propia contraseña | ✅ | ✅ | ✅ |

**Recomendación de uso:**
- **Operador** — para quien surte pedidos y mantiene el catálogo al día (la mayoría de tus
  empleados de operación diaria).
- **Solo lectura** — para alguien de confianza que solo necesita consultar (un socio, tu
  contador, un empleado nuevo antes de subirlo a Operador). Cero riesgo de que toque algo
  por accidente.
- **Dueño** — resérvalo. Cualquier cuenta Dueño puede ver los datos de todos tus clientes
  (respaldo completo) y crear más cuentas Dueño.

## Dar de alta a un empleado

1. Panel → **Usuarios** → **+ Nuevo usuario**.
2. Nombre, correo, una contraseña inicial (mínimo 8 caracteres) y el rol.
3. Pásale la contraseña por un canal privado (WhatsApp, en persona — nunca por correo sin
   cifrar si puedes evitarlo).
4. En su primer ingreso, el panel **lo obliga a activar la verificación en dos pasos (2FA)**
   antes de dejarlo hacer nada — sin excepción, para los 3 modos.
5. Recomiéndale cambiar esa contraseña inicial desde **Seguridad → Cambiar mi contraseña**.

## Dar de baja a un empleado

Usa **Desactivar** (botón en la fila de Usuarios), no Eliminar:
- La cuenta queda bloqueada al instante — si tenía una sesión abierta, su siguiente clic
  falla.
- Se conserva su historial: quién hizo qué mientras trabajó sigue siendo consultable.
- Si vuelve a contratarlo, lo reactivas con el mismo botón y conserva su rol anterior.

**Eliminar** borra la cuenta de verdad y solo debería usarse para cuentas creadas por
error — al eliminar, las acciones que esa cuenta hizo en el pasado quedan sin el nombre
asociado.

## Si alguien pierde el acceso a su celular (2FA)

Puede entrar con uno de sus **códigos de recuperación** (se le mostraron al activar el
2FA, una sola vez — dile que los guarde bien). Si también los perdió, tú (Dueño) le
reseteas la contraseña desde **Usuarios**, y luego él vuelve a activar el 2FA desde cero.

## Restablecer la contraseña de un empleado

**Usuarios** → fila del empleado → **Contraseña** → escribe la nueva. Esto cierra
cualquier sesión que tuviera abierta.

## Reglas que el sistema no te deja romper (por tu propia seguridad)

- No puedes cambiar tu propio rol, ni desactivarte, ni eliminarte a ti mismo — así nunca
  te quedas fuera de tu propio panel por accidente.
- Siempre debe quedar al menos un Dueño activo en la tienda.

## Lo que NO existe todavía

- Permisos "a la carta" (marcar casillas por persona) — son 3 modos fijos, a propósito:
  más simple de configurar y más difícil de dejar un hueco sin querer.
- Una pantalla para ver el historial completo de auditoría desde el panel (la información
  se guarda, pero hoy solo es consultable por base de datos). Es la siguiente mejora
  recomendada si vas a tener varios empleados.
