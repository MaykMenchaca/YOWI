<?php
declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../lib/Response.php';
require __DIR__ . '/../lib/Mailer.php';

// GET desde el enlace del correo. Redirige a una página amable según el resultado.
$appUrl = ds_app_url();
$okUrl   = ($appUrl ?: '.') . '/login.html?verificado=1';
$failUrl = ($appUrl ?: '.') . '/login.html?verificado=0';

function ds_redirect(string $url): void
{
    header('Location: ' . $url, true, 302);
    exit;
}

$token = (string) ($_GET['token'] ?? '');
if ($token === '' || !ctype_xdigit($token)) {
    ds_redirect($failUrl);
}

$pdo  = ds_get_pdo();
$hash = ds_hash_token($token);

$stmt = $pdo->prepare(
    'SELECT id, user_id FROM email_verifications
     WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()'
);
$stmt->execute([$hash]);
$row = $stmt->fetch();

if (!$row) {
    ds_redirect($failUrl);
}

$pdo->beginTransaction();
try {
    $pdo->prepare('UPDATE users SET email_verified = 1 WHERE id = ?')->execute([(int) $row['user_id']]);
    $pdo->prepare('UPDATE email_verifications SET used_at = NOW() WHERE id = ?')->execute([(int) $row['id']]);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('verify-email.php: ' . $e->getMessage());
    ds_redirect($failUrl);
}

ds_redirect($okUrl);
