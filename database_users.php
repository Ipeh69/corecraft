<?php
require_once __DIR__ . "/config.php";

$conn_users = new mysqli(CORECRAFT_DB_HOST, CORECRAFT_DB_USER, CORECRAFT_DB_PASSWORD, CORECRAFT_DB_NAME);
if ($conn_users->connect_error) {
    header("Content-Type: application/json; charset=UTF-8");
    header("HTTP/1.1 503 Service Unavailable");
    echo json_encode(array(
        "success" => false,
        "error" => "The account database is unavailable. Check the database settings (error code " . (int) $conn_users->connect_errno . ")."
    ));
    exit;
}
?>

