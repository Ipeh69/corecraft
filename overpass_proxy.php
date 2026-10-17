<?php
// Server-side OpenStreetMap (Overpass) lookup with mirror failover and disk cache,
// so the store map keeps working when public mirrors are slow or reject browser requests.
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function overpassFail($status, $message) {
    header('HTTP/1.1 ' . $status);
    echo json_encode(array('error' => $message));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    overpassFail(405, 'Only POST requests are allowed.');
}

$payload = json_decode(file_get_contents('php://input'), true);
$query = is_array($payload) && isset($payload['query']) ? trim((string)$payload['query']) : '';
// Only allow the shop-listing queries this site generates, so this is not an open proxy.
if ($query === '' || strlen($query) > 2500 || strpos($query, '[out:json]') !== 0 || strpos($query, '"shop"') === false) {
    overpassFail(422, 'Invalid query.');
}

$cacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'corecraft_overpass';
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0777, true);
$cacheFile = is_dir($cacheDir) && is_writable($cacheDir) ? $cacheDir . DIRECTORY_SEPARATOR . md5($query) . '.json' : '';

$freshSeconds = 21600;
$cached = null;
$cachedAge = null;
if ($cacheFile !== '' && is_file($cacheFile)) {
    $decoded = json_decode(file_get_contents($cacheFile), true);
    if (is_array($decoded) && isset($decoded['elements']) && is_array($decoded['elements'])) {
        $cached = $decoded;
        $cachedAge = time() - filemtime($cacheFile);
    }
}
if ($cached !== null && $cachedAge < $freshSeconds) {
    echo json_encode(array('elements' => $cached['elements'], 'source' => 'cache'));
    exit;
}

$mirrors = array(
    'https://overpass-api.de/api/interpreter',
    'https://overpass.private.coffee/api/interpreter',
    'https://overpass.kumi.systems/api/interpreter',
    'https://lz4.overpass-api.de/api/interpreter',
    'https://z.overpass-api.de/api/interpreter',
    'https://overpass.openstreetmap.fr/api/interpreter'
);

// Old bundled PHP/OpenSSL (e.g. WAMP with PHP 5.3) cannot negotiate modern TLS; use the system curl.exe, which still verifies certificates.
function overpassPostWithCurlExe($url, $form) {
    $exe = getenv('SystemRoot') ? getenv('SystemRoot') . '\\System32\\curl.exe' : '';
    if ($exe === '' || !is_file($exe) || !function_exists('exec')) return null;
    $bodyFile = tempnam(sys_get_temp_dir(), 'ovb');
    $configFile = tempnam(sys_get_temp_dir(), 'ovc');
    if ($bodyFile === false || $configFile === false) return null;
    file_put_contents($bodyFile, $form);
    file_put_contents($configFile, 'url = "' . $url . "\"\nheader = \"Accept: application/json\"\nuser-agent = \"CoreCraft/1.0 (PC parts store finder; capstone project)\"\n");
    $output = array();
    $code = 1;
    exec('"' . $exe . '" -sS -m 14 -K "' . $configFile . '" --data-binary "@' . $bodyFile . '" -w "\n%{http_code}" 2>&1', $output, $code);
    @unlink($bodyFile);
    @unlink($configFile);
    $text = implode("\n", $output);
    $pos = strrpos($text, "\n");
    $status = $pos === false ? 0 : (int)substr($text, $pos + 1);
    if ($status === 0) return null;
    return array('body' => substr($text, 0, $pos), 'status' => $status);
}

$elements = null;
foreach ($mirrors as $endpoint) {
    $form = 'data=' . rawurlencode($query);
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, array(
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $form,
        CURLOPT_HTTPHEADER => array('Accept: application/json'),
        CURLOPT_USERAGENT => 'CoreCraft/1.0 (PC parts store finder; capstone project)',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_TIMEOUT => 14,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ));
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    if ($body === false && preg_match('/ssl|certificate|protocol/i', $curlError)) {
        $fallback = overpassPostWithCurlExe($endpoint, $form);
        if ($fallback !== null) {
            $body = $fallback['body'];
            $status = $fallback['status'];
        }
    }
    if ($body === false || $status !== 200) continue;
    $data = json_decode($body, true);
    if (is_array($data) && isset($data['elements']) && is_array($data['elements']) && !isset($data['remark'])) {
        $elements = $data['elements'];
        break;
    }
}

if ($elements !== null) {
    if ($cacheFile !== '') @file_put_contents($cacheFile, json_encode(array('elements' => $elements)));
    echo json_encode(array('elements' => $elements, 'source' => 'live'));
    exit;
}

// Every mirror failed: serve stale results rather than an empty map.
if ($cached !== null) {
    echo json_encode(array('elements' => $cached['elements'], 'source' => 'stale-cache'));
    exit;
}

overpassFail(502, 'OpenStreetMap servers are busy. Please try again in a moment.');
