<?php
require_once "database.php";

header("Content-Type: application/json; charset=UTF-8");

function normalize_category($value) {
    $cat = strtolower(trim($value));
    if ($cat === "student") {
        return "students";
    }
    return $cat;
}

function estimate_budget($build_name, $category) {
    $name = strtolower($build_name);
    if (strpos($name, "budget") !== false || strpos($name, "basic") !== false || strpos($name, "saver") !== false) {
        return 20000;
    }
    if (strpos($name, "mid") !== false || strpos($name, "value") !== false) {
        return 40000;
    }
    if (strpos($name, "high") !== false || strpos($name, "beast") !== false) {
        return 70000;
    }
    return 30000;
}

function recommendation_pros($category, $build_name) {
    if ($category === "gaming") return "Good 1080p gaming performance with a dedicated GPU and balanced CPU.";
    if ($category === "office") return "Responsive for documents, browser multitasking, video calls, and daily productivity.";
    if ($category === "students") return "Affordable and practical for schoolwork, coding, research, and light creative tasks.";
    if ($category === "streaming") return "Handles livestreaming and esports while keeping the total build cost controlled.";
    if ($category === "editing") return "More memory and fast storage improve timeline editing, exports, and content creation.";
    if ($category === "workstation") return "High core count, large memory capacity, and dedicated graphics support demanding workloads.";
    return "Reliable everyday performance with low power use and enough speed for common home and office tasks.";
}

function recommendation_cons($category, $build_name) {
    if ($category === "gaming") return "The graphics card may need an upgrade for high-refresh 1440p or 4K gaming.";
    if ($category === "office") return "Integrated graphics are not suitable for modern AAA games or GPU-heavy applications.";
    if ($category === "students") return "Limited graphics performance and storage may require upgrades for heavier projects.";
    if ($category === "streaming") return "Entry-level parts can struggle with demanding games and high-quality 4K streams.";
    if ($category === "editing") return "Export times and complex effects are slower than on a higher-end workstation build.";
    if ($category === "workstation") return "Higher power consumption and cost make it excessive for basic office or school use.";
    return "Not designed for intensive gaming, 3D rendering, or professional video production.";
}

$response = array(
    "gaming" => array(),
    "office" => array(),
    "students" => array(),
    "streaming" => array(),
    "editing" => array(),
    "workstation" => array(),
    "home" => array()
);

function fallback_recommendations() {
    return array(
        "gaming" => array(
            array("id" => "fallback-gaming-1", "build_name" => "Gaming Value 1080p", "description" => "Balanced entry gaming build for esports and modern games.", "estimated_budget" => 28000, "components" => array(array("cat" => "CPU", "name" => "Ryzen 5 5600"), array("cat" => "Motherboard", "name" => "B550M"), array("cat" => "RAM", "name" => "16GB DDR4"), array("cat" => "GPU", "name" => "RX 6600 8GB"), array("cat" => "Storage", "name" => "1TB NVMe SSD"), array("cat" => "PSU", "name" => "550W 80+ Bronze"))),
            array("id" => "fallback-gaming-2", "build_name" => "Gaming Beast 1440p", "description" => "High-performance build for demanding 1080p and 1440p gaming.", "estimated_budget" => 65000, "components" => array(array("cat" => "CPU", "name" => "Ryzen 5 7600X"), array("cat" => "Motherboard", "name" => "B650 ATX"), array("cat" => "RAM", "name" => "32GB DDR5"), array("cat" => "GPU", "name" => "RTX 4070 12GB"), array("cat" => "Storage", "name" => "1TB NVMe SSD"), array("cat" => "PSU", "name" => "750W 80+ Gold")))
        ),
        "office" => array(
            array("id" => "fallback-office-1", "build_name" => "Office Productivity", "description" => "Reliable system for documents, browser work, meetings, and productivity.", "estimated_budget" => 26000, "components" => array(array("cat" => "CPU", "name" => "Intel Core i3-12100"), array("cat" => "Motherboard", "name" => "H610M"), array("cat" => "RAM", "name" => "16GB DDR4"), array("cat" => "GPU", "name" => "Integrated UHD Graphics"), array("cat" => "Storage", "name" => "500GB SATA SSD"), array("cat" => "PSU", "name" => "500W 80+ Bronze")))
        ),
        "students" => array(
            array("id" => "fallback-students-1", "build_name" => "Student Smart Ryzen 5", "description" => "Practical PC for schoolwork, coding, research, and online classes.", "estimated_budget" => 22000, "components" => array(array("cat" => "CPU", "name" => "Ryzen 5 5600G"), array("cat" => "Motherboard", "name" => "B550M"), array("cat" => "RAM", "name" => "16GB DDR4"), array("cat" => "GPU", "name" => "Integrated Radeon Graphics"), array("cat" => "Storage", "name" => "512GB NVMe SSD"), array("cat" => "PSU", "name" => "500W 80+ Bronze")))
        ),
        "streaming" => array(
            array("id" => "fallback-streaming-1", "build_name" => "Starter Streaming", "description" => "Affordable setup for livestreaming and esports content.", "estimated_budget" => 30000, "components" => array(array("cat" => "CPU", "name" => "Ryzen 5 5600"), array("cat" => "Motherboard", "name" => "B550M"), array("cat" => "RAM", "name" => "16GB DDR4"), array("cat" => "GPU", "name" => "RTX 3060 12GB"), array("cat" => "Storage", "name" => "1TB NVMe SSD"), array("cat" => "PSU", "name" => "650W 80+ Bronze")))
        ),
        "editing" => array(
            array("id" => "fallback-editing-1", "build_name" => "Creator Editing", "description" => "Editing-focused build for 1080p projects and content creation.", "estimated_budget" => 42000, "components" => array(array("cat" => "CPU", "name" => "Ryzen 7 7700"), array("cat" => "Motherboard", "name" => "B650M"), array("cat" => "RAM", "name" => "32GB DDR5"), array("cat" => "GPU", "name" => "RTX 4060 8GB"), array("cat" => "Storage", "name" => "2TB NVMe SSD"), array("cat" => "PSU", "name" => "650W 80+ Gold")))
        ),
        "workstation" => array(
            array("id" => "fallback-workstation-1", "build_name" => "Developer Workstation", "description" => "Powerful build for development, rendering, virtualization, and multitasking.", "estimated_budget" => 55000, "components" => array(array("cat" => "CPU", "name" => "Ryzen 9 7900"), array("cat" => "Motherboard", "name" => "B650 ATX"), array("cat" => "RAM", "name" => "64GB DDR5"), array("cat" => "GPU", "name" => "RTX 4070 12GB"), array("cat" => "Storage", "name" => "2TB NVMe SSD"), array("cat" => "PSU", "name" => "750W 80+ Gold")))
        ),
        "home" => array(
            array("id" => "fallback-home-1", "build_name" => "Home Office Essential", "description" => "Quiet everyday system for browsing, documents, calls, and media.", "estimated_budget" => 20000, "components" => array(array("cat" => "CPU", "name" => "Intel Core i3-12100"), array("cat" => "Motherboard", "name" => "H610M"), array("cat" => "RAM", "name" => "16GB DDR4"), array("cat" => "GPU", "name" => "Integrated UHD Graphics"), array("cat" => "Storage", "name" => "500GB SATA SSD"), array("cat" => "PSU", "name" => "500W 80+ Bronze")))
        )
    );
}

