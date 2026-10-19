<?php
// Checks public search results (price + online availability) on Philippine PC retailer websites.
// These stores run on Shopify, which exposes a public JSON search endpoint.
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
@set_time_limit(120);
@ini_set('display_errors', '0');

function stockFail($status, $message) {
    header('HTTP/1.1 ' . $status);
    echo json_encode(array('error' => $message));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    stockFail(405, 'Only POST requests are allowed.');
}

$payload = json_decode(file_get_contents('php://input'), true);
$part = is_array($payload) && isset($payload['part']) ? trim((string)$payload['part']) : '';
if ($part === '' || strlen($part) > 120) {
    stockFail(422, 'Enter a part name or model.');
}

$stores = array(
    array('name' => 'PC Express', 'host' => 'pcx.com.ph'),
    array('name' => 'EasyPC', 'host' => 'easypc.com.ph'),
    array('name' => 'DynaQuest PC', 'host' => 'dynaquestpc.com'),
    array('name' => 'PCWorx', 'host' => 'pcworx.ph'),
    array('name' => 'Gigahertz', 'host' => 'www.gigahertz.com.ph')
);

// Keep only alphanumeric characters so "RTX 4060" matches "RTX4060" in a product title.
function stockNormalize($text) {
    return preg_replace('/[^a-z0-9]+/', '', strtolower($text));
}

$ignored = array('graphics', 'card', 'video', 'videocard', 'the', 'for', 'and', 'with');
$tokens = array();
foreach (preg_split('/[\s,]+/', strtolower($part)) as $word) {
    $word = stockNormalize($word);
    if ($word !== '' && !in_array($word, $ignored, true)) $tokens[] = $word;
}
if (count($tokens) === 0) {
    stockFail(422, 'Enter a part name or model.');
}

$cacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'corecraft_stock';
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0777, true);
$cacheFile = is_dir($cacheDir) && is_writable($cacheDir) ? $cacheDir . DIRECTORY_SEPARATOR . md5('v7 ' . implode(' ', $tokens)) . '.json' : '';
if ($cacheFile !== '' && is_file($cacheFile) && time() - filemtime($cacheFile) < 600) {
    $cached = json_decode(file_get_contents($cacheFile), true);
    if (is_array($cached)) {
        $cached['cached'] = true;
        echo json_encode($cached);
        exit;
    }
}

// Old bundled PHP/OpenSSL (e.g. WAMP with PHP 5.3) cannot negotiate modern TLS; use the system curl.exe, which still verifies certificates.
function stockGetWithCurlExe($url) {
    $exe = getenv('SystemRoot') ? getenv('SystemRoot') . '\\System32\\curl.exe' : '';
    if ($exe === '' || !is_file($exe) || !function_exists('exec')) return null;
    $configFile = tempnam(sys_get_temp_dir(), 'skc');
    if ($configFile === false) return null;
    file_put_contents($configFile, 'url = "' . $url . "\"\ngloboff\nlocation\nuser-agent = \"CoreCraft/1.0 (PC parts price finder; capstone project)\"\n");
    $output = array();
    $code = 1;
    exec('"' . $exe . '" -sS -m 20 -K "' . $configFile . '" -w "\n%{http_code}" 2>&1', $output, $code);
    @unlink($configFile);
    $text = implode("\n", $output);
    $pos = strrpos($text, "\n");
    $status = $pos === false ? 0 : (int)substr($text, $pos + 1);
    if ($status === 0) return null;
    return array('body' => substr($text, 0, $pos), 'status' => $status);
}

function stockGet($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'CoreCraft/1.0 (PC parts price finder; capstone project)',
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ));
    $body = @curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($body === false && preg_match('/ssl|certificate|protocol/i', $error)) {
        $fallback = stockGetWithCurlExe($url);
        if ($fallback !== null) return $fallback;
    }
    return array('body' => $body, 'status' => $status);
}

$results = array();
$skippedLaptops = 0;
$wantsLaptop = (bool)preg_match('/laptop|notebook|desktop|pc\b/i', $part);
$checked = array();
$failed = array();
// Search with the most specific tokens (model numbers) first, then filter by every token.
$searchTerm = implode(' ', $tokens);
foreach ($stores as $store) {
    $url = 'https://' . $store['host'] . '/search/suggest.json?q=' . rawurlencode($searchTerm) . '&resources[type]=product&resources[limit]=10';
    $response = stockGet($url);
    $data = is_string($response['body']) ? json_decode($response['body'], true) : null;
    if ($response['status'] !== 200 || !is_array($data) || !isset($data['resources']['results']['products'])) {
        $failed[] = $store['name'];
        continue;
    }
    $checked[] = $store['name'];
    foreach ($data['resources']['results']['products'] as $product) {
        $title = isset($product['title']) ? trim((string)$product['title']) : '';
        // Some stores double-encode ®/™; drop non-ASCII symbols so titles read cleanly.
        $title = trim(preg_replace('/\s+/', ' ', preg_replace('/[^\x20-\x7E]+/', ' ', $title)));
        $haystack = ' ' . trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower($title))) . ' ';
        $matches = $title !== '';
        foreach ($tokens as $token) {
            // Whole-word match; a model number must not continue into a variant (4060 vs 4060 Ti / 5600X).
            $pattern = '/(?<![a-z0-9])' . preg_quote($token, '/') . '(?![a-z0-9])/';
            if (!preg_match($pattern, $haystack)) { $matches = false; break; }
            if (preg_match('/\d$/', $token) && preg_match('/(?<![a-z0-9])' . preg_quote($token, '/') . '\s+(ti|super|xt|xtx|gre|x3d|x|g|f|k|kf|ks)(?![a-z0-9])/', $haystack)
                && !preg_match('/(?<![a-z0-9])(ti|super|xt|xtx|gre|x3d)(?![a-z0-9])/', implode(' ', $tokens))) { $matches = false; break; }
        }
        if (!$matches) continue;
        $productType = isset($product['type']) ? (string)$product['type'] : '';
        $isLaptop = preg_match('/laptop|notebook|desktop|all.?in.?one|gaming pc|win ?1[01]\b/i', $title . ' ' . $productType)
            || preg_match('/\b1[3-7](\.\d)?\s?(in|inch|")|\b1[3-7]\.\d/i', $title);
        if (!$wantsLaptop && $isLaptop) { $skippedLaptops++; continue; }
        $path = isset($product['url']) ? (string)$product['url'] : '';
        $queryPos = strpos($path, '?');
        if ($queryPos !== false) $path = substr($path, 0, $queryPos);
        if ($path === '' || $path[0] !== '/') continue;
        $results[] = array(
            'store' => $store['name'],
            'title' => $title,
            'price' => isset($product['price']) ? (float)$product['price'] : 0,
            'available' => !empty($product['available']),
            'url' => 'https://' . $store['host'] . $path
        );
    }
}

usort($results, 'stockCompare');
function stockCompare($a, $b) {
    if ($a['available'] !== $b['available']) return $a['available'] ? -1 : 1;
    if ($a['price'] === $b['price']) return 0;
    return $a['price'] < $b['price'] ? -1 : 1;
}

$output = array(
    'part' => $part,
    'results' => array_slice($results, 0, 20),
    'checked' => $checked,
    'failed' => $failed,
    'skippedLaptops' => $skippedLaptops,
    'checkedAt' => gmdate('c'),
    'cached' => false
);
if ($cacheFile !== '' && count($checked) > 0) @file_put_contents($cacheFile, json_encode($output));
echo json_encode($output);
