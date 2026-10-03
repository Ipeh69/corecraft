<?php
// CoreCraft server configuration.
// Edit this file before uploading it to InfinityFree htdocs.

// InfinityFree: Control Panel > MySQL Databases.
define('CORECRAFT_DB_HOST', 'YOUR_INFINITYFREE_MYSQL_HOST');
define('CORECRAFT_DB_USER', 'YOUR_INFINITYFREE_DB_USERNAME');
define('CORECRAFT_DB_PASSWORD', 'YOUR_INFINITYFREE_DB_PASSWORD');
define('CORECRAFT_DB_NAME', 'YOUR_INFINITYFREE_DB_NAME');

// Gemini: https://aistudio.google.com/app/apikey
// Keep this key on the server. Do not put it in script.js.
define('CORECRAFT_GEMINI_API_KEY', 'PASTE_YOUR_GEMINI_API_KEY_HERE');
// Comma-separated fallback order. Use models enabled for your Google AI project.
define('CORECRAFT_GEMINI_MODELS', 'gemini-2.5-flash,gemini-2.5-flash-lite,gemini-2.0-flash');

// Customize Gemini's voice and behavior without editing gemini.php.
define('CORECRAFT_GEMINI_PERSONA', 'You are CoreCraft AI, a practical Filipino PC-building assistant. Be clear, friendly, evidence-based, and concise. Use Philippine pesos when discussing prices. Explain uncertainty instead of inventing live stock, prices, or store information.');
define('CORECRAFT_GEMINI_LANGUAGE', 'Answer in the same language as the user. Use Filipino or Taglish when the user writes in Filipino or Taglish, otherwise use clear English.');

// Google Sign-In Web Client ID.
define('CORECRAFT_GOOGLE_CLIENT_ID', 'PASTE_YOUR_GOOGLE_CLIENT_ID_HERE');

