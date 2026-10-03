<?php
// Loads KEY=VALUE pairs from .env (not committed) into the environment.
function corecraft_load_env($path)
{
    if (!is_readable($path)) {
        return;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && substr($value, -1) === $value[0]) {
            $value = substr($value, 1, -1);
        }
        if (getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

function corecraft_env($key, $default = '')
{
    $value = getenv($key);
    return $value === false ? $default : $value;
}

corecraft_load_env(__DIR__ . '/.env');
corecraft_load_env(__DIR__ . '/corecraft.env');

define('CORECRAFT_DB_HOST', corecraft_env('CORECRAFT_DB_HOST'));
define('CORECRAFT_DB_USER', corecraft_env('CORECRAFT_DB_USER'));
define('CORECRAFT_DB_PASSWORD', corecraft_env('CORECRAFT_DB_PASSWORD'));
define('CORECRAFT_DB_NAME', corecraft_env('CORECRAFT_DB_NAME'));

define('CORECRAFT_GEMINI_API_KEY', corecraft_env('CORECRAFT_GEMINI_API_KEY'));
define('CORECRAFT_GEMINI_MODELS', corecraft_env('CORECRAFT_GEMINI_MODELS', 'gemini-3.6-flash,gemini-3.6-flash-lite,gemini-3.6-flash'));
define('CORECRAFT_GEMINI_PERSONA', corecraft_env('CORECRAFT_GEMINI_PERSONA', 'You are CoreCraft AI, a practical Filipino PC-building assistant. Be clear, friendly, evidence-based, and concise. Use Philippine pesos when discussing prices. Explain uncertainty instead of inventing live stock, prices, or store information.'));
define('CORECRAFT_GEMINI_LANGUAGE', corecraft_env('CORECRAFT_GEMINI_LANGUAGE', 'Answer in the same language as the user. Use Filipino or Taglish when the user writes in Filipino or Taglish, otherwise use clear English.'));
define('CORECRAFT_GOOGLE_CLIENT_ID', corecraft_env('CORECRAFT_GOOGLE_CLIENT_ID'));
define('CORECRAFT_GOOGLE_MAPS_BROWSER_KEY', corecraft_env('CORECRAFT_GOOGLE_MAPS_BROWSER_KEY', 'PASTE_YOUR_GOOGLE_MAPS_BROWSER_KEY_HERE'));

