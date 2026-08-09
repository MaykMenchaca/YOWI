<?php
declare(strict_types=1);

require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../lib/Response.php';
require __DIR__ . '/../../lib/AdminSession.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') ds_json_error('Método no permitido', 405);

$adminId = ds_require_admin(true); // consultar el propio estado no requiere ya estar enrolado
$pdo = ds_get_pdo();
$stmt = $pdo->prepare('SELECT totp_enabled FROM admins WHERE id = ?');
$stmt->execute([$adminId]);
$enabled = (int) $stmt->fetchColumn() === 1;

ds_json_success(['enabled' => $enabled]);
