<?php
// 서울/부산/제주 날씨를 data.go.kr 기상청 API에서 가져와 DB에 저장.
// 실행: php bin/fetch_weather.php
declare(strict_types=1);

require __DIR__ . '/../src/weather.php';

try {
    $started = microtime(true);
    $result = weather_collect_all(db());
    $failed = 0;
    foreach ($result as $row) {
        if ($row['status'] === 'ok') {
            printf(
                "[%s] OK current=%s forecast=%s rows=%d\n",
                $row['city'],
                $row['current_base'],
                $row['forecast_base'],
                $row['forecast_rows']
            );
            continue;
        }
        $failed++;
        printf("[%s] FAIL %s\n", $row['city'], $row['error']);
    }
    printf("%s elapsed=%.2fs\n", $failed === 0 ? 'OK' : 'PARTIAL', microtime(true) - $started);
    exit($failed === 0 ? 0 : 1);
} catch (Throwable $e) {
    fwrite(STDERR, "FAIL " . $e->getMessage() . PHP_EOL);
    exit(1);
}
