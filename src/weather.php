<?php
// data.go.kr 기상청_단기예보 조회서비스 연동 및 DB 저장.
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

const WEATHER_API_BASE = 'http://apis.data.go.kr/1360000/VilageFcstInfoService_2.0';

function weather_cities(): array
{
    return [
        'seoul' => ['code' => 'seoul', 'name' => '서울', 'nx' => 60, 'ny' => 127],
        'busan' => ['code' => 'busan', 'name' => '부산', 'nx' => 98, 'ny' => 76],
        'jeju'  => ['code' => 'jeju',  'name' => '제주', 'nx' => 52, 'ny' => 38],
    ];
}

function weather_collect_all(PDO $pdo): array
{
    $result = [];
    foreach (weather_cities() as $city) {
        $row = [
            'city' => $city['name'],
            'current_base' => null,
            'forecast_base' => null,
            'forecast_rows' => 0,
            'status' => 'ok',
            'error' => null,
        ];
        try {
            $current = weather_fetch_current($city);
            weather_save_current($pdo, $city, $current);
            $row['current_base'] = $current['base_date'] . ' ' . $current['base_time'];

            $forecast = weather_fetch_forecast($city);
            $row['forecast_rows'] = weather_save_forecast($pdo, $city, $forecast);
            $row['forecast_base'] = $forecast['base_date'] . ' ' . $forecast['base_time'];
        } catch (Throwable $e) {
            $row['status'] = 'failed';
            $row['error'] = $e->getMessage();
        }
        $result[] = $row;
        if ($row['status'] === 'failed' && str_contains(strtolower((string)$row['error']), 'rate limit')) {
            break;
        }
    }
    return $result;
}

function weather_fetch_current(array $city): array
{
    [$baseDate, $baseTime] = weather_latest_ultra_srt_ncst_base();
    $items = weather_api_request('getUltraSrtNcst', [
        'pageNo' => 1,
        'numOfRows' => 100,
        'dataType' => 'JSON',
        'base_date' => $baseDate,
        'base_time' => $baseTime,
        'nx' => $city['nx'],
        'ny' => $city['ny'],
    ]);

    return [
        'base_date' => $baseDate,
        'base_time' => $baseTime,
        'items' => $items,
    ];
}

function weather_fetch_forecast(array $city): array
{
    [$baseDate, $baseTime] = weather_latest_vilage_fcst_base();
    $items = weather_api_request('getVilageFcst', [
        'pageNo' => 1,
        'numOfRows' => 1000,
        'dataType' => 'JSON',
        'base_date' => $baseDate,
        'base_time' => $baseTime,
        'nx' => $city['nx'],
        'ny' => $city['ny'],
    ]);

    return [
        'base_date' => $baseDate,
        'base_time' => $baseTime,
        'items' => $items,
    ];
}

function weather_api_request(string $endpoint, array $params): array
{
    $cfg = app_config()['weather'] ?? [];
    $serviceKey = trim((string)($cfg['service_key'] ?? ''));
    if ($serviceKey === '') {
        throw new RuntimeException('weather.service_key is empty');
    }

    $url = WEATHER_API_BASE . '/' . $endpoint . '?' . weather_build_query($serviceKey, $params);
    $context = stream_context_create([
        'http' => [
            'timeout' => 12,
            'ignore_errors' => true,
            'header' => "User-Agent: weather-commenting-app/1.0\r\n",
        ],
    ]);
    $body = @file_get_contents($url, false, $context);
    if ($body === false) {
        $error = error_get_last();
        $message = is_array($error) ? (string)($error['message'] ?? 'unknown error') : 'unknown error';
        throw new RuntimeException("weather API request failed: {$endpoint}: {$message}");
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        throw new RuntimeException("weather API returned non-JSON response: " . substr($body, 0, 180));
    }

    $response = $decoded['response'] ?? null;
    $header = is_array($response) ? ($response['header'] ?? []) : [];
    $resultCode = (string)($header['resultCode'] ?? '');
    if ($resultCode !== '00') {
        $message = (string)($header['resultMsg'] ?? 'unknown error');
        throw new RuntimeException("weather API error {$resultCode}: {$message}");
    }

    $items = $response['body']['items']['item'] ?? [];
    if (!is_array($items)) {
        return [];
    }
    return array_is_list($items) ? $items : [$items];
}

function weather_build_query(string $serviceKey, array $params): string
{
    $encodedKey = str_contains($serviceKey, '%') ? $serviceKey : rawurlencode($serviceKey);
    $parts = ['serviceKey=' . $encodedKey];
    foreach ($params as $key => $value) {
        $parts[] = rawurlencode((string)$key) . '=' . rawurlencode((string)$value);
    }
    return implode('&', $parts);
}

