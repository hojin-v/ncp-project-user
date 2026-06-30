<?php
// DB에 저장된 날씨 조회 API. 외부 API 직접 호출 없음.
declare(strict_types=1);

require __DIR__ . '/../../src/db.php';

try {
    $pdo = db();
    $cities = weather_api_cities($pdo);
    $cityCode = weather_api_city_code($_GET['city'] ?? null, $cities);
    $current = weather_api_current($pdo, $cityCode);
    if ($current === null) {
        json_response(['error' => 'weather not found'], 404);
    }

    json_response([
        'cities' => array_values($cities),
        'selected_city' => $cityCode,
        'current' => $current,
        'forecast' => weather_api_forecast_summary($pdo, $cityCode),
    ]);
} catch (InvalidArgumentException $e) {
    json_response(['error' => $e->getMessage()], 400);
} catch (Throwable $e) {
    error_log($e);
    json_response(['error' => 'internal server error'], 500);
}

function weather_api_cities(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT city_code, city_name
         FROM weather_current
         ORDER BY FIELD(city_code, "seoul", "busan", "jeju"), city_name'
    );
    $cities = [];
    foreach ($stmt->fetchAll() as $row) {
        $cities[$row['city_code']] = [
            'code' => $row['city_code'],
            'name' => $row['city_name'],
        ];
    }
    return $cities;
}

function weather_api_city_code(mixed $requested, array $cities): string
{
    if ($cities === []) {
        throw new InvalidArgumentException('weather data is empty');
    }
    $code = trim((string)($requested ?? ''));
    if ($code === '') {
        return array_key_first($cities);
    }
    if (!isset($cities[$code])) {
        throw new InvalidArgumentException('unknown city');
    }
    return $code;
}

function weather_api_current(PDO $pdo, string $cityCode): ?array
{
    $stmt = $pdo->prepare(
        'SELECT city_code, city_name, nx, ny, base_date, base_time, observed_at,
                temperature_c, humidity_percent, precipitation_type_name,
                precipitation_1h, precipitation_1h_mm, wind_speed_ms, wind_direction_deg, updated_at
         FROM weather_current
         WHERE city_code = :city_code'
    );
    $stmt->execute([':city_code' => $cityCode]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    return [
        'city_code' => $row['city_code'],
        'city_name' => $row['city_name'],
        'observed_at' => $row['observed_at'],
        'updated_at' => $row['updated_at'],
        'base' => $row['base_date'] . ' ' . $row['base_time'],
        'temperature_c' => $row['temperature_c'] === null ? null : (float)$row['temperature_c'],
        'humidity_percent' => $row['humidity_percent'] === null ? null : (int)$row['humidity_percent'],
        'precipitation_type_name' => $row['precipitation_type_name'],
        'precipitation_1h' => $row['precipitation_1h'],
        'precipitation_1h_mm' => $row['precipitation_1h_mm'] === null ? null : (float)$row['precipitation_1h_mm'],
        'wind_speed_ms' => $row['wind_speed_ms'] === null ? null : (float)$row['wind_speed_ms'],
        'wind_direction_deg' => $row['wind_direction_deg'] === null ? null : (int)$row['wind_direction_deg'],
        'summary' => weather_api_current_summary($row),
    ];
}

function weather_api_forecast_summary(PDO $pdo, string $cityCode): array
{
    $stmt = $pdo->prepare(
        'SELECT forecast_date, forecast_time, forecast_at, temperature_c, temp_min_c, temp_max_c,
                sky_name, precipitation_type_name, precipitation_probability_percent,
                precipitation_amount, humidity_percent, wind_speed_ms
         FROM weather_forecast
         WHERE city_code = :city_code
         ORDER BY forecast_at
         LIMIT 160'
    );
    $stmt->execute([':city_code' => $cityCode]);

    $days = [];
    foreach ($stmt->fetchAll() as $row) {
        $date = $row['forecast_date'];
        $temp = $row['temperature_c'] === null ? null : (float)$row['temperature_c'];
        $min = $row['temp_min_c'] === null ? $temp : (float)$row['temp_min_c'];
        $max = $row['temp_max_c'] === null ? $temp : (float)$row['temp_max_c'];
        $pop = $row['precipitation_probability_percent'] === null ? null : (int)$row['precipitation_probability_percent'];
        $pty = (string)$row['precipitation_type_name'];
        $sky = (string)($row['sky_name'] ?? '');

        $days[$date] ??= [
            'date' => $date,
            'label' => weather_api_date_label($date),
            'temp_min_c' => null,
            'temp_max_c' => null,
            'precipitation_probability_percent' => null,
            'condition' => null,
            'hours' => 0,
            'conditions' => [],
        ];
        if ($min !== null) {
            $days[$date]['temp_min_c'] = $days[$date]['temp_min_c'] === null ? $min : min($days[$date]['temp_min_c'], $min);
        }
        if ($max !== null) {
            $days[$date]['temp_max_c'] = $days[$date]['temp_max_c'] === null ? $max : max($days[$date]['temp_max_c'], $max);
        }
        if ($pop !== null) {
            $days[$date]['precipitation_probability_percent'] = max($days[$date]['precipitation_probability_percent'] ?? 0, $pop);
        }
        $condition = $pty !== '' && $pty !== '없음' ? $pty : ($sky !== '' ? $sky : '예보');
        $days[$date]['conditions'][$condition] = ($days[$date]['conditions'][$condition] ?? 0) + 1;
        $days[$date]['hours']++;
    }

    $result = [];
    foreach ($days as $day) {
        arsort($day['conditions']);
        $day['condition'] = array_key_first($day['conditions']) ?: '예보';
        unset($day['conditions']);
        $result[] = $day;
        if (count($result) >= 5) {
            break;
        }
    }
    return $result;
}

function weather_api_current_summary(array $row): string
{
    $temp = $row['temperature_c'] === null ? '-' : rtrim(rtrim((string)$row['temperature_c'], '0'), '.') . '도';
    $rain = $row['precipitation_type_name'] === '없음' ? '강수 없음' : $row['precipitation_type_name'];
    return "{$row['city_name']} {$temp}, {$rain}";
}

function weather_api_date_label(string $ymd): string
{
    $dt = DateTimeImmutable::createFromFormat('Ymd', $ymd, new DateTimeZone('Asia/Seoul'));
    if (!$dt instanceof DateTimeImmutable) {
        return $ymd;
    }
    $days = ['일', '월', '화', '수', '목', '금', '토'];
    return $dt->format('n.j') . ' ' . $days[(int)$dt->format('w')];
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
