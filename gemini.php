<?php
// CoreCraft Gemini endpoint. Keep the API key on the server, never in script.js.
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
require_once __DIR__ . '/config.php';

// Keep the key outside the public web directory. Production can also provide it
// through the CORECRAFT_GEMINI_API_KEY server environment variable.
$configuredGeminiKey = '';
$environmentGeminiKey = getenv('CORECRAFT_GEMINI_API_KEY');
if ($environmentGeminiKey !== false && trim((string) $environmentGeminiKey) !== '') {
    $configuredGeminiKey = trim((string) $environmentGeminiKey);
}

$privateConfigCandidates = array(
    dirname(__DIR__) . DIRECTORY_SEPARATOR . 'gemini-config.php',
    dirname(__DIR__) . DIRECTORY_SEPARATOR . 'gemini_-config.php'
);
foreach ($privateConfigCandidates as $privateConfig) {
    if ($configuredGeminiKey === '' && is_file($privateConfig)) {
        $GEMINI_API_KEY = '';
        include $privateConfig;
        if (isset($GEMINI_API_KEY) && trim((string) $GEMINI_API_KEY) !== '') {
            $configuredGeminiKey = trim((string) $GEMINI_API_KEY);
        }
    }
}

// Keep compatibility with deployments that store the key in config.php.
if ($configuredGeminiKey === '' && defined('CORECRAFT_GEMINI_API_KEY')) {
    $configuredGeminiKey = trim((string) CORECRAFT_GEMINI_API_KEY);
}

function setHttpStatus($statusCode) {
    header('HTTP/1.1 ' . $statusCode);
}

$apiKey = $configuredGeminiKey;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setHttpStatus(405);
    echo json_encode(array('error' => 'Only POST requests are allowed.'));
    exit;
}

if ($apiKey === '' || strpos($apiKey, 'YOUR_') === 0 || strpos($apiKey, 'PASTE_') === 0) {
    setHttpStatus(503);
    echo json_encode(array('error' => 'Gemini API key is not configured. Add CORECRAFT_GEMINI_API_KEY to the project .env file or set it in the server environment, then try again.'));
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) $payload = array();
$message = trim((string)(isset($payload['message']) ? $payload['message'] : ''));
$feature = strtolower(trim((string)(isset($payload['feature']) ? $payload['feature'] : 'general')));
$context = trim((string)(isset($payload['context']) ? $payload['context'] : ''));

if ($message === '') {
    setHttpStatus(422);
    echo json_encode(array('error' => 'Message is required.'));
    exit;
}

if (strlen($message) > 12000) {
    setHttpStatus(422);
    echo json_encode(array('error' => 'Message is too long.'));
    exit;
}

