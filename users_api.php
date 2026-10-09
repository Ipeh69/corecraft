<?php
require_once "database_users.php";

header("Content-Type: application/json; charset=UTF-8");

function respond($payload, $status_code = 200) {
    if (function_exists("http_response_code")) {
        http_response_code($status_code);
    } else {
        header("HTTP/1.1 " . $status_code);
    }
    echo json_encode($payload);
    exit;
}

if (!function_exists("password_hash")) {
    if (!defined("PASSWORD_DEFAULT")) {
        define("PASSWORD_DEFAULT", 1);
    }

    function password_hash($password, $algorithm) {
        return crypt($password, "$2a$10$" . substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ./"), 0, 22));
    }
}

if (!function_exists("password_verify")) {
    function password_verify($password, $hash) {
        return crypt($password, $hash) === $hash;
    }
}

$create_table_sql = "CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8";

if (!$conn_users->query($create_table_sql)) {
    respond(array("success" => false, "error" => "Failed to prepare users table"), 500);
}

$token_table_sql = "CREATE TABLE IF NOT EXISTS auth_tokens (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_email VARCHAR(190) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_auth_token (token_hash),
    KEY idx_auth_email (user_email),
    KEY idx_auth_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8";

if (!$conn_users->query($token_table_sql)) {
    respond(array("success" => false, "error" => "Failed to prepare account sessions"), 500);
}

function issue_auth_token($conn, $email) {
    $random_bytes = false;
    if (function_exists("random_bytes")) {
        try {
            $random_bytes = random_bytes(32);
        } catch (Exception $error) {
            $random_bytes = false;
        }
    }
    if ($random_bytes === false && function_exists("openssl_random_pseudo_bytes")) {
        $crypto_strong = false;
        $random_bytes = openssl_random_pseudo_bytes(32, $crypto_strong);
        if ($random_bytes === false || !$crypto_strong) {
            return false;
        }
    }
    if ($random_bytes === false) {
        return false;
    }
    $token = bin2hex($random_bytes);
    $token_hash = hash("sha256", $token);
    $stmt = $conn->prepare("INSERT INTO auth_tokens (user_email, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("ss", $email, $token_hash);
    if (!$stmt->execute()) {
        $stmt->close();
        return false;
    }
    $stmt->close();
    return $token;
}

$body = json_decode(file_get_contents("php://input"), true);
if (!is_array($body)) {
    $body = array();
}
$action = isset($body["action"]) ? $body["action"] : "";
$email = strtolower(trim(isset($body["email"]) ? $body["email"] : ""));
$password = isset($body["password"]) ? $body["password"] : "";

if ($action === "google") {
    $credential = isset($body["credential"]) ? trim($body["credential"]) : "";
    $google_client_id = trim((string) CORECRAFT_GOOGLE_CLIENT_ID);
    if ($google_client_id === "" || strpos($google_client_id, "PASTE_") === 0 || strpos($google_client_id, "YOUR_") === 0) {
        respond(array("success" => false, "error" => "Google sign-in is not configured. Add the OAuth Web Client ID to CORECRAFT_GOOGLE_CLIENT_ID in config.php."), 503);
    }
    if ($credential === "") {
        respond(array("success" => false, "error" => "Google did not return a sign-in credential. Check the authorized domain and try again."), 401);
    }
    $token_json = false;
    if (function_exists("curl_init")) {
        $verify = curl_init("https://oauth2.googleapis.com/tokeninfo?id_token=" . rawurlencode($credential));
        curl_setopt($verify, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($verify, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($verify, CURLOPT_TIMEOUT, 10);
        $token_json = curl_exec($verify);
        $verify_status = (int) curl_getinfo($verify, CURLINFO_HTTP_CODE);
        curl_close($verify);
        if ($token_json === false || $verify_status !== 200) $token_json = false;
    } else {
        $context = stream_context_create(array("http" => array("timeout" => 10)));
        $token_json = @file_get_contents("https://oauth2.googleapis.com/tokeninfo?id_token=" . rawurlencode($credential), false, $context);
    }
    if ($token_json === false) {
        respond(array("success" => false, "error" => "Could not verify the Google sign-in with Google. Check that outbound HTTPS/cURL is enabled on the hosting server."), 503);
    }
    $token = $token_json === false ? null : json_decode($token_json, true);
    if (!is_array($token)
        || $google_client_id !== (isset($token["aud"]) ? $token["aud"] : "")
        || !isset($token["iss"]) || !in_array($token["iss"], array("accounts.google.com", "https://accounts.google.com"), true)
        || !isset($token["email_verified"]) || $token["email_verified"] !== "true"
        || !isset($token["exp"]) || (int) $token["exp"] <= time()
        || !filter_var(isset($token["email"]) ? $token["email"] : "", FILTER_VALIDATE_EMAIL)) {
        respond(array("success" => false, "error" => "Google account verification failed"), 401);
    }
    $google_email = strtolower($token["email"]);
    $stmt = $conn_users->prepare("SELECT full_name, email FROM users WHERE email = ? LIMIT 1");
    if (!$stmt) {
        respond(array("success" => false, "error" => "Failed to prepare Google sign-in"), 500);
    }
    $stmt->bind_param("s", $google_email);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($name, $stored_email);
    if ($stmt->fetch()) {
        $stmt->close();
        $auth_token = issue_auth_token($conn_users, $stored_email);
        if ($auth_token === false) {
            respond(array("success" => false, "error" => "Could not create an account session"), 500);
        }
        respond(array("success" => true, "user" => array("name" => $name, "email" => $stored_email, "auth_token" => $auth_token)));
    }
    $stmt->close();
    $name = trim(isset($token["name"]) ? $token["name"] : "Google User");
    $generated_password = password_hash(uniqid("google_", true) . mt_rand(), PASSWORD_DEFAULT);
    $stmt = $conn_users->prepare("INSERT INTO users (full_name, email, password_hash) VALUES (?, ?, ?)");
    if (!$stmt) {
        respond(array("success" => false, "error" => "Failed to prepare Google account"), 500);
    }
    $stmt->bind_param("sss", $name, $google_email, $generated_password);
    if (!$stmt->execute()) {
        $stmt->close();
        respond(array("success" => false, "error" => "Could not create the Google account"), 500);
    }
    $stmt->close();
    $auth_token = issue_auth_token($conn_users, $google_email);
    if ($auth_token === false) {
        respond(array("success" => false, "error" => "Could not create an account session"), 500);
    }
    respond(array("success" => true, "user" => array("name" => $name, "email" => $google_email, "auth_token" => $auth_token)), 201);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === "") {
    respond(array("success" => false, "error" => "Valid email and password are required"), 400);
}

if ($action === "register") {
    $name = trim(isset($body["name"]) ? $body["name"] : "");
    if ($name === "" || strlen($name) > 120) {
        respond(array("success" => false, "error" => "Full name is required"), 400);
    }

    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn_users->prepare("INSERT INTO users (full_name, email, password_hash) VALUES (?, ?, ?)");
    if (!$stmt) {
        respond(array("success" => false, "error" => "Failed to prepare registration"), 500);
    }
    $stmt->bind_param("sss", $name, $email, $password_hash);
    if (!$stmt->execute()) {
        $duplicate = $stmt->errno === 1062;
        $stmt->close();
        respond(array("success" => false, "error" => $duplicate ? "An account with that email already exists" : "Registration failed"), $duplicate ? 409 : 500);
    }
    $stmt->close();
    $auth_token = issue_auth_token($conn_users, $email);
    if ($auth_token === false) {
        respond(array("success" => false, "error" => "Could not create an account session"), 500);
    }
    respond(array("success" => true, "user" => array("name" => $name, "email" => $email, "auth_token" => $auth_token)), 201);
}

if ($action === "login") {
    $stmt = $conn_users->prepare("SELECT full_name, email, password_hash FROM users WHERE email = ? LIMIT 1");
    if (!$stmt) {
        respond(array("success" => false, "error" => "Failed to prepare login"), 500);
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($name, $stored_email, $password_hash);
    if (!$stmt->fetch() || !password_verify($password, $password_hash)) {
        $stmt->close();
        respond(array("success" => false, "error" => "Invalid email or password"), 401);
    }
    $stmt->close();
    $auth_token = issue_auth_token($conn_users, $stored_email);
    if ($auth_token === false) {
        respond(array("success" => false, "error" => "Could not create an account session"), 500);
    }
    respond(array("success" => true, "user" => array("name" => $name, "email" => $stored_email, "auth_token" => $auth_token)));
}

respond(array("success" => false, "error" => "Unsupported action"), 400);
