<?php
declare(strict_types=1);

/**
 * Thin client for Open-Meteo (free, no API key) with file-based caching.
 */
final class WeatherService
{
    private const GEO_URL      = 'https://geocoding-api.open-meteo.com/v1/search';
    private const FORECAST_URL = 'https://api.open-meteo.com/v1/forecast';

    public function __construct(
        private readonly string $cacheDir,
        private readonly int $ttl = 600,
    ) {
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }
    }

    /** @return array{name:string,country:string,latitude:float,longitude:float,timezone:string}|null */
    public function geocode(string $city): ?array
    {
        $data = $this->getJson(self::GEO_URL, [
            'name' => $city, 'count' => 1, 'language' => 'en', 'format' => 'json',
        ], 86400);

        $r = $data['results'][0] ?? null;
        if (!$r) {
            return null;
        }
        return [
            'name'      => (string) $r['name'],
            'country'   => (string) ($r['country'] ?? ''),
            'latitude'  => (float) $r['latitude'],
            'longitude' => (float) $r['longitude'],
            'timezone'  => (string) ($r['timezone'] ?? 'auto'),
        ];
    }

    public function forecast(float $lat, float $lon, string $unit = 'celsius'): array
    {
        return $this->getJson(self::FORECAST_URL, [
            'latitude'         => $lat,
            'longitude'        => $lon,
            'current'          => 'temperature_2m,apparent_temperature,relative_humidity_2m,weather_code,wind_speed_10m,is_day',
            'hourly'           => 'temperature_2m,precipitation_probability',
            'daily'            => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max,sunrise,sunset',
            'timezone'         => 'auto',
            'forecast_days'    => 7,
            'forecast_hours'   => 24,
            'temperature_unit' => $unit === 'fahrenheit' ? 'fahrenheit' : 'celsius',
            'wind_speed_unit'  => 'kmh',
        ], $this->ttl);
    }

    private function getJson(string $url, array $query, int $ttl): array
    {
        $full = $url . '?' . http_build_query($query);
        $file = $this->cacheDir . '/' . sha1($full) . '.json';

        if (is_file($file) && (time() - filemtime($file)) < $ttl) {
            $cached = json_decode((string) file_get_contents($file), true);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $ch = curl_init($full);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_USERAGENT      => 'php-weather-app/1.0',
        ];
        if ($ca = getenv('CA_BUNDLE')) {
            $opts[CURLOPT_CAINFO] = $ca;
        }
        curl_setopt_array($ch, $opts);

        $body   = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        if ($body === false || $status !== 200) {
            // Serve stale cache rather than failing hard.
            if (is_file($file)) {
                return json_decode((string) file_get_contents($file), true) ?: [];
            }
            throw new RuntimeException($err ?: "Weather service returned HTTP $status");
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new RuntimeException('Invalid response from weather service');
        }
        @file_put_contents($file, $body, LOCK_EX);
        return $data;
    }

    /** WMO weather code → [label, emoji] */
    public static function describe(int $code, bool $isDay = true): array
    {
        return match (true) {
            $code === 0              => ['Clear sky', $isDay ? '☀️' : '🌙'],
            in_array($code, [1, 2])  => ['Partly cloudy', $isDay ? '🌤️' : '☁️'],
            $code === 3              => ['Overcast', '☁️'],
            in_array($code, [45, 48])=> ['Fog', '🌫️'],
            $code >= 51 && $code <= 57 => ['Drizzle', '🌦️'],
            $code >= 61 && $code <= 67 => ['Rain', '🌧️'],
            $code >= 71 && $code <= 77 => ['Snow', '🌨️'],
            $code >= 80 && $code <= 82 => ['Rain showers', '🌦️'],
            in_array($code, [85, 86])=> ['Snow showers', '🌨️'],
            $code >= 95              => ['Thunderstorm', '⛈️'],
            default                  => ['Unknown', '🌡️'],
        };
    }
}
