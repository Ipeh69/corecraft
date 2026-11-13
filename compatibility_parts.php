<?php
require_once __DIR__ . "/config.php";
header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, max-age=0");

$allowed = array("cpu", "motherboard", "ram", "gpu", "storage", "psu");
$category = isset($_GET["category"]) ? trim((string) $_GET["category"]) : "";
if (!in_array($category, $allowed, true)) {
    http_response_code(400);
    echo json_encode(array("success" => false, "error" => "Invalid component category"));
    exit;
}

$conn = new mysqli(CORECRAFT_DB_HOST, CORECRAFT_DB_USER, CORECRAFT_DB_PASSWORD, CORECRAFT_DB_NAME);
if ($conn->connect_error) {
    http_response_code(503);
    echo json_encode(array("success" => false, "error" => "Component catalog unavailable"));
    exit;
}
$stmt = $conn->prepare("SELECT id, brand, model, socket, tdp_watts, form_factor, price_php, specs_json FROM components WHERE category = ? AND is_active = 1 ORDER BY brand ASC, model ASC LIMIT 500");
if (!$stmt) {
    http_response_code(503);
    echo json_encode(array("success" => false, "error" => "Component catalog unavailable"));
    exit;
}
$stmt->bind_param("s", $category);
$stmt->execute();
$result = $stmt->get_result();
$components = array();
while ($row = $result->fetch_assoc()) {
    $specs = json_decode($row["specs_json"], true);
    if (!is_array($specs)) $specs = array();
    $details = array();
    if ($row["socket"]) $details[] = $row["socket"];
    if ($row["tdp_watts"] !== null && (int) $row["tdp_watts"] > 0) $details[] = (int) $row["tdp_watts"] . "W";
    if ($row["form_factor"]) $details[] = $row["form_factor"];
    foreach (array("memory_type", "type", "capacity", "interface", "wattage", "efficiency") as $key) {
        if (isset($specs[$key]) && $specs[$key] !== "") $details[] = (string) $specs[$key];
    }
    $components[] = array(
        "id" => (int) $row["id"],
        "name" => trim($row["brand"] . " " . $row["model"]),
        "price" => (float) $row["price_php"],
        "details" => implode(" / ", array_unique($details))
    );
}
$stmt->close();
$conn->close();
echo json_encode(array("success" => true, "components" => $components));
