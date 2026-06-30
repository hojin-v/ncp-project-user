<?php
// web-user-01 전용 설정 템플릿.
// 실제 위치: /var/www/weather-app-config/config.php
// 실제 비밀번호/API키는 이 repo에 커밋하지 않는다.

return [
    'db' => [
        'host' => '<CDB_PRIVATE_ENDPOINT>',
        'database' => 'weather_board',
        'username' => 'weather_user',
        'password' => 'CHANGE_ME',
        'charset' => 'utf8mb4',
    ],
    'weather' => [
        'service_key' => 'CHANGE_ME',
        'base_url' => 'https://apis.data.go.kr/1360000/VilageFcstInfoService_2.0',
    ],
];