$fallback = fallback_recommendations();
if (!$conn || !is_object($conn)) {
    echo json_encode($fallback);
    exit;
}

$conn->query("ALTER TABLE build_recommendations ADD COLUMN price DECIMAL(10,2) NOT NULL DEFAULT 0");
$conn->query("ALTER TABLE build_recommendations ADD COLUMN pros TEXT NULL");
$conn->query("ALTER TABLE build_recommendations ADD COLUMN cons TEXT NULL");

$sql = "SELECT id, build_name, category, description, price, pros, cons, cpu, motherboard, ram, gpu, storage, psu
    FROM build_recommendations
                ORDER BY
                    CASE
                        WHEN LOWER(build_name) LIKE '%budget%' THEN 1
                        WHEN LOWER(build_name) LIKE '%mid%' THEN 2
                        WHEN LOWER(build_name) LIKE '%high%' THEN 3
                        ELSE 4
                    END,
                    id ASC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode($fallback);
    exit;
}

$stmt->execute();
$stmt->store_result();
$stmt->bind_result($id, $build_name, $category, $description, $price, $pros, $cons, $cpu, $motherboard, $ram, $gpu, $storage, $psu);

while ($stmt->fetch()) {
    $normalized_category = normalize_category($category);
    if (!isset($response[$normalized_category])) {
        continue;
    }

    $components = array(
        array("cat" => "CPU", "name" => $cpu),
        array("cat" => "Motherboard", "name" => $motherboard),
        array("cat" => "RAM", "name" => $ram),
        array("cat" => "GPU", "name" => $gpu),
        array("cat" => "Storage", "name" => $storage),
        array("cat" => "PSU", "name" => $psu)
    );

    $response[$normalized_category][] = array(
        "id" => $id,
        "build_name" => $build_name,
        "description" => $description ? $description : "",
        "category" => $normalized_category,
        "estimated_budget" => ((float) $price > 0) ? (float) $price : estimate_budget($build_name, $normalized_category),
        "pros" => $pros ? $pros : recommendation_pros($normalized_category, $build_name),
        "cons" => $cons ? $cons : recommendation_cons($normalized_category, $build_name),
        "components" => $components
    );
}

$stmt->free_result();

$stmt->close();
$conn->close();

foreach ($response as $category_name => $category_recommendations) {
    if (count($category_recommendations) === 0 && isset($fallback[$category_name])) {
        $response[$category_name] = $fallback[$category_name];
    }
}

echo json_encode($response);