function weather_save_current(PDO $pdo, array $city, array $payload): void
{
    $byCategory = [];
    foreach ($payload['items'] as $item) {
        if (isset($item['category'])) {
            $byCategory[$item['category']] = (string)($item['obsrValue'] ?? '');
        }
    }

    $pty = weather_int_or_null($byCategory['PTY'] ?? null);
    $rainRaw = $byCategory['RN1'] ?? null;
    $observedAt = weather_datetime_from_ymdhm($payload['base_date'], $payload['base_time']);
    $stmt = $pdo->prepare(
        "INSERT INTO weather_current (
            city_code, city_name, nx, ny, base_date, base_time, observed_at,
            temperature_c, humidity_percent, precipitation_type, precipitation_type_name,
            precipitation_1h, precipitation_1h_mm, wind_speed_ms, wind_direction_deg, raw_payload
        ) VALUES (
            :city_code, :city_name, :nx, :ny, :base_date, :base_time, :observed_at,
            :temperature_c, :humidity_percent, :precipitation_type, :precipitation_type_name,
            :precipitation_1h, :precipitation_1h_mm, :wind_speed_ms, :wind_direction_deg, :raw_payload
        )
        ON DUPLICATE KEY UPDATE
            city_name = VALUES(city_name),
            nx = VALUES(nx),
            ny = VALUES(ny),
            base_date = VALUES(base_date),
            base_time = VALUES(base_time),
            observed_at = VALUES(observed_at),
            temperature_c = VALUES(temperature_c),
            humidity_percent = VALUES(humidity_percent),
            precipitation_type = VALUES(precipitation_type),
            precipitation_type_name = VALUES(precipitation_type_name),
            precipitation_1h = VALUES(precipitation_1h),
            precipitation_1h_mm = VALUES(precipitation_1h_mm),
            wind_speed_ms = VALUES(wind_speed_ms),
            wind_direction_deg = VALUES(wind_direction_deg),
            raw_payload = VALUES(raw_payload)"
    );
    $stmt->execute([
        ':city_code' => $city['code'],
        ':city_name' => $city['name'],
        ':nx' => $city['nx'],
        ':ny' => $city['ny'],
        ':base_date' => $payload['base_date'],
        ':base_time' => $payload['base_time'],
        ':observed_at' => $observedAt,
        ':temperature_c' => weather_float_or_null($byCategory['T1H'] ?? null),
        ':humidity_percent' => weather_int_or_null($byCategory['REH'] ?? null),
        ':precipitation_type' => $pty,
        ':precipitation_type_name' => weather_pty_name($pty),
        ':precipitation_1h' => $rainRaw,
        ':precipitation_1h_mm' => weather_precipitation_mm($rainRaw),
        ':wind_speed_ms' => weather_float_or_null($byCategory['WSD'] ?? null),
        ':wind_direction_deg' => weather_int_or_null($byCategory['VEC'] ?? null),
        ':raw_payload' => json_encode($payload['items'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
}

function weather_save_forecast(PDO $pdo, array $city, array $payload): int
{
    $grouped = [];
    foreach ($payload['items'] as $item) {
        $date = (string)($item['fcstDate'] ?? '');
        $time = (string)($item['fcstTime'] ?? '');
        $category = (string)($item['category'] ?? '');
        if ($date === '' || $time === '' || $category === '') {
            continue;
        }
        $key = $date . $time;
        $grouped[$key]['date'] = $date;
        $grouped[$key]['time'] = $time;
        $grouped[$key]['items'][] = $item;
        $grouped[$key]['values'][$category] = (string)($item['fcstValue'] ?? '');
    }

    $stmt = $pdo->prepare(
        "INSERT INTO weather_forecast (
            city_code, city_name, nx, ny, base_date, base_time, forecast_date, forecast_time, forecast_at,
            temperature_c, temp_min_c, temp_max_c, sky_code, sky_name,
            precipitation_type, precipitation_type_name, precipitation_probability_percent,
            precipitation_amount, humidity_percent, wind_speed_ms, raw_payload
        ) VALUES (
            :city_code, :city_name, :nx, :ny, :base_date, :base_time, :forecast_date, :forecast_time, :forecast_at,
            :temperature_c, :temp_min_c, :temp_max_c, :sky_code, :sky_name,
            :precipitation_type, :precipitation_type_name, :precipitation_probability_percent,
            :precipitation_amount, :humidity_percent, :wind_speed_ms, :raw_payload
        )
        ON DUPLICATE KEY UPDATE
            city_name = VALUES(city_name),
            nx = VALUES(nx),
            ny = VALUES(ny),
            base_date = VALUES(base_date),
            base_time = VALUES(base_time),
            forecast_at = VALUES(forecast_at),
            temperature_c = VALUES(temperature_c),
            temp_min_c = VALUES(temp_min_c),
            temp_max_c = VALUES(temp_max_c),
            sky_code = VALUES(sky_code),
            sky_name = VALUES(sky_name),
            precipitation_type = VALUES(precipitation_type),
            precipitation_type_name = VALUES(precipitation_type_name),
            precipitation_probability_percent = VALUES(precipitation_probability_percent),
            precipitation_amount = VALUES(precipitation_amount),
            humidity_percent = VALUES(humidity_percent),
            wind_speed_ms = VALUES(wind_speed_ms),
            raw_payload = VALUES(raw_payload)"
    );

    $saved = 0;
    foreach ($grouped as $row) {
        $v = $row['values'];
        $pty = weather_int_or_null($v['PTY'] ?? null);
        $sky = weather_int_or_null($v['SKY'] ?? null);
        $stmt->execute([
            ':city_code' => $city['code'],
            ':city_name' => $city['name'],
            ':nx' => $city['nx'],
            ':ny' => $city['ny'],
            ':base_date' => $payload['base_date'],
            ':base_time' => $payload['base_time'],
            ':forecast_date' => $row['date'],
            ':forecast_time' => $row['time'],
            ':forecast_at' => weather_datetime_from_ymdhm($row['date'], $row['time']),
            ':temperature_c' => weather_float_or_null($v['TMP'] ?? null),
            ':temp_min_c' => weather_float_or_null($v['TMN'] ?? null),
            ':temp_max_c' => weather_float_or_null($v['TMX'] ?? null),
            ':sky_code' => $sky,
            ':sky_name' => $sky === null ? null : weather_sky_name($sky),
            ':precipitation_type' => $pty,
            ':precipitation_type_name' => weather_pty_name($pty),
            ':precipitation_probability_percent' => weather_int_or_null($v['POP'] ?? null),
            ':precipitation_amount' => $v['PCP'] ?? null,
            ':humidity_percent' => weather_int_or_null($v['REH'] ?? null),
            ':wind_speed_ms' => weather_float_or_null($v['WSD'] ?? null),
            ':raw_payload' => json_encode($row['items'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        $saved++;
    }
    return $saved;
}

function weather_latest_ultra_srt_ncst_base(): array
{
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Seoul'));
    if ((int)$now->format('i') < 40) {
        $now = $now->modify('-1 hour');
    }
    return [$now->format('Ymd'), $now->format('H') . '00'];
}

function weather_latest_vilage_fcst_base(): array
{
    $now = (new DateTimeImmutable('now', new DateTimeZone('Asia/Seoul')))->modify('-15 minutes');
    $hour = (int)$now->format('H');
    $baseHours = [2, 5, 8, 11, 14, 17, 20, 23];
    $selected = null;
    foreach ($baseHours as $baseHour) {
        if ($hour >= $baseHour) {
            $selected = $baseHour;
        }
    }
    if ($selected === null) {
        $now = $now->modify('-1 day');
        $selected = 23;
    }
    return [$now->format('Ymd'), sprintf('%02d00', $selected)];
}

function weather_datetime_from_ymdhm(string $date, string $time): string
{
    $dt = DateTimeImmutable::createFromFormat('Ymd Hi', $date . ' ' . $time, new DateTimeZone('Asia/Seoul'));
    if (!$dt instanceof DateTimeImmutable) {
        throw new InvalidArgumentException("invalid weather datetime: {$date} {$time}");
    }
    return $dt->format('Y-m-d H:i:s');
}

function weather_float_or_null(?string $value): ?float
{
    if ($value === null || trim($value) === '') {
        return null;
    }
    return is_numeric($value) ? (float)$value : null;
}

function weather_int_or_null(?string $value): ?int
{
    if ($value === null || trim($value) === '') {
        return null;
    }
    return is_numeric($value) ? (int)$value : null;
}

function weather_precipitation_mm(?string $value): ?float
{
    if ($value === null || trim($value) === '') {
        return null;
    }
    if ($value === '강수없음') {
        return 0.0;
    }
    if (str_contains($value, '1mm 미만')) {
        return 0.5;
    }
    if (preg_match('/([0-9]+(?:\.[0-9]+)?)/', $value, $m)) {
        return (float)$m[1];
    }
    return null;
}

function weather_pty_name(?int $code): string
{
    return match ($code) {
        1 => '비',
        2 => '비/눈',
        3 => '눈',
        5 => '빗방울',
        6 => '빗방울/눈날림',
        7 => '눈날림',
        default => '없음',
    };
}

function weather_sky_name(int $code): string
{
    return match ($code) {
        1 => '맑음',
        3 => '구름많음',
        4 => '흐림',
        default => '알수없음',
    };
}
