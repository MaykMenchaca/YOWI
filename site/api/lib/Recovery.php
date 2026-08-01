<?php
// Códigos de recuperación del 2FA de admin. Respaldo si se pierde el autenticador.
// Se guarda SOLO el hash (SHA-256) del código normalizado; el texto se muestra una vez.

declare(strict_types=1);

/** Normaliza un código: minúsculas y sin separadores, para hashear/comparar. */
function ds_recovery_normalize(string $code): string
{
    return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $code));
}

/**
 * Genera N códigos nuevos para el admin, INVALIDANDO los anteriores. Devuelve los
 * códigos en claro (formato "abcde-fghij") para mostrarlos una sola vez.
 */
function ds_generate_recovery_codes(PDO $pdo, int $adminId, int $count = 10): array
{
    $pdo->prepare('DELETE FROM admin_recovery_codes WHERE admin_id = ?')->execute([$adminId]);
    $ins = $pdo->prepare('INSERT INTO admin_recovery_codes (admin_id, code_hash) VALUES (?, ?)');

    $plain = [];
    for ($i = 0; $i < $count; $i++) {
        $raw = bin2hex(random_bytes(5)); // 10 caracteres hex
        $display = substr($raw, 0, 5) . '-' . substr($raw, 5, 5);
        $plain[] = $display;
        $ins->execute([$adminId, hash('sha256', ds_recovery_normalize($display))]);
    }
    return $plain;
}

/**
 * Verifica y CONSUME un código de recuperación (un solo uso). Devuelve true si era
 * válido (y lo marca como usado). Comparación por hash exacto en columna indexada.
 */
function ds_consume_recovery_code(PDO $pdo, int $adminId, string $code): bool
{
    $norm = ds_recovery_normalize($code);
    if ($norm === '') {
        return false;
    }
    $hash = hash('sha256', $norm);
    $stmt = $pdo->prepare(
        'SELECT id FROM admin_recovery_codes WHERE admin_id = ? AND code_hash = ? AND used_at IS NULL'
    );
    $stmt->execute([$adminId, $hash]);
    $id = $stmt->fetchColumn();
    if ($id === false) {
        return false;
    }
    $pdo->prepare('UPDATE admin_recovery_codes SET used_at = NOW() WHERE id = ?')->execute([(int) $id]);
    return true;
}

/** Cuántos códigos de recuperación sin usar le quedan al admin. */
function ds_recovery_remaining(PDO $pdo, int $adminId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM admin_recovery_codes WHERE admin_id = ? AND used_at IS NULL');
    $stmt->execute([$adminId]);
    return (int) $stmt->fetchColumn();
}
