<?php
// CLI 연결 점검: DB만(user-web은 LiteLLM 비접근). 실행: php bin/healthcheck.php
declare(strict_types=1);
require __DIR__ . '/../src/db.php';

echo "[DB] ";
try {
    $n = db()->query('SELECT COUNT(*) AS c FROM comments')->fetch()['c'];
    echo "OK (comments=$n)\n";
} catch (Throwable $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}
