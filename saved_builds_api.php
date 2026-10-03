<?php
require_once "database_builds.php";

header("Content-Type: application/json; charset=UTF-8");

function respond($payload, $status_code) {
    if (function_exists("http_response_code")) {
        http_response_code($status_code);
    } else {
        $reason = "OK";
        if ($status_code === 400) $reason = "Bad Request";
        else if ($status_code === 401) $reason = "Unauthorized";
        else if ($status_code === 404) $reason = "Not Found";
        else if ($status_code === 500) $reason = "Internal Server Error";

        if (isset($_SERVER["SERVER_PROTOCOL"])) {
            header($_SERVER["SERVER_PROTOCOL"] . " " . $status_code . " " . $reason, true, $status_code);
        } else {
            header("HTTP/1.1 " . $status_code . " " . $reason, true, $status_code);
        }
    }
    echo json_encode($payload);
    exit;
}

function is_valid_email($email) {
    return (bool) preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $email);
}

$create_table_sql = "CREATE TABLE IF NOT EXISTS user_saved_builds (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_email VARCHAR(190) NOT NULL,
    build_name VARCHAR(120) NOT NULL,
    components_json TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_build (user_email, build_name),
    KEY idx_user_updated (user_email, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8";

if (!$conn_builds->query($create_table_sql)) {
    respond(array(
        "success" => false,
        "error" => "Failed to prepare saved builds table",
        "details" => $conn_builds->error
    ), 500);
}

$raw_body = file_get_contents("php://input");
$input = array();

if ($raw_body) {
    $decoded = json_decode($raw_body, true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}

$action = isset($input["action"]) ? $input["action"] : (isset($_GET["action"]) ? $_GET["action"] : "");
$user_email = isset($input["user_email"]) ? strtolower(trim($input["user_email"])) : (isset($_GET["user_email"]) ? strtolower(trim($_GET["user_email"])) : "");
$build_name = isset($input["build_name"]) ? trim($input["build_name"]) : (isset($_GET["build_name"]) ? trim($_GET["build_name"]) : "");

if ($action === "") {
    respond(array("success" => false, "error" => "Missing action"), 400);
}

if (!is_valid_email($user_email)) {
    respond(array("success" => false, "error" => "Invalid user email"), 400);
}

if ($action === "list") {
    $stmt = $conn_builds->prepare("SELECT build_name, components_json, updated_at FROM user_saved_builds WHERE user_email = ? ORDER BY updated_at DESC");
    if (!$stmt) {
        respond(array("success" => false, "error" => "Prepare failed", "details" => $conn->error), 500);
    }

    $stmt->bind_param("s", $user_email);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($row_build_name, $row_components_json, $row_updated_at);

    $items = array();
    while ($stmt->fetch()) {
        $components = json_decode($row_components_json, true);
        if (!is_array($components)) {
            $components = array();
        }

        $items[] = array(
            "build_name" => $row_build_name,
            "components" => $components,
            "updated_at" => $row_updated_at
        );
    }

    $stmt->free_result();
    $stmt->close();

    respond(array("success" => true, "items" => $items), 200);
}

if ($action === "save") {
    if ($build_name === "") {
        respond(array("success" => false, "error" => "Build name is required"), 400);
    }

    $components = isset($input["components"]) && is_array($input["components"]) ? $input["components"] : array();
    if (count($components) === 0) {
        respond(array("success" => false, "error" => "At least one component is required"), 400);
    }

    $components_json = json_encode($components);

    $stmt = $conn_builds->prepare("INSERT INTO user_saved_builds (user_email, build_name, components_json, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE components_json = VALUES(components_json), updated_at = NOW()");
    if (!$stmt) {
        respond(array("success" => false, "error" => "Prepare failed", "details" => $conn->error), 500);
    }

    $stmt->bind_param("sss", $user_email, $build_name, $components_json);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        respond(array("success" => false, "error" => "Failed to save build", "details" => $conn_builds->error), 500);
    }

    respond(array("success" => true, "message" => "Build saved"), 200);
}

if ($action === "delete") {
    if ($build_name === "") {
        respond(array("success" => false, "error" => "Build name is required"), 400);
    }

    $stmt = $conn_builds->prepare("DELETE FROM user_saved_builds WHERE user_email = ? AND build_name = ?");
    if (!$stmt) {
        respond(array("success" => false, "error" => "Prepare failed", "details" => $conn->error), 500);
    }

    $stmt->bind_param("ss", $user_email, $build_name);
    $ok = $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if (!$ok) {
        respond(array("success" => false, "error" => "Failed to delete build", "details" => $conn_builds->error), 500);
    }

    respond(array("success" => true, "deleted" => $affected > 0), 200);
}

respond(array("success" => false, "error" => "Unsupported action"), 400);
