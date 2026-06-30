<?php
// 설정 로더 — webroot 밖 시크릿 파일을 읽어 배열로 반환.
// 경로는 APP_SECRET_PATH 환경변수로 덮어쓰기 가능(없으면 기본 경로).
declare(strict_types=1);

function app_config(): array
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }
    $path = getenv('APP_SECRET_PATH') ?: '/var/www/weather-app-config/config.php';
    if (!is_readable($path)) {
        http_response_code(500);
        die('config not readable: ' . $path);
    }
    $cfg = require $path;
    return $cfg;
}
