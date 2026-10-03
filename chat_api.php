<?php
ob_start();
require_once __DIR__ . "/database_users.php";

header("Content-Type: application/json; charset=UTF-8");

function chat_respond($payload, $status_code = 200) {
    http_response_code($status_code);
    if (ob_get_length()) {
        ob_clean();
    }
    $json = json_encode($payload);
    echo is_string($json) ? $json : '{"success":false,"error":"Invalid server response"}';
    exit;
}

$create_messages_sql = "CREATE TABLE IF NOT EXISTS direct_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sender_email VARCHAR(190) NOT NULL,
    recipient_email VARCHAR(190) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_conversation (sender_email, recipient_email, created_at),
    KEY idx_recipient (recipient_email, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8";

if (!$conn_users->query($create_messages_sql)) {
    chat_respond(array("success" => false, "error" => "Failed to prepare messages table"), 500);
}

$create_community_sql = "CREATE TABLE IF NOT EXISTS community_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    community VARCHAR(80) NOT NULL,
    sender_email VARCHAR(190) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_community_messages (community, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8";

if (!$conn_users->query($create_community_sql)) {
    chat_respond(array("success" => false, "error" => "Failed to prepare community messages table"), 500);
}

$conn_users->query("DELETE FROM community_messages WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");

$body = json_decode(file_get_contents("php://input"), true);
if (!is_array($body)) {
    $body = array();
}

$email = strtolower(trim(isset($body["email"]) ? $body["email"] : ""));
$token = trim(isset($body["auth_token"]) ? $body["auth_token"] : "");
$action = isset($body["action"]) ? $body["action"] : "";

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $token === "") {
    chat_respond(array("success" => false, "error" => "Please sign in again"), 401);
}

$token_hash = hash("sha256", $token);
$auth = $conn_users->prepare("SELECT user_email FROM auth_tokens WHERE user_email = ? AND token_hash = ? AND expires_at > NOW() LIMIT 1");
if (!$auth) {
    chat_respond(array("success" => false, "error" => "Failed to verify account session"), 500);
}
$auth->bind_param("ss", $email, $token_hash);
$auth->execute();
$auth->store_result();
if ($auth->num_rows !== 1) {
    $auth->close();
    chat_respond(array("success" => false, "error" => "Your session expired. Please log in again"), 401);
}
$auth->close();

if ($action === "users") {
    $stmt = $conn_users->prepare("SELECT full_name, email FROM users WHERE email <> ? ORDER BY full_name ASC");
    if (!$stmt) {
        chat_respond(array("success" => false, "error" => "Failed to load users"), 500);
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $users = array();
    while ($row = $result->fetch_assoc()) {
        $users[] = array("name" => $row["full_name"], "email" => $row["email"]);
    }
    $stmt->close();
    chat_respond(array("success" => true, "users" => $users));
}

$communities = array("Recommendation Builds", "Part Pricing", "Troubleshooting", "Build Showcase");
$community = trim(isset($body["community"]) ? $body["community"] : "");
if (($action === "community_messages" || $action === "community_send") && !in_array($community, $communities, true)) {
    chat_respond(array("success" => false, "error" => "Invalid community"), 400);
}

if ($action === "community_messages") {
    $stmt = $conn_users->prepare("SELECT messages.sender_email, users.full_name AS sender_name, messages.message, messages.created_at FROM community_messages messages INNER JOIN users ON users.email = messages.sender_email WHERE messages.community = ? AND messages.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) ORDER BY messages.created_at ASC, messages.id ASC LIMIT 200");
    $stmt->bind_param("s", $community);
    $stmt->execute();
    $result = $stmt->get_result();
    $messages = array();
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    $stmt->close();
    chat_respond(array("success" => true, "messages" => $messages));
}

if ($action === "community_send") {
    $message = trim(isset($body["message"]) ? $body["message"] : "");
    if ($message === "" || strlen($message) > 2000) {
        chat_respond(array("success" => false, "error" => "Message must be between 1 and 2000 characters"), 400);
    }
    $stmt = $conn_users->prepare("INSERT INTO community_messages (community, sender_email, message) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $community, $email, $message);
    if (!$stmt->execute()) {
        $stmt->close();
        chat_respond(array("success" => false, "error" => "Message could not be sent"), 500);
    }
    $stmt->close();
    chat_respond(array("success" => true));
}

$recipient = strtolower(trim(isset($body["recipient"]) ? $body["recipient"] : ""));
if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || $recipient === $email) {
    chat_respond(array("success" => false, "error" => "Choose another registered user"), 400);
}

$check_user = $conn_users->prepare("SELECT email FROM users WHERE email = ? LIMIT 1");
$check_user->bind_param("s", $recipient);
$check_user->execute();
$check_user->store_result();
if ($check_user->num_rows !== 1) {
    $check_user->close();
    chat_respond(array("success" => false, "error" => "That user does not exist"), 404);
}
$check_user->close();

if ($action === "messages") {
    $stmt = $conn_users->prepare("SELECT sender_email, recipient_email, message, created_at FROM direct_messages WHERE (sender_email = ? AND recipient_email = ?) OR (sender_email = ? AND recipient_email = ?) ORDER BY created_at ASC, id ASC LIMIT 200");
    $stmt->bind_param("ssss", $email, $recipient, $recipient, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $messages = array();
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    $stmt->close();
    chat_respond(array("success" => true, "messages" => $messages));
}

if ($action === "send") {
    $message = trim(isset($body["message"]) ? $body["message"] : "");
    if ($message === "" || strlen($message) > 2000) {
        chat_respond(array("success" => false, "error" => "Message must be between 1 and 2000 characters"), 400);
    }
    $stmt = $conn_users->prepare("INSERT INTO direct_messages (sender_email, recipient_email, message) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $email, $recipient, $message);
    if (!$stmt->execute()) {
        $stmt->close();
        chat_respond(array("success" => false, "error" => "Message could not be sent"), 500);
    }
    $stmt->close();
    chat_respond(array("success" => true));
}

chat_respond(array("success" => false, "error" => "Unsupported action"), 400);
