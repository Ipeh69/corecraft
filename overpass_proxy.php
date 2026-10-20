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
    exec('"' . $exe . '" -sS -m 8 -K "' . $configFile . '" --data-binary "@' . $bodyFile . '" -w "\n%{http_code}" 2>&1', $output, $code);
    @unlink($bodyFile);
    @unlink($configFile);
    $text = implode("\n", $output);
    $pos = strrpos($text, "\n");
    $status = $pos === false ? 0 : (int)substr($text, $pos + 1);
    if ($status === 0) return null;
    return array('body' => substr($text, 0, $pos), 'status' => $status);
}

$elements = null;
$form = 'data=' . rawurlencode($query);
$userAgent = 'CoreCraft/1.0 (PC parts store finder; capstone project)';

// Query every mirror at the same time and use the first valid answer.
$multi = curl_multi_init();
$handles = array();
foreach ($mirrors as $endpoint) {
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, array(
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $form,
        CURLOPT_HTTPHEADER => array('Accept: application/json'),
        CURLOPT_USERAGENT => $userAgent,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ));
    curl_multi_add_handle($multi, $ch);
    $handles[] = array('ch' => $ch, 'endpoint' => $endpoint);
}
$running = 0;
$done = array();
$sslFailed = array();
do {
    curl_multi_exec($multi, $running);
    while ($info = curl_multi_info_read($multi)) {
        $id = (int)$info['handle'];
        if (isset($done[$id])) continue;
        $done[$id] = true;
        if ($info['result'] !== 0) {
            if ($info['result'] === CURLE_SSL_CONNECT_ERROR || $info['result'] === 35 || $info['result'] === 51 || $info['result'] === 58 || $info['result'] === 60) $sslFailed[$id] = true;
            continue;
        }
        $body = curl_multi_getcontent($info['handle']);
        $status = (int)curl_getinfo($info['handle'], CURLINFO_HTTP_CODE);
        if ($status !== 200 || $body === null || $body === '') continue;
        $data = json_decode($body, true);
        if (is_array($data) && isset($data['elements']) && is_array($data['elements']) && !isset($data['remark'])) {
            $elements = $data['elements'];
            $running = 0;
            break 2;
        }
    }
    if ($running > 0) curl_multi_select($multi, 0.2);
} while ($running > 0);

$sslHandles = array();
foreach ($handles as $h) {
    if ($elements === null && isset($sslFailed[(int)$h['ch']])) $sslHandles[] = $h['endpoint'];
    curl_multi_remove_handle($multi, $h['ch']);
    curl_close($h['ch']);
}
curl_multi_close($multi);

// Old bundled PHP/OpenSSL cannot reach the mirrors over TLS; retry via curl.exe.
if ($elements === null && $sslHandles) {
    foreach (array_slice($sslHandles, 0, 1) as $endpoint) {
        $fallback = overpassPostWithCurlExe($endpoint, $form);
        if ($fallback === null || $fallback['status'] !== 200) continue;
        $data = json_decode($fallback['body'], true);
        if (is_array($data) && isset($data['elements']) && is_array($data['elements']) && !isset($data['remark'])) {
            $elements = $data['elements'];
            break;
        }
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
