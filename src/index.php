<?php
declare(strict_types=1);

require __DIR__ . '/WeatherService.php';

header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'");

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$city = trim((string) ($_GET['city'] ?? 'Paris'));
$city = mb_substr($city === '' ? 'Paris' : $city, 0, 80);
$unit = ($_GET['unit'] ?? 'celsius') === 'fahrenheit' ? 'fahrenheit' : 'celsius';
$sym  = $unit === 'fahrenheit' ? '°F' : '°C';

$svc   = new WeatherService(sys_get_temp_dir() . '/weather-cache', (int) (getenv('CACHE_TTL') ?: 600));
$error = null;
$place = null;
$wx    = null;

try {
    $place = $svc->geocode($city);
    if (!$place) {
        $error = "City “{$city}” not found.";
    } else {
        $wx = $svc->forecast($place['latitude'], $place['longitude'], $unit);
    }
} catch (Throwable $t) {
    error_log('weather: ' . $t->getMessage());
    http_response_code(502);
    $error = 'Weather service is unreachable right now. Please try again shortly.';
}

if (($_GET['format'] ?? '') === 'json') {
    header('Content-Type: application/json');
    echo json_encode($error ? ['error' => $error] : ['place' => $place, 'weather' => $wx]);
    exit;
}

$cur = $wx['current'] ?? null;
[$label, $icon] = $cur ? WeatherService::describe((int) $cur['weather_code'], (bool) $cur['is_day']) : ['', ''];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Weather · <?= e($place['name'] ?? $city) ?></title>
<style>
  :root { --bg:#f4f6fb; --card:#fff; --fg:#0f172a; --muted:#64748b; --accent:#2563eb; --line:#e2e8f0; }
  @media (prefers-color-scheme: dark) { :root { --bg:#0b1120; --card:#131c2e; --fg:#e2e8f0; --muted:#94a3b8; --accent:#60a5fa; --line:#1e293b; } }
  * { box-sizing:border-box; }
  body { margin:0; font:16px/1.5 system-ui,-apple-system,Segoe UI,Roboto,sans-serif; background:var(--bg); color:var(--fg); }
  main { max-width:860px; margin:0 auto; padding:24px 16px 48px; }
  form { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:20px; }
  input, select, button { font:inherit; padding:10px 12px; border-radius:10px; border:1px solid var(--line); background:var(--card); color:var(--fg); }
  input { flex:1 1 200px; }
  button { background:var(--accent); color:#fff; border:0; cursor:pointer; font-weight:600; }
  .card { background:var(--card); border:1px solid var(--line); border-radius:16px; padding:20px; margin-bottom:16px; }
  .hero { display:flex; align-items:center; gap:20px; flex-wrap:wrap; }
  .hero .icon { font-size:72px; line-height:1; }
  .hero .temp { font-size:56px; font-weight:700; }
  .muted { color:var(--muted); }
  .stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:12px; margin-top:16px; }
  .stat { border:1px solid var(--line); border-radius:12px; padding:10px 12px; }
  .stat b { display:block; font-size:20px; }
  .hours { display:flex; gap:10px; overflow-x:auto; padding-bottom:6px; }
  .hour { flex:0 0 64px; text-align:center; border:1px solid var(--line); border-radius:10px; padding:8px 4px; font-size:14px; }
  .days { display:grid; gap:8px; }
  .day { display:grid; grid-template-columns:90px 40px 1fr auto; align-items:center; gap:10px; padding:8px 0; border-bottom:1px solid var(--line); }
  .day:last-child { border-bottom:0; }
  .err { border-color:#f87171; color:#b91c1c; }
  h2 { margin:0 0 12px; font-size:18px; }
  footer { font-size:13px; text-align:center; }
</style>
</head>
<body>
<main>
  <form method="get">
    <input name="city" value="<?= e($city) ?>" placeholder="Search a city…" aria-label="City" required>
    <select name="unit" aria-label="Unit">
      <option value="celsius" <?= $unit === 'celsius' ? 'selected' : '' ?>>°C</option>
      <option value="fahrenheit" <?= $unit === 'fahrenheit' ? 'selected' : '' ?>>°F</option>
    </select>
    <button type="submit">Search</button>
  </form>

<?php if ($error): ?>
  <div class="card err"><?= e($error) ?></div>
<?php else: ?>
  <section class="card">
    <div class="hero">
      <div class="icon"><?= $icon ?></div>
      <div>
        <div class="muted"><?= e($place['name']) ?><?= $place['country'] ? ', ' . e($place['country']) : '' ?></div>
        <div class="temp"><?= round((float) $cur['temperature_2m']) . $sym ?></div>
        <div><?= e($label) ?> · feels like <?= round((float) $cur['apparent_temperature']) . $sym ?></div>
      </div>
    </div>
    <div class="stats">
      <div class="stat"><span class="muted">Humidity</span><b><?= (int) $cur['relative_humidity_2m'] ?>%</b></div>
      <div class="stat"><span class="muted">Wind</span><b><?= round((float) $cur['wind_speed_10m']) ?> km/h</b></div>
      <div class="stat"><span class="muted">Sunrise</span><b><?= e(substr((string) $wx['daily']['sunrise'][0], 11, 5)) ?></b></div>
      <div class="stat"><span class="muted">Sunset</span><b><?= e(substr((string) $wx['daily']['sunset'][0], 11, 5)) ?></b></div>
    </div>
  </section>

  <section class="card">
    <h2>Next 24 hours</h2>
    <div class="hours">
      <?php foreach ($wx['hourly']['time'] as $i => $t): ?>
        <div class="hour">
          <div class="muted"><?= e(substr((string) $t, 11, 5)) ?></div>
          <b><?= round((float) $wx['hourly']['temperature_2m'][$i]) ?>°</b>
          <div class="muted">💧<?= (int) ($wx['hourly']['precipitation_probability'][$i] ?? 0) ?>%</div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="card">
    <h2>7-day forecast</h2>
    <div class="days">
      <?php foreach ($wx['daily']['time'] as $i => $d):
        [$dl, $di] = WeatherService::describe((int) $wx['daily']['weather_code'][$i]); ?>
        <div class="day">
          <span><?= $i === 0 ? 'Today' : e(date('D j', strtotime((string) $d))) ?></span>
          <span title="<?= e($dl) ?>"><?= $di ?></span>
          <span class="muted">💧<?= (int) ($wx['daily']['precipitation_probability_max'][$i] ?? 0) ?>%</span>
          <span><b><?= round((float) $wx['daily']['temperature_2m_max'][$i]) ?>°</b> / <span class="muted"><?= round((float) $wx['daily']['temperature_2m_min'][$i]) ?>°</span></span>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

  <footer class="muted">Data: <a href="https://open-meteo.com/" rel="noopener">Open-Meteo</a> · JSON: <a href="?city=<?= urlencode($city) ?>&unit=<?= $unit ?>&format=json">/?format=json</a></footer>
</main>
</body>
</html>
