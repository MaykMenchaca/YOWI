<?php
declare(strict_types=1);

// Borrado/anonimización de una cuenta de cliente — una sola fuente de verdad, usada tanto
// por el propio cliente (api/auth/delete-account.php) como por el dueño desde el panel
// (api/admin/clientes/delete.php). Un borrado ingenuo (solo DELETE FROM users) no basta:
//
//   - orders.user_id es ON DELETE SET NULL (no CASCADE): el pedido sobrevive con nombre,
//     teléfono y domicilio intactos, huérfano de la cuenta que se borró.
//   - orders.mensaje_whatsapp es una COPIA TEXTUAL de esos mismos datos.
//   - login_attempts no tiene relación (FK) con users — guarda el email suelto — así que
//     un DELETE en users nunca lo toca.
//
// Los pedidos SÍ se conservan (hacen falta para la contabilidad del negocio), pero
// anonimizados: sin nombre, teléfono ni domicilio. user_addresses, favorites y los
// tokens de verificación/recuperación sí tienen ON DELETE CASCADE y se van solos con el
// DELETE FROM users.

function ds_delete_and_anonymize_user(PDO $pdo, int $userId, string $email): void
{
    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'UPDATE orders
             SET nombre_cliente = ?, telefono = NULL, direccion_envio = NULL, mensaje_whatsapp = NULL
             WHERE user_id = ?'
        )->execute(['Cliente eliminado', $userId]);

        $pdo->prepare('DELETE FROM login_attempts WHERE tipo = ? AND email = ?')
            ->execute(['cliente', $email]);

        // CASCADE se encarga de user_addresses, favorites, email_verifications y
        // password_resets — no hace falta borrarlos aquí uno por uno.
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
