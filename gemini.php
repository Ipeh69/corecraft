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
    echo json_encode(array('error' => 'Gemini is not configured. Upload gemini-config.php one directory above htdocs, or set CORECRAFT_GEMINI_API_KEY on the server, then try again.'));
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
    'pricing' => 'Help the user compare PC component prices and stock information in the Philippines. Never invent live prices, inventory counts, or store availability. Treat Google Places as location information only, not proof that a shop carries a part. When the user supplies a seller listing, message, or URL, summarize only what it supports, label the result VERIFIED FROM PROVIDED SOURCE or UNVERIFIED, and recommend contacting the seller because stock changes quickly. Use Philippine pesos when discussing costs.',
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

$configuredModels = defined('CORECRAFT_GEMINI_MODELS') ? CORECRAFT_GEMINI_MODELS : 'gemini-2.5-flash,gemini-2.5-flash-lite,gemini-2.0-flash';
$models = array();
foreach (explode(',', $configuredModels) as $model) {
    $model = trim($model);
    if ($model !== '') $models[] = $model;
}
if (count($models) === 0) $models = array('gemini-2.5-flash');

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
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0
    ));
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
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
    echo json_encode(array('error' => 'All configured Gemini models failed: ' . $lastError));
    exit;
}

$text = '';
if (isset($gemini['candidates'][0]['content']['parts']) && is_array($gemini['candidates'][0]['content']['parts'])) {
    foreach ($gemini['candidates'][0]['content']['parts'] as $part) {
        if (isset($part['text'])) $text .= $part['text'];
    }
}
if ($text === '') {
    setHttpStatus(502);
    echo json_encode(array('error' => 'Gemini returned an empty response.'));
    exit;
}

echo json_encode(array('reply' => $text));
