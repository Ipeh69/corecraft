<?php
require_once __DIR__ . "/config.php";

$conn_builds = new mysqli(CORECRAFT_DB_HOST, CORECRAFT_DB_USER, CORECRAFT_DB_PASSWORD, CORECRAFT_DB_NAME);
if ($conn_builds->connect_error) {
    die("Builds database connection failed: " . $conn_builds->connect_error);
}
?>
