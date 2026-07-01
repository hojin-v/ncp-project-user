<?php
declare(strict_types=1);

require __DIR__ . '/../src/db.php';

try {
    $pdo = db();
    $stmt = $pdo->query('SELECT COUNT(*) AS current_count FROM weather_current');
    $row = $stmt->fetch();
    $count = (int)($row['current_count'] ?? 0);
    if ($count < 1) {
        throw new RuntimeException('weather_current is empty');
    }

    http_response_code(200);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'READY';
} catch (Throwable $e) {
    error_log($e);
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'NOT_READY';
}
