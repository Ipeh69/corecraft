<?php
require_once __DIR__ . "/config.php";

// Keep the public page available when the deployment still has template values.
// Database-dependent requests return a clear 503 from their own endpoint.
$conn = null;
$hasDatabaseConfig = CORECRAFT_DB_HOST !== ''
    && strpos(CORECRAFT_DB_HOST, 'YOUR_') !== 0
    && CORECRAFT_DB_USER !== ''
    && strpos(CORECRAFT_DB_USER, 'YOUR_') !== 0
    && CORECRAFT_DB_NAME !== ''
    && strpos(CORECRAFT_DB_NAME, 'YOUR_') !== 0;

if ($hasDatabaseConfig) {
    $candidate = @new mysqli(CORECRAFT_DB_HOST, CORECRAFT_DB_USER, CORECRAFT_DB_PASSWORD, CORECRAFT_DB_NAME);
    if (!$candidate->connect_error) {
        $conn = $candidate;
    }
}
?>