$featurePrompts = array(
    'pricing' => 'Help the user compare PC component prices and stock information in the Philippines. Never invent live prices, inventory counts, or store availability. Treat map listings as location information only, not proof that a shop carries a part. For a live-search request, use only current web-search results and retailer pages as evidence. Name a specific store only when a source supports it, link each result to its source, and distinguish a posted online price from a confirmed in-store price. Say STOCK NOT VERIFIED when a source does not explicitly establish current availability, and say NO CURRENT LISTING FOUND when search produces no reliable product listing. Include the current date, exact product/model, Philippine peso price, source publication/update date when available, and direct retailer URL when available. Never infer stock from a search snippet, map marker, generic store page, or old/marketplace listing. Recommend confirming stock and final price directly with the seller. Use Philippine pesos.',
    'trouble' => 'Act as a careful PC troubleshooting assistant. Give safe, numbered diagnostic steps. Warn the user to unplug power before opening the case and never recommend unsafe electrical experiments. Ask for missing details when needed.',
    'compat' => 'You are the authoritative PC compatibility checker. Use the exact user-entered part names and your hardware knowledge, never database assumptions. Give a complete, detailed answer and do not stop after identifying the first issue. Use this exact order: VERDICT: Compatible or VERDICT: Needs Changes; IMMEDIATE CHANGES: all important actions the user must take; WHY: detailed explanation of each issue; OPTIONS: replacement choices; FINAL RECOMMENDATION: one complete working direction; ESTIMATED PHILIPPINES PRICES: every entered part in the format PART | LOW ESTIMATE | HIGH ESTIMATE, followed by TOTAL ESTIMATED BUILD COST. Use realistic current-market ranges in Philippine pesos based on the exact model, never claim an exact live seller price, and label all amounts as estimates that vary by seller and date. Name the actual parts and label each issue CRITICAL, WARNING, or OK. For CPU/motherboard mismatch, provide two complete paths: keep the CPU and name a compatible motherboard, or keep the motherboard and name a compatible CPU. For a weak PSU, state minimum wattage, recommended wattage, quality tier, and connector requirement. Separate true incompatibility from performance bottleneck. If a model is incomplete, mark it UNVERIFIED and state the exact detail needed. Always include IMMEDIATE CHANGES, FINAL RECOMMENDATION, and ESTIMATED PHILIPPINES PRICES before ending.',
    'buildai' => 'Recommend a balanced PC build for the user\'s budget and purpose in the Philippines. Use current component knowledge cautiously, provide approximate prices only when appropriate, and explain trade-offs. Do not claim that prices are live.',
    'phchat' => 'Be a friendly Filipino PC-building community assistant. Give practical and respectful advice about PC parts, builds, troubleshooting, and buying in the Philippines. Avoid pretending to be a real person or claiming unverified store information.',
    'general' => 'Be a helpful PC-building assistant for CoreCraft users in the Philippines. Give concise, accurate, practical answers and ask a clarifying question when important information is missing.'
);

$selectedPrompt = isset($featurePrompts[$feature]) ? $featurePrompts[$feature] : $featurePrompts['general'];
$persona = defined('CORECRAFT_GEMINI_PERSONA') ? trim(CORECRAFT_GEMINI_PERSONA) : 'You are CoreCraft AI, a helpful PC-building assistant.';
$language = defined('CORECRAFT_GEMINI_LANGUAGE') ? trim(CORECRAFT_GEMINI_LANGUAGE) : 'Answer clearly in the language used by the user.';
$systemPrompt = $persona . "\n" . $language . "\n\n" . $selectedPrompt . "\n\nFormatting rules: use short paragraphs and bullet points. Do not use HTML. If the user asks for dangerous instructions, refuse that part and provide a safe alternative.";
if ($context !== '') {
    $systemPrompt .= "\n\nAdditional system context:\n" . substr($context, 0, 6000);
}

$requestBody = array(
    'system_instruction' => array(
        'parts' => array(array('text' => $systemPrompt))
    ),
    'contents' => array(
        array(
            'role' => 'user',
            'parts' => array(array('text' => $message))
        )
    ),
    'generationConfig' => array(
        'temperature' => 0.4,
        'maxOutputTokens' => 8192
    )
);

$useGoogleSearch = $feature === 'pricing'
    && isset($payload['grounded_search'])
    && $payload['grounded_search'] === true;
$searchStartedAt = $useGoogleSearch ? gmdate('c') : null;
if ($useGoogleSearch) {
    $systemPrompt .= "\nCurrent date and time for search-result freshness: " . gmdate('Y-m-d H:i') . ' UTC.';
    $requestBody['tools'] = array(array('google_search' => new stdClass()));
}

$configuredModels = defined('CORECRAFT_GEMINI_MODELS') ? CORECRAFT_GEMINI_MODELS : 'gemini-3.5-flash-lite';
$models = array();
foreach (explode(',', $configuredModels) as $model) {
    $model = trim($model);
    if ($model !== '') $models[] = $model;
}
if (count($models) === 0) $models = array('gemini-3.5-flash-lite');

// Old bundled PHP/OpenSSL (e.g. WAMP with PHP 5.3) cannot verify modern TLS; use the system curl.exe, which still verifies certificates.
function geminiPostWithCurlExe($url, $json) {
    $exe = getenv('SystemRoot') ? getenv('SystemRoot') . '\\System32\\curl.exe' : '';
    if ($exe === '' || !is_file($exe) || !function_exists('exec')) return null;
    $bodyFile = tempnam(sys_get_temp_dir(), 'gmb');
    $configFile = tempnam(sys_get_temp_dir(), 'gmc');
    if ($bodyFile === false || $configFile === false) return null;
    file_put_contents($bodyFile, $json);
    file_put_contents($configFile, 'url = "' . $url . "\"\nheader = \"Content-Type: application/json\"\n");
    $output = array();
    $code = 1;
    exec('"' . $exe . '" -sS -m 90 -K "' . $configFile . '" --data-binary "@' . $bodyFile . '" -w "\n%{http_code}" 2>&1', $output, $code);
    @unlink($bodyFile);
    @unlink($configFile);
    $text = implode("\n", $output);
    $pos = strrpos($text, "\n");
    $status = $pos === false ? 0 : (int)substr($text, $pos + 1);
    if ($status === 0) return null;
    return array('body' => substr($text, 0, $pos), 'status' => $status);
}

$response = false;
$curlError = '';
$statusCode = 0;
$gemini = array();
$lastError = 'Gemini returned an error.';
foreach ($models as $model) {
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($apiKey);
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
        CURLOPT_POSTFIELDS => json_encode($requestBody),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT => 90,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ));
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($response === false && preg_match('/ssl|certificate|protocol/i', $curlError)) {
        $fallback = geminiPostWithCurlExe($url, json_encode($requestBody));
        if ($fallback !== null) {
            $response = $fallback['body'];
            $statusCode = $fallback['status'];
            $curlError = '';
        }
    }
    $gemini = is_string($response) ? json_decode($response, true) : array();
    if ($response !== false && !$curlError && $statusCode >= 200 && $statusCode < 300) break;
    $lastError = isset($gemini['error']['message']) ? $gemini['error']['message'] : ($curlError ? $curlError : 'Model unavailable.');
}

if ($response === false || $curlError) {
    setHttpStatus(502);
    echo json_encode(array('error' => 'Unable to connect to Gemini: ' . ($curlError ? $curlError : $lastError)));
    exit;
}

if ($statusCode < 200 || $statusCode >= 300) {
    setHttpStatus($statusCode === 429 ? 429 : 502);
    $quotaHint = ($useGoogleSearch && $statusCode === 429)
        ? ' Live web search (Google Search grounding) needs a Gemini API key on a paid/billing-enabled plan; the free tier has no grounding quota.'
        : '';
    echo json_encode(array('error' => 'All configured Gemini models failed: ' . $lastError . $quotaHint));
    exit;
}

$text = '';
$sources = array();
$groundingMetadata = isset($gemini['candidates'][0]['groundingMetadata'])
    && is_array($gemini['candidates'][0]['groundingMetadata'])
    ? $gemini['candidates'][0]['groundingMetadata']
    : array();
if (isset($gemini['candidates'][0]['content']['parts']) && is_array($gemini['candidates'][0]['content']['parts'])) {
    foreach ($gemini['candidates'][0]['content']['parts'] as $part) {
        if (isset($part['text'])) $text .= $part['text'];
    }
}
if (isset($groundingMetadata['groundingChunks']) && is_array($groundingMetadata['groundingChunks'])) {
    foreach ($groundingMetadata['groundingChunks'] as $chunk) {
        $webSource = isset($chunk['web']) && is_array($chunk['web']) ? $chunk['web'] : array();
        $uri = isset($webSource['uri']) ? trim((string)$webSource['uri']) : '';
        if ($uri === '' || !filter_var($uri, FILTER_VALIDATE_URL) || strtolower((string)parse_url($uri, PHP_URL_SCHEME)) !== 'https') {
            continue;
        }
        $sources[$uri] = array(
            'title' => isset($webSource['title']) ? trim((string)$webSource['title']) : '',
            'url' => $uri
        );
        if (count($sources) >= 10) break;
    }
}
if ($text === '') {
    setHttpStatus(502);
    echo json_encode(array('error' => 'Gemini returned an empty response.'));
    exit;
}

echo json_encode(array(
    'reply' => $text,
    'grounded' => $useGoogleSearch && count($sources) > 0,
    'sources' => array_values($sources),
    'searchedAt' => $searchStartedAt
));
