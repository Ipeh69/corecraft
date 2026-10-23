<?php
// CoreCraft admin API: catalog, compatibility rules, moderation, pricing feeds and analytics.
// Standalone JSON endpoint (admin_api.php). Requires an authenticated admin (or moderator) session.
require_once __DIR__ . "/config.php";

$conn_admin = new mysqli(CORECRAFT_DB_HOST, CORECRAFT_DB_USER, CORECRAFT_DB_PASSWORD, CORECRAFT_DB_NAME);
if ($conn_admin->connect_error) {
    header("Content-Type: application/json; charset=UTF-8");
    http_response_code(503);
    echo json_encode(array(
        "success" => false,
        "error" => "The admin database is unavailable. Check the database settings (error code " . (int) $conn_admin->connect_errno . ")."
    ));
    exit;
}

header("Content-Type: application/json; charset=UTF-8");

function admin_respond($payload, $status_code = 200)
{
    http_response_code($status_code);
    echo json_encode($payload);
    exit;
}

function admin_field($body, $key, $default = "")
{
    return isset($body[$key]) ? $body[$key] : $default;
}

function admin_log($conn, $level, $source, $message)
{
    $stmt = $conn->prepare("INSERT INTO system_logs (level, source, message) VALUES (?, ?, ?)");
    if (!$stmt) {
        return;
    }
    $level = substr((string) $level, 0, 20);
    $source = substr((string) $source, 0, 60);
    $message = substr((string) $message, 0, 2000);
    $stmt->bind_param("sss", $level, $source, $message);
    $stmt->execute();
    $stmt->close();
}

// Adds a column only when it is missing (older MySQL lacks ADD COLUMN IF NOT EXISTS).
function admin_ensure_column($conn, $table, $column, $alter_sql)
{
    $stmt = $conn->prepare("SELECT COUNT(*) AS column_count FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    if (!$stmt) {
        return;
    }
    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    if ($row && (int) $row["column_count"] === 0) {
        $conn->query($alter_sql);
    }
}

function admin_scalar($conn, $sql, $default = 0)
{
    $result = $conn->query($sql);
    if (!$result) {
        return $default;
    }
    $row = $result->fetch_row();
    return $row ? (int) $row[0] : $default;
}

// ——— Schema bootstrap ———
$conn_admin->query("CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8");

admin_ensure_column($conn_admin, "users", "role", "ALTER TABLE users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'user'");
admin_ensure_column($conn_admin, "users", "status", "ALTER TABLE users ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active'");
admin_ensure_column($conn_admin, "users", "last_login_at", "ALTER TABLE users ADD COLUMN last_login_at DATETIME NULL");

$conn_admin->query("CREATE TABLE IF NOT EXISTS auth_tokens (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_email VARCHAR(190) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_auth_token (token_hash),
    KEY idx_auth_email (user_email),
    KEY idx_auth_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8");

$conn_admin->query("CREATE TABLE IF NOT EXISTS components (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    category VARCHAR(40) NOT NULL,
    brand VARCHAR(80) NOT NULL,
    model VARCHAR(160) NOT NULL,
    socket VARCHAR(40) NULL,
    tdp_watts INT NULL,
    form_factor VARCHAR(60) NULL,
    price_php DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    price_usd DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    stock_status VARCHAR(30) NOT NULL DEFAULT 'unknown',
    specs_json TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_component_model (category, brand, model),
    KEY idx_component_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8");

$conn_admin->query("CREATE TABLE IF NOT EXISTS compatibility_rules (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    rule_key VARCHAR(80) NOT NULL,
    name VARCHAR(160) NOT NULL,
    category VARCHAR(60) NOT NULL,
    severity VARCHAR(20) NOT NULL DEFAULT 'warning',
    logic_summary TEXT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rule_key (rule_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8");

$conn_admin->query("CREATE TABLE IF NOT EXISTS pricing_regions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    region VARCHAR(80) NOT NULL,
    currency VARCHAR(8) NOT NULL DEFAULT 'PHP',
    multiplier DECIMAL(6,3) NOT NULL DEFAULT 1.000,
    notes VARCHAR(255) NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_region (region)
) ENGINE=InnoDB DEFAULT CHARSET=utf8");

$conn_admin->query("CREATE TABLE IF NOT EXISTS moderation_reports (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    content_type VARCHAR(40) NOT NULL,
    content_ref VARCHAR(190) NOT NULL,
    community VARCHAR(80) NULL,
    reported_by VARCHAR(190) NOT NULL,
    reason VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'open',
    resolved_by VARCHAR(190) NULL,
    resolved_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_report_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8");

$conn_admin->query("CREATE TABLE IF NOT EXISTS system_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    level VARCHAR(20) NOT NULL DEFAULT 'info',
    source VARCHAR(60) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_log_level (level, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8");

$conn_admin->query("CREATE TABLE IF NOT EXISTS analytics_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_type VARCHAR(60) NOT NULL,
    detail VARCHAR(255) NULL,
    user_email VARCHAR(190) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_event_type (event_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8");

// Tables owned by other endpoints, created here so admin statistics always resolve.
$conn_admin->query("CREATE TABLE IF NOT EXISTS builds (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    build_name VARCHAR(120) NOT NULL,
    description TEXT NOT NULL,
    components_text TEXT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    category VARCHAR(20) NOT NULL,
    is_recommended TINYINT(1) NOT NULL DEFAULT 0,
    rating DECIMAL(3,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_build_name_category (build_name, category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8");

$conn_admin->query("CREATE TABLE IF NOT EXISTS user_saved_builds (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_email VARCHAR(190) NOT NULL,
    build_name VARCHAR(120) NOT NULL,
    components_json TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_build (user_email, build_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8");

$conn_admin->query("CREATE TABLE IF NOT EXISTS communities (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(80) NOT NULL,
    description VARCHAR(255) NOT NULL,
    created_by VARCHAR(190) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_community_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8");

$conn_admin->query("CREATE TABLE IF NOT EXISTS community_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    community VARCHAR(80) NOT NULL,
    sender_email VARCHAR(190) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_community_messages (community, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8");

$conn_admin->query("CREATE TABLE IF NOT EXISTS community_posts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    community VARCHAR(80) NOT NULL,
    user_email VARCHAR(190) NOT NULL,
    body TEXT NOT NULL,
    image_path VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_posts_community (community, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8");

$conn_admin->query("CREATE TABLE IF NOT EXISTS community_post_comments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    post_id BIGINT UNSIGNED NOT NULL,
    user_email VARCHAR(190) NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_post_comments (post_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8");

$conn_admin->query("CREATE TABLE IF NOT EXISTS private_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sender_email VARCHAR(190) NOT NULL,
    recipient_email VARCHAR(190) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_dm_sender (sender_email, created_at),
    KEY idx_dm_recipient (recipient_email, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8");

// ——— Seed reference data (idempotent) ———
$conn_admin->query("INSERT IGNORE INTO components (category, brand, model, socket, tdp_watts, form_factor, price_php, price_usd, stock_status, specs_json) VALUES
('cpu','AMD','Ryzen 5 5600','AM4',65,'',7499.00,135.00,'in_stock','{\"cores\":6,\"threads\":12,\"base_clock\":\"3.5 GHz\",\"boost_clock\":\"4.4 GHz\",\"memory_type\":\"DDR4\"}'),
('cpu','AMD','Ryzen 7 7800X3D','AM5',120,'',21999.00,399.00,'in_stock','{\"cores\":8,\"threads\":16,\"boost_clock\":\"5.0 GHz\",\"memory_type\":\"DDR5\",\"l3_cache\":\"96 MB\"}'),
('cpu','Intel','Core i5-12400F','LGA1700',65,'',8999.00,160.00,'in_stock','{\"cores\":6,\"threads\":12,\"boost_clock\":\"4.4 GHz\",\"memory_type\":\"DDR5/DDR4\"}'),
('cpu','Intel','Core i7-14700K','LGA1700',125,'',22999.00,415.00,'in_stock','{\"cores\":20,\"threads\":28,\"boost_clock\":\"5.6 GHz\",\"memory_type\":\"DDR5/DDR4\"}'),
('motherboard','MSI','B550M PRO-VDH WIFI','AM4',0,'mATX',5499.00,99.00,'in_stock','{\"chipset\":\"B550\",\"dimm_slots\":4,\"memory_type\":\"DDR4\",\"pcie\":\"4.0\"}'),
('motherboard','ASUS','TUF GAMING B650-PLUS','AM5',0,'ATX',12499.00,225.00,'in_stock','{\"chipset\":\"B650\",\"dimm_slots\":4,\"memory_type\":\"DDR5\",\"pcie\":\"4.0\"}'),
('motherboard','Gigabyte','B760M DS3H','LGA1700',0,'mATX',6999.00,125.00,'in_stock','{\"chipset\":\"B760\",\"dimm_slots\":4,\"memory_type\":\"DDR5\",\"pcie\":\"4.0\"}'),
('gpu','NVIDIA','GeForce RTX 4060',NULL,170,'dual-slot',18999.00,299.00,'in_stock','{\"vram\":\"8 GB GDDR6\",\"length_mm\":200,\"power_connector\":\"8-pin\",\"pcie\":\"4.0 x8\"}'),
('gpu','NVIDIA','GeForce RTX 4070 SUPER',NULL,220,'dual-slot',38999.00,599.00,'in_stock','{\"vram\":\"12 GB GDDR6X\",\"length_mm\":305,\"power_connector\":\"16-pin 12VHPWR\",\"pcie\":\"4.0 x16\"}'),
('gpu','AMD','Radeon RX 7600',NULL,165,'dual-slot',17999.00,269.00,'in_stock','{\"vram\":\"8 GB GDDR6\",\"length_mm\":204,\"power_connector\":\"8-pin\",\"pcie\":\"4.0 x8\"}'),
('ram','Kingston','Fury Beast 16GB (2x8) DDR4 3200',NULL,0,'DIMM',2499.00,45.00,'in_stock','{\"type\":\"DDR4\",\"capacity\":\"16 GB\",\"speed\":\"3200 MT/s\",\"profile\":\"XMP 2.0\"}'),
('ram','G.Skill','Trident Z5 32GB (2x16) DDR5 6000',NULL,0,'DIMM',7499.00,120.00,'in_stock','{\"type\":\"DDR5\",\"capacity\":\"32 GB\",\"speed\":\"6000 MT/s\",\"profile\":\"XMP 3.0 / EXPO\"}'),
('storage','Samsung','980 PRO 1TB NVMe',NULL,6,'M.2 2280',6499.00,110.00,'in_stock','{\"capacity\":\"1 TB\",\"interface\":\"PCIe 4.0 x4 NVMe\",\"read\":\"7000 MB/s\"}'),
('storage','Kingston','NV2 500GB NVMe',NULL,4,'M.2 2280',1999.00,35.00,'low_stock','{\"capacity\":\"500 GB\",\"interface\":\"PCIe 4.0 x4 NVMe\"}'),
('psu','Corsair','RM750e 750W 80+ Gold',NULL,750,'ATX',5999.00,100.00,'in_stock','{\"wattage\":750,\"efficiency\":\"80+ Gold\",\"modular\":\"fully modular\",\"atx_version\":\"ATX 3.0\",\"connectors\":\"12VHPWR\"}'),
('psu','Seasonic','Focus GX-650 650W 80+ Gold',NULL,650,'ATX',4999.00,90.00,'in_stock','{\"wattage\":650,\"efficiency\":\"80+ Gold\",\"modular\":\"fully modular\"}'),
('case','Lian Li','Lancool 216',NULL,0,'Mid Tower',4499.00,90.00,'in_stock','{\"max_gpu_length_mm\":392,\"max_cooler_height_mm\":180,\"form_factors\":\"ATX/mATX/ITX\"}'),
('cooler','DeepCool','AK400',NULL,0,'Tower',1899.00,35.00,'in_stock','{\"height_mm\":155,\"sockets\":\"AM4/AM5/LGA1700\",\"tdp_rating\":220}'),
('cooler','Thermalright','Peerless Assassin 120 SE',NULL,0,'Dual Tower',2799.00,40.00,'in_stock','{\"height_mm\":155,\"sockets\":\"AM4/AM5/LGA1700\",\"tdp_rating\":265,\"ram_clearance_mm\":38}')
ON DUPLICATE KEY UPDATE model = VALUES(model)");

$conn_admin->query("INSERT IGNORE INTO compatibility_rules (rule_key, name, category, severity, logic_summary, is_enabled) VALUES
('socket_match','CPU socket must match motherboard socket','cpu-motherboard','error','Compare CPU.socket against Motherboard.socket. Mismatch blocks the build.',1),
('ram_type_match','RAM generation must match motherboard memory type','memory','error','DDR4 modules require a DDR4 board; DDR5 requires DDR5.',1),
('psu_wattage_headroom','PSU must exceed estimated load by 20%','power','warning','Sum component TDP, add 20% headroom, and compare against PSU wattage.',1),
('gpu_length_clearance','GPU must fit the case clearance','physical','error','GPU length must be below the case max GPU length.',1),
('cooler_ram_clearance','CPU cooler must clear tall RAM heatspreaders','physical','warning','Cooler height/fin clearance versus RAM module height.',1),
('bios_version_support','Motherboard BIOS must support the installed CPU','firmware','warning','New CPU generations may require a BIOS update on older boards.',1),
('pcie_generation','PCIe generation must be compatible','expansion','info','PCIe 3.0/4.0/5.0 devices are backwards compatible at the lower link speed.',1),
('cpu_gpu_bottleneck','Flag heavy CPU/GPU performance imbalance','performance','info','Estimate gaming bottleneck when the CPU or GPU differs by several tiers.',1),
('power_connector_support','PSU must supply the GPU power connector','power','error','12VHPWR and 8-pin requirements must be available on the PSU.',1),
('ram_speed_profile','Enable XMP/EXPO for rated RAM speed','memory','info','Rated DDR5 6000+ speeds require XMP 3.0 or EXPO profiles.',0)
ON DUPLICATE KEY UPDATE name = VALUES(name)");

$conn_admin->query("INSERT IGNORE INTO pricing_regions (region, currency, multiplier, notes) VALUES
('NCR / Metro Manila','PHP',1.000,'Baseline local market pricing'),
('Luzon (Provincial)','PHP',1.030,'+3% average shipping and distributor markup'),
('Visayas','PHP',1.060,'+6% average inter-island freight'),
('Mindanao','PHP',1.075,'+7.5% average freight and limited supply'),
('Online / Global','USD',1.000,'USD reference pricing for AI estimation')
ON DUPLICATE KEY UPDATE notes = VALUES(notes)");

// ——— Admin access bootstrap ———
$admin_email_list = strtolower(trim(corecraft_env("CORECRAFT_ADMIN_EMAILS", "")));
if ($admin_email_list !== "") {
    foreach (preg_split('/[\s,;]+/', $admin_email_list) as $candidate) {
        $candidate = trim($candidate);
        if (filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
            $promote = $conn_admin->prepare("UPDATE users SET role = 'admin' WHERE email = ?");
            if ($promote) {
                $promote->bind_param("s", $candidate);
                $promote->execute();
                $promote->close();
            }
        }
    }
} elseif (admin_scalar($conn_admin, "SELECT COUNT(*) FROM users WHERE role = 'admin'") === 0) {
    // Fresh install: the earliest account becomes the platform owner so an admin always exists.
    $conn_admin->query("UPDATE users SET role = 'admin' ORDER BY id ASC LIMIT 1");
}

// ——— Authentication ———
$body = json_decode(file_get_contents("php://input"), true);
if (!is_array($body)) {
    $body = array();
}
$action = isset($body["action"]) ? $body["action"] : "";
$email = strtolower(trim(admin_field($body, "email", "")));
$token = trim(admin_field($body, "auth_token", ""));

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $token === "") {
    admin_respond(array("success" => false, "error" => "Please sign in again"), 401);
}

$token_hash = hash("sha256", $token);
$auth = $conn_admin->prepare("SELECT u.full_name, u.role, u.status FROM auth_tokens t INNER JOIN users u ON u.email = t.user_email WHERE t.user_email = ? AND t.token_hash = ? AND t.expires_at > NOW() LIMIT 1");
if (!$auth) {
    admin_respond(array("success" => false, "error" => "Failed to verify account session"), 500);
}
$auth->bind_param("ss", $email, $token_hash);
$auth->execute();
$auth->store_result();
$auth->bind_result($admin_name, $admin_role, $admin_status);
if ($auth->num_rows !== 1 || !$auth->fetch()) {
    $auth->close();
    admin_respond(array("success" => false, "error" => "Your session expired. Please log in again"), 401);
}
$auth->close();

$is_admin = $admin_role === "admin";
$is_moderator = $is_admin || $admin_role === "moderator";

if ($action === "whoami") {
    admin_respond(array(
        "success" => true,
        "is_admin" => $is_admin,
        "is_moderator" => $is_moderator,
        "role" => $admin_role,
        "name" => $admin_name,
        "status" => $admin_status
    ));
}

if ($admin_status === "banned") {
    admin_respond(array("success" => false, "error" => "This account has been suspended"), 403);
}

// Analytics tracking is open to any signed-in user so the dashboard has real usage data.
if ($action === "track") {
    $event_type = substr(trim(admin_field($body, "event_type", "")), 0, 60);
    if ($event_type === "") {
        admin_respond(array("success" => false, "error" => "Missing event type"), 400);
    }
    $detail = substr(trim(admin_field($body, "detail", "")), 0, 255);
    $stmt = $conn_admin->prepare("INSERT INTO analytics_events (event_type, detail, user_email) VALUES (?, ?, ?)");
    if (!$stmt) {
        admin_respond(array("success" => false, "error" => "Failed to record event"), 500);
    }
    $stmt->bind_param("sss", $event_type, $detail, $email);
    $stmt->execute();
    $stmt->close();
    admin_respond(array("success" => true));
}

// report_create is open to any signed-in user (the community UI flags content).
// Moderation actions are available to moderators; everything else requires a full admin.
$member_actions = array("report_create");
$moderator_actions = array("report_list", "report_resolve", "content_list", "content_delete");
if (in_array($action, $member_actions, true)) {
    // allowed for any authenticated account
} elseif (in_array($action, $moderator_actions, true)) {
    if (!$is_moderator) {
        admin_respond(array("success" => false, "error" => "Moderator access required"), 403);
    }
} elseif (!$is_admin) {
    admin_respond(array("success" => false, "error" => "Admin access required"), 403);
}

// Last N days (oldest first) of per-day counts, zero-filled.
function admin_daily_series($conn, $sql, $days = 14)
{
    $map = array();
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $map[$row["d"]][$row["k"]] = (int) $row["total"];
        }
    }
    $series = array();
    for ($i = $days - 1; $i >= 0; $i--) {
        $d = date("Y-m-d", strtotime("-$i day"));
        $series[] = array("date" => $d, "counts" => isset($map[$d]) ? $map[$d] : new stdClass());
    }
    return $series;
}

function admin_chart_data($conn)
{
    $events = admin_daily_series($conn, "SELECT DATE(created_at) AS d, event_type AS k, COUNT(*) AS total FROM analytics_events WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) GROUP BY d, k");
    $signups = admin_daily_series($conn, "SELECT DATE(created_at) AS d, 'signups' AS k, COUNT(*) AS total FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) GROUP BY d");
    $roles = array();
    $result = $conn->query("SELECT role, COUNT(*) AS total FROM users GROUP BY role ORDER BY total DESC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $roles[] = array("label" => $row["role"], "total" => (int) $row["total"]);
        }
    }
    $categories = array();
    $result = $conn->query("SELECT category, COUNT(*) AS total FROM components WHERE is_active = 1 GROUP BY category ORDER BY total DESC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = array("label" => $row["category"], "total" => (int) $row["total"]);
        }
    }
    return array("events_daily" => $events, "signups_daily" => $signups, "roles" => $roles, "categories" => $categories);
}

// ——— Dashboard overview ———
if ($action === "overview") {
    $overview = array(
        "total_users" => admin_scalar($conn_admin, "SELECT COUNT(*) FROM users"),
        "new_users_7d" => admin_scalar($conn_admin, "SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"),
        "banned_users" => admin_scalar($conn_admin, "SELECT COUNT(*) FROM users WHERE status = 'banned'"),
        "moderators" => admin_scalar($conn_admin, "SELECT COUNT(*) FROM users WHERE role IN ('moderator','verified')"),
        "total_components" => admin_scalar($conn_admin, "SELECT COUNT(*) FROM components"),
        "active_components" => admin_scalar($conn_admin, "SELECT COUNT(*) FROM components WHERE is_active = 1"),
        "inactive_components" => admin_scalar($conn_admin, "SELECT COUNT(*) FROM components WHERE is_active = 0"),
        "rules_enabled" => admin_scalar($conn_admin, "SELECT COUNT(*) FROM compatibility_rules WHERE is_enabled = 1"),
        "rules_total" => admin_scalar($conn_admin, "SELECT COUNT(*) FROM compatibility_rules"),
        "saved_builds" => admin_scalar($conn_admin, "SELECT COUNT(*) FROM user_saved_builds"),
        "communities" => admin_scalar($conn_admin, "SELECT COUNT(*) FROM communities"),
        "posts" => admin_scalar($conn_admin, "SELECT COUNT(*) FROM community_posts"),
        "messages" => admin_scalar($conn_admin, "SELECT COUNT(*) FROM community_messages"),
        "open_reports" => admin_scalar($conn_admin, "SELECT COUNT(*) FROM moderation_reports WHERE status = 'open'"),
        "build_checks_today" => admin_scalar($conn_admin, "SELECT COUNT(*) FROM analytics_events WHERE event_type = 'build_check' AND created_at >= CURDATE()"),
        "build_checks_7d" => admin_scalar($conn_admin, "SELECT COUNT(*) FROM analytics_events WHERE event_type = 'build_check' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"),
        "errors_today" => admin_scalar($conn_admin, "SELECT COUNT(*) FROM system_logs WHERE level = 'error' AND created_at >= CURDATE()"),
        "logs_total" => admin_scalar($conn_admin, "SELECT COUNT(*) FROM system_logs")
    );

    $recent_users = array();
    $result = $conn_admin->query("SELECT full_name, email, role, status, created_at FROM users ORDER BY id DESC LIMIT 6");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recent_users[] = $row;
        }
    }
    $overview["recent_users"] = $recent_users;

    $recent_logs = array();
    $result = $conn_admin->query("SELECT level, source, message, created_at FROM system_logs ORDER BY id DESC LIMIT 8");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recent_logs[] = $row;
        }
    }
    $overview["recent_logs"] = $recent_logs;
    $overview["charts"] = admin_chart_data($conn_admin);

    admin_respond(array("success" => true, "overview" => $overview));
}

// ——— Component catalog CRUD ———
if ($action === "component_list") {
    $search = trim(admin_field($body, "search", ""));
    $category = trim(admin_field($body, "category", ""));
    $sql = "SELECT id, category, brand, model, socket, tdp_watts, form_factor, price_php, price_usd, stock_status, specs_json, is_active, updated_at FROM components WHERE 1 = 1";
    $params = array();
    $types = "";
    if ($category !== "") {
        $sql .= " AND category = ?";
        $params[] = $category;
        $types .= "s";
    }
    if ($search !== "") {
        $sql .= " AND (brand LIKE ? OR model LIKE ?)";
        $like = "%" . $search . "%";
        $params[] = $like;
        $params[] = $like;
        $types .= "ss";
    }
    $sql .= " ORDER BY category ASC, brand ASC, model ASC LIMIT 500";
    $stmt = $conn_admin->prepare($sql);
    if (!$stmt) {
        admin_respond(array("success" => false, "error" => "Failed to load components"), 500);
    }
    if ($types !== "") {
        $bind = array_merge(array($types), $params);
        $refs = array();
        foreach ($bind as $key => $value) {
            $refs[$key] = &$bind[$key];
        }
        call_user_func_array(array($stmt, "bind_param"), $refs);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $items = array();
    while ($row = $result->fetch_assoc()) {
        $row["specs"] = json_decode($row["specs_json"], true);
        if (!is_array($row["specs"])) {
            $row["specs"] = array();
        }
        unset($row["specs_json"]);
        $row["is_active"] = (int) $row["is_active"];
        $items[] = $row;
    }
    $stmt->close();
    admin_respond(array("success" => true, "components" => $items));
}

if ($action === "component_save") {
    $component_id = (int) admin_field($body, "id", 0);
    $category = trim(admin_field($body, "category", ""));
    $brand = trim(admin_field($body, "brand", ""));
    $model = trim(admin_field($body, "model", ""));
    if ($category === "" || $brand === "" || $model === "") {
        admin_respond(array("success" => false, "error" => "Category, brand and model are required"), 400);
    }
    $allowed_categories = array("cpu", "motherboard", "gpu", "ram", "storage", "psu", "case", "cooler", "other");
    if (!in_array($category, $allowed_categories, true)) {
        admin_respond(array("success" => false, "error" => "Unknown component category"), 400);
    }
    $socket = trim(admin_field($body, "socket", ""));
    $socket = $socket === "" ? null : $socket;
    $tdp = admin_field($body, "tdp_watts", "");
    $tdp_watts = ($tdp === "" || $tdp === null) ? null : (int) $tdp;
    $form_factor = trim(admin_field($body, "form_factor", ""));
    $form_factor = $form_factor === "" ? null : $form_factor;
    $price_php = (float) admin_field($body, "price_php", 0);
    $price_usd = (float) admin_field($body, "price_usd", 0);
    $stock_status = trim(admin_field($body, "stock_status", "unknown"));
    $is_active = ((int) admin_field($body, "is_active", 1)) === 0 ? 0 : 1;
    $specs = admin_field($body, "specs", array());
    if (is_string($specs)) {
        $decoded = json_decode($specs, true);
        $specs = is_array($decoded) ? $decoded : array();
    }
    if (!is_array($specs)) {
        $specs = array();
    }
    $specs_json = json_encode($specs);

    if ($component_id > 0) {
        $stmt = $conn_admin->prepare("UPDATE components SET category = ?, brand = ?, model = ?, socket = ?, tdp_watts = ?, form_factor = ?, price_php = ?, price_usd = ?, stock_status = ?, specs_json = ?, is_active = ? WHERE id = ?");
        if (!$stmt) {
            admin_respond(array("success" => false, "error" => "Failed to update component"), 500);
        }
        $stmt->bind_param("ssssisddssii", $category, $brand, $model, $socket, $tdp_watts, $form_factor, $price_php, $price_usd, $stock_status, $specs_json, $is_active, $component_id);
        if (!$stmt->execute()) {
            $duplicate = $stmt->errno === 1062;
            $stmt->close();
            admin_respond(array("success" => false, "error" => $duplicate ? "Another part already uses that brand and model in this category" : "Component update failed"), $duplicate ? 409 : 500);
        }
        $stmt->close();
        admin_log($conn_admin, "info", "catalog", "Updated component #" . $component_id . " (" . $brand . " " . $model . ")");
        admin_respond(array("success" => true, "id" => $component_id, "message" => "Component updated"));
    }

    $stmt = $conn_admin->prepare("INSERT INTO components (category, brand, model, socket, tdp_watts, form_factor, price_php, price_usd, stock_status, specs_json, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        admin_respond(array("success" => false, "error" => "Failed to create component"), 500);
    }
    $stmt->bind_param("ssssisddssi", $category, $brand, $model, $socket, $tdp_watts, $form_factor, $price_php, $price_usd, $stock_status, $specs_json, $is_active);
    if (!$stmt->execute()) {
        $duplicate = $stmt->errno === 1062;
        $stmt->close();
        admin_respond(array("success" => false, "error" => $duplicate ? "A part with that brand and model already exists in this category" : "Component creation failed"), $duplicate ? 409 : 500);
    }
    $new_id = $stmt->insert_id;
    $stmt->close();
    admin_log($conn_admin, "info", "catalog", "Added component #" . $new_id . " (" . $brand . " " . $model . ")");
    admin_respond(array("success" => true, "id" => $new_id, "message" => "Component created"), 201);
}

if ($action === "component_delete") {
    $component_id = (int) admin_field($body, "id", 0);
    if ($component_id <= 0) {
        admin_respond(array("success" => false, "error" => "Missing component id"), 400);
    }
    $stmt = $conn_admin->prepare("DELETE FROM components WHERE id = ?");
    if (!$stmt) {
        admin_respond(array("success" => false, "error" => "Failed to delete component"), 500);
    }
    $stmt->bind_param("i", $component_id);
    $stmt->execute();
    $deleted = $stmt->affected_rows;
    $stmt->close();
    admin_log($conn_admin, "warning", "catalog", "Deleted component #" . $component_id);
    admin_respond(array("success" => true, "deleted" => $deleted > 0));
}

if ($action === "component_bulk_price") {
    $mode = trim(admin_field($body, "mode", "percent"));
    $category = trim(admin_field($body, "category", ""));
    $value = (float) admin_field($body, "value", 0);
    if ($value === 0.0) {
        admin_respond(array("success" => false, "error" => "Enter a non-zero adjustment value"), 400);
    }
    $where = "";
    $types = "";
    $params = array();
    if ($category !== "") {
        $where = " WHERE category = ?";
        $types .= "s";
        $params[] = $category;
    }
    if ($mode === "percent") {
        $sql = "UPDATE components SET price_php = ROUND(price_php * (1 + ? / 100), 2)" . $where;
        $types = "d" . $types;
        array_unshift($params, $value);
        $label = "bulk price change of " . $value . "%";
    } elseif ($mode === "multiplier") {
        $sql = "UPDATE components SET price_php = ROUND(price_php * ?, 2)" . $where;
        $types = "d" . $types;
        array_unshift($params, $value);
        $label = "bulk price multiplier x" . $value;
    } elseif ($mode === "usd_rate") {
        $sql = "UPDATE components SET price_php = ROUND(price_usd * ?, 2) WHERE price_usd > 0" . ($category !== "" ? " AND category = ?" : "");
        $types = "d" . $types;
        array_unshift($params, $value);
        $label = "USD rate recalculation at " . $value . " PHP/USD";
    } else {
        admin_respond(array("success" => false, "error" => "Unknown bulk pricing mode"), 400);
    }
    $stmt = $conn_admin->prepare($sql);
    if (!$stmt) {
        admin_respond(array("success" => false, "error" => "Failed to prepare bulk pricing"), 500);
    }
    $bind = array_merge(array($types), $params);
    $refs = array();
    foreach ($bind as $key => $val) {
        $refs[$key] = &$bind[$key];
    }
    call_user_func_array(array($stmt, "bind_param"), $refs);
    $ok = $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    if (!$ok) {
        admin_respond(array("success" => false, "error" => "Bulk pricing update failed"), 500);
    }
    admin_log($conn_admin, "info", "pricing", "Applied " . $label . ($category !== "" ? " to " . $category : " to all categories") . " (" . $affected . " parts)");
    admin_respond(array("success" => true, "affected" => $affected, "message" => "Updated " . $affected . " component prices"));
}

// ——— Compatibility rule maintenance ———
if ($action === "rules_list") {
    $result = $conn_admin->query("SELECT id, rule_key, name, category, severity, logic_summary, is_enabled, updated_at FROM compatibility_rules ORDER BY category ASC, name ASC");
    $rules = array();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row["is_enabled"] = (int) $row["is_enabled"];
            $rules[] = $row;
        }
    }
    admin_respond(array("success" => true, "rules" => $rules));
}

if ($action === "rule_save") {
    $rule_id = (int) admin_field($body, "id", 0);
    $rule_key = trim(admin_field($body, "rule_key", ""));
    $name = trim(admin_field($body, "name", ""));
    $category = trim(admin_field($body, "category", "general"));
    $severity = trim(admin_field($body, "severity", "warning"));
    $logic_summary = trim(admin_field($body, "logic_summary", ""));
    if ($rule_key === "" || $name === "") {
        admin_respond(array("success" => false, "error" => "Rule key and name are required"), 400);
    }
    $rule_key = preg_replace('/[^a-z0-9_]/', '_', strtolower($rule_key));
    if (!in_array($severity, array("info", "warning", "error"), true)) {
        $severity = "warning";
    }
    $is_enabled = ((int) admin_field($body, "is_enabled", 1)) === 0 ? 0 : 1;
    if ($rule_id > 0) {
        $stmt = $conn_admin->prepare("UPDATE compatibility_rules SET rule_key = ?, name = ?, category = ?, severity = ?, logic_summary = ?, is_enabled = ? WHERE id = ?");
        if (!$stmt) {
            admin_respond(array("success" => false, "error" => "Failed to update rule"), 500);
        }
        $stmt->bind_param("sssssii", $rule_key, $name, $category, $severity, $logic_summary, $is_enabled, $rule_id);
        $ok = $stmt->execute();
        $errno = $stmt->errno;
        $stmt->close();
        if (!$ok) {
            admin_respond(array("success" => false, "error" => $errno === 1062 ? "Another rule already uses that key" : "Rule update failed"), $errno === 1062 ? 409 : 500);
        }
        admin_log($conn_admin, "info", "rules", "Updated rule " . $rule_key);
        admin_respond(array("success" => true, "id" => $rule_id, "message" => "Rule updated"));
    }
    $stmt = $conn_admin->prepare("INSERT INTO compatibility_rules (rule_key, name, category, severity, logic_summary, is_enabled) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        admin_respond(array("success" => false, "error" => "Failed to create rule"), 500);
    }
    $stmt->bind_param("sssssi", $rule_key, $name, $category, $severity, $logic_summary, $is_enabled);
    if (!$stmt->execute()) {
        $duplicate = $stmt->errno === 1062;
        $stmt->close();
        admin_respond(array("success" => false, "error" => $duplicate ? "A rule with that key already exists" : "Rule creation failed"), $duplicate ? 409 : 500);
    }
    $new_id = $stmt->insert_id;
    $stmt->close();
    admin_log($conn_admin, "info", "rules", "Created rule " . $rule_key);
    admin_respond(array("success" => true, "id" => $new_id, "message" => "Rule created"), 201);
}

if ($action === "rule_toggle") {
    $rule_id = (int) admin_field($body, "id", 0);
    $stmt = $conn_admin->prepare("UPDATE compatibility_rules SET is_enabled = 1 - is_enabled WHERE id = ?");
    if (!$stmt) {
        admin_respond(array("success" => false, "error" => "Failed to toggle rule"), 500);
    }
    $stmt->bind_param("i", $rule_id);
    $stmt->execute();
    $stmt->close();
    admin_respond(array("success" => true));
}

if ($action === "rule_delete") {
    $rule_id = (int) admin_field($body, "id", 0);
    $stmt = $conn_admin->prepare("DELETE FROM compatibility_rules WHERE id = ?");
    if (!$stmt) {
        admin_respond(array("success" => false, "error" => "Failed to delete rule"), 500);
    }
    $stmt->bind_param("i", $rule_id);
    $stmt->execute();
    $deleted = $stmt->affected_rows;
    $stmt->close();
    admin_log($conn_admin, "warning", "rules", "Deleted rule #" . $rule_id);
    admin_respond(array("success" => true, "deleted" => $deleted > 0));
}

// ——— User & role management ———
if ($action === "user_list") {
    $search = trim(admin_field($body, "search", ""));
    $role_filter = trim(admin_field($body, "role", ""));
    $sql = "SELECT u.id, u.full_name, u.email, u.role, u.status, u.created_at, u.last_login_at,
        (SELECT COUNT(*) FROM user_saved_builds b WHERE b.user_email = u.email) AS saved_builds
        FROM users u WHERE 1 = 1";
    $types = "";
    $params = array();
    if ($search !== "") {
        $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ?)";
        $like = "%" . $search . "%";
        $params[] = $like;
        $params[] = $like;
        $types .= "ss";
    }
    if ($role_filter !== "") {
        $sql .= " AND u.role = ?";
        $params[] = $role_filter;
        $types .= "s";
    }
    $sql .= " ORDER BY u.id DESC LIMIT 300";
    $stmt = $conn_admin->prepare($sql);
    if (!$stmt) {
        admin_respond(array("success" => false, "error" => "Failed to load users"), 500);
    }
    if ($types !== "") {
        $bind = array_merge(array($types), $params);
        $refs = array();
        foreach ($bind as $key => $value) {
            $refs[$key] = &$bind[$key];
        }
        call_user_func_array(array($stmt, "bind_param"), $refs);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $users = array();
    while ($row = $result->fetch_assoc()) {
        $row["saved_builds"] = (int) $row["saved_builds"];
        $users[] = $row;
    }
    $stmt->close();
    admin_respond(array("success" => true, "users" => $users));
}

if ($action === "user_set_role") {
    $target = strtolower(trim(admin_field($body, "user_email", "")));
    $role = trim(admin_field($body, "role", "user"));
    $allowed_roles = array("user", "verified", "moderator", "admin");
    if (!filter_var($target, FILTER_VALIDATE_EMAIL) || !in_array($role, $allowed_roles, true)) {
        admin_respond(array("success" => false, "error" => "Invalid user or role"), 400);
    }
    if ($target === $email && $role !== "admin") {
        admin_respond(array("success" => false, "error" => "You cannot remove your own admin access"), 400);
    }
    $stmt = $conn_admin->prepare("UPDATE users SET role = ? WHERE email = ?");
    if (!$stmt) {
        admin_respond(array("success" => false, "error" => "Failed to update role"), 500);
    }
    $stmt->bind_param("ss", $role, $target);
    $stmt->execute();
    $stmt->close();
    admin_log($conn_admin, "warning", "users", "Set role of " . $target . " to " . $role);
    admin_respond(array("success" => true, "message" => "Role updated"));
}

if ($action === "user_set_status") {
    $target = strtolower(trim(admin_field($body, "user_email", "")));
    $status = trim(admin_field($body, "status", "active"));
    if (!filter_var($target, FILTER_VALIDATE_EMAIL) || !in_array($status, array("active", "banned"), true)) {
        admin_respond(array("success" => false, "error" => "Invalid user or status"), 400);
    }
    if ($target === $email) {
        admin_respond(array("success" => false, "error" => "You cannot change your own account status"), 400);
    }
    $stmt = $conn_admin->prepare("UPDATE users SET status = ? WHERE email = ?");
    if (!$stmt) {
        admin_respond(array("success" => false, "error" => "Failed to update status"), 500);
    }
    $stmt->bind_param("ss", $status, $target);
    $stmt->execute();
    $stmt->close();
    if ($status === "banned") {
        $revoke = $conn_admin->prepare("DELETE FROM auth_tokens WHERE user_email = ?");
        if ($revoke) {
            $revoke->bind_param("s", $target);
            $revoke->execute();
            $revoke->close();
        }
    }
    admin_log($conn_admin, "warning", "users", ($status === "banned" ? "Banned " : "Reactivated ") . $target);
    admin_respond(array("success" => true, "message" => $status === "banned" ? "User banned and sessions revoked" : "User reactivated"));
}

if ($action === "user_delete") {
    $target = strtolower(trim(admin_field($body, "user_email", "")));
    if (!filter_var($target, FILTER_VALIDATE_EMAIL)) {
        admin_respond(array("success" => false, "error" => "Invalid user"), 400);
    }
    if ($target === $email) {
        admin_respond(array("success" => false, "error" => "You cannot delete your own account from here"), 400);
    }
    $conn_admin->autocommit(false);
    $cleanup = array(
        "DELETE FROM auth_tokens WHERE user_email = ?",
        "DELETE FROM user_saved_builds WHERE user_email = ?",
        "DELETE FROM private_messages WHERE sender_email = ? OR recipient_email = ?",
        "DELETE FROM community_messages WHERE sender_email = ?",
        "DELETE FROM community_posts WHERE user_email = ?",
        "DELETE FROM community_post_comments WHERE user_email = ?",
        "DELETE FROM users WHERE email = ?"
    );
    foreach ($cleanup as $sql) {
        $stmt = $conn_admin->prepare($sql);
        if (!$stmt) {
            $conn_admin->rollback();
            $conn_admin->autocommit(true);
            admin_respond(array("success" => false, "error" => "Failed to clean up account data"), 500);
        }
        if (substr_count($sql, "?") === 2) {
            $stmt->bind_param("ss", $target, $target);
        } else {
            $stmt->bind_param("s", $target);
        }
        if (!$stmt->execute()) {
            $stmt->close();
            $conn_admin->rollback();
            $conn_admin->autocommit(true);
            admin_respond(array("success" => false, "error" => "Account deletion failed"), 500);
        }
        $stmt->close();
    }
    $conn_admin->commit();
    $conn_admin->autocommit(true);
    admin_log($conn_admin, "warning", "users", "Deleted account " . $target);
    admin_respond(array("success" => true, "message" => "Account and associated content deleted"));
}

// ——— Community moderation ———
if ($action === "report_list") {
    $status_filter = trim(admin_field($body, "status", ""));
    $sql = "SELECT id, content_type, content_ref, community, reported_by, reason, status, resolved_by, resolved_at, created_at FROM moderation_reports";
    $types = "";
    $params = array();
    if ($status_filter !== "") {
        $sql .= " WHERE status = ?";
        $types = "s";
        $params[] = $status_filter;
    }
    $sql .= " ORDER BY id DESC LIMIT 200";
    $stmt = $conn_admin->prepare($sql);
    if (!$stmt) {
        admin_respond(array("success" => false, "error" => "Failed to load reports"), 500);
    }
    if ($types !== "") {
        $stmt->bind_param($types, $params[0]);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $reports = array();
    while ($row = $result->fetch_assoc()) {
        $reports[] = $row;
    }
    $stmt->close();
    admin_respond(array("success" => true, "reports" => $reports));
}

if ($action === "report_resolve") {
    $report_id = (int) admin_field($body, "id", 0);
    $status = trim(admin_field($body, "status", "resolved"));
    if (!in_array($status, array("resolved", "dismissed", "open"), true)) {
        admin_respond(array("success" => false, "error" => "Invalid report status"), 400);
    }
    $stmt = $conn_admin->prepare("UPDATE moderation_reports SET status = ?, resolved_by = ?, resolved_at = NOW() WHERE id = ?");
    if (!$stmt) {
        admin_respond(array("success" => false, "error" => "Failed to update report"), 500);
    }
    $stmt->bind_param("ssi", $status, $email, $report_id);
    $stmt->execute();
    $stmt->close();
    admin_log($conn_admin, "info", "moderation", "Report #" . $report_id . " marked " . $status);
    admin_respond(array("success" => true, "message" => "Report " . $status));
}

// Any signed-in user can flag content; used by the community UI.
if ($action === "report_create") {
    $content_type = trim(admin_field($body, "content_type", ""));
    $content_ref = trim((string) admin_field($body, "content_ref", ""));
    $community = trim(admin_field($body, "community", ""));
    $reason = trim(admin_field($body, "reason", ""));
    if ($content_type !== "community_post" || !ctype_digit($content_ref) || (int) $content_ref <= 0 || $community === "" || strlen($community) > 80 || $reason === "" || strlen($reason) > 255) {
        admin_respond(array("success" => false, "error" => "A valid community post and report reason are required (up to 255 characters)"), 400);
    }
    $post_id = (int) $content_ref;
    $post_check = $conn_admin->prepare("SELECT id FROM community_posts WHERE id = ? AND community = ? LIMIT 1");
    if (!$post_check) {
        admin_respond(array("success" => false, "error" => "Failed to verify the reported post"), 500);
    }
    $post_check->bind_param("is", $post_id, $community);
    if (!$post_check->execute()) {
        $post_check->close();
        admin_respond(array("success" => false, "error" => "Failed to verify the reported post"), 500);
    }
    $post_check->store_result();
    if ($post_check->num_rows !== 1) {
        $post_check->close();
        admin_respond(array("success" => false, "error" => "The reported post could not be found"), 404);
    }
    $post_check->close();
    $stmt = $conn_admin->prepare("INSERT INTO moderation_reports (content_type, content_ref, community, reported_by, reason) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
        admin_respond(array("success" => false, "error" => "Failed to submit report"), 500);
    }
    $stmt->bind_param("sssss", $content_type, $content_ref, $community, $email, $reason);
    if (!$stmt->execute()) {
        $stmt->close();
        admin_respond(array("success" => false, "error" => "Failed to submit report"), 500);
    }
    $stmt->close();
    admin_respond(array("success" => true, "message" => "Report submitted for review"), 201);
}

if ($action === "content_list") {
    $type = trim(admin_field($body, "type", "posts"));
    $community = trim(admin_field($body, "community", ""));
    if ($type === "messages") {
        $sql = "SELECT m.id, m.community, m.sender_email AS author, m.message AS body, m.created_at FROM community_messages m";
        $sql .= $community !== "" ? " WHERE m.community = ?" : "";
        $sql .= " ORDER BY m.id DESC LIMIT 100";
    } elseif ($type === "comments") {
        $sql = "SELECT c.id, p.community, c.user_email AS author, c.comment AS body, c.created_at FROM community_post_comments c INNER JOIN community_posts p ON p.id = c.post_id";
        $sql .= $community !== "" ? " WHERE p.community = ?" : "";
        $sql .= " ORDER BY c.id DESC LIMIT 100";
    } else {
        $sql = "SELECT p.id, p.community, p.user_email AS author, p.body, p.image_path, p.created_at FROM community_posts p";
        $sql .= $community !== "" ? " WHERE p.community = ?" : "";
        $sql .= " ORDER BY p.id DESC LIMIT 100";
    }
    $stmt = $conn_admin->prepare($sql);
    if (!$stmt) {
        admin_respond(array("success" => false, "error" => "Failed to load content"), 500);
    }
    if ($community !== "") {
        $stmt->bind_param("s", $community);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $items = array();
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();
    admin_respond(array("success" => true, "items" => $items));
}

if ($action === "content_delete") {
    $type = trim(admin_field($body, "type", "posts"));
    $item_id = (int) admin_field($body, "id", 0);
    if ($item_id <= 0) {
        admin_respond(array("success" => false, "error" => "Missing content id"), 400);
    }
    if ($type === "messages") {
        $sql = "DELETE FROM community_messages WHERE id = ?";
    } elseif ($type === "comments") {
        $sql = "DELETE FROM community_post_comments WHERE id = ?";
    } else {
        $sql = "DELETE FROM community_posts WHERE id = ?";
    }
    $stmt = $conn_admin->prepare($sql);
    if (!$stmt) {
        admin_respond(array("success" => false, "error" => "Failed to delete content"), 500);
    }
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $deleted = $stmt->affected_rows;
    $stmt->close();
    if ($type === "posts" && $deleted > 0) {
        $cleanup = $conn_admin->prepare("DELETE FROM community_post_comments WHERE post_id = ?");
        if ($cleanup) {
            $cleanup->bind_param("i", $item_id);
            $cleanup->execute();
            $cleanup->close();
        }
    }
    admin_log($conn_admin, "warning", "moderation", "Removed " . $type . " item #" . $item_id);
    admin_respond(array("success" => true, "deleted" => $deleted > 0));
}

// ——— Pricing feed manager ———
if ($action === "pricing_list") {
    $result = $conn_admin->query("SELECT id, region, currency, multiplier, notes, updated_at FROM pricing_regions ORDER BY multiplier ASC");
    $regions = array();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $regions[] = $row;
        }
    }
    admin_respond(array("success" => true, "regions" => $regions));
}

if ($action === "pricing_save") {
    $region_id = (int) admin_field($body, "id", 0);
    $region = trim(admin_field($body, "region", ""));
    $currency = trim(admin_field($body, "currency", "PHP"));
    $multiplier = (float) admin_field($body, "multiplier", 1);
    $notes = trim(admin_field($body, "notes", ""));
    if ($region === "") {
        admin_respond(array("success" => false, "error" => "Region name is required"), 400);
    }
    if ($multiplier <= 0) {
        admin_respond(array("success" => false, "error" => "Multiplier must be greater than zero"), 400);
    }
    if ($region_id > 0) {
        $stmt = $conn_admin->prepare("UPDATE pricing_regions SET region = ?, currency = ?, multiplier = ?, notes = ? WHERE id = ?");
        if (!$stmt) {
            admin_respond(array("success" => false, "error" => "Failed to update region"), 500);
        }
        $stmt->bind_param("ssdsi", $region, $currency, $multiplier, $notes, $region_id);
        $ok = $stmt->execute();
        $errno = $stmt->errno;
        $stmt->close();
        if (!$ok) {
            admin_respond(array("success" => false, "error" => $errno === 1062 ? "That region already exists" : "Region update failed"), $errno === 1062 ? 409 : 500);
        }
        admin_log($conn_admin, "info", "pricing", "Updated pricing region " . $region);
        admin_respond(array("success" => true, "id" => $region_id, "message" => "Region updated"));
    }
    $stmt = $conn_admin->prepare("INSERT INTO pricing_regions (region, currency, multiplier, notes) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        admin_respond(array("success" => false, "error" => "Failed to create region"), 500);
    }
    $stmt->bind_param("ssds", $region, $currency, $multiplier, $notes);
    if (!$stmt->execute()) {
        $duplicate = $stmt->errno === 1062;
        $stmt->close();
        admin_respond(array("success" => false, "error" => $duplicate ? "That region already exists" : "Region creation failed"), $duplicate ? 409 : 500);
    }
    $new_id = $stmt->insert_id;
    $stmt->close();
    admin_log($conn_admin, "info", "pricing", "Added pricing region " . $region);
    admin_respond(array("success" => true, "id" => $new_id, "message" => "Region created"), 201);
}

if ($action === "pricing_delete") {
    $region_id = (int) admin_field($body, "id", 0);
    $stmt = $conn_admin->prepare("DELETE FROM pricing_regions WHERE id = ?");
    if (!$stmt) {
        admin_respond(array("success" => false, "error" => "Failed to delete region"), 500);
    }
    $stmt->bind_param("i", $region_id);
    $stmt->execute();
    $deleted = $stmt->affected_rows;
    $stmt->close();
    admin_respond(array("success" => true, "deleted" => $deleted > 0));
}

// Applies a saved region multiplier across the whole catalog (localized market sync).
if ($action === "pricing_apply_region") {
    $region_id = (int) admin_field($body, "id", 0);
    $stmt = $conn_admin->prepare("SELECT region, multiplier FROM pricing_regions WHERE id = ? LIMIT 1");
    if (!$stmt) {
        admin_respond(array("success" => false, "error" => "Failed to load region"), 500);
    }
    $stmt->bind_param("i", $region_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $region = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    if (!$region) {
        admin_respond(array("success" => false, "error" => "Region not found"), 404);
    }
    $multiplier = (float) $region["multiplier"];
    // price_php holds the baseline; re-derive from price_usd so applying twice is idempotent.
    $apply = $conn_admin->prepare("UPDATE components SET price_php = ROUND(price_usd * ? * ?, 2) WHERE price_usd > 0");
    if (!$apply) {
        admin_respond(array("success" => false, "error" => "Failed to apply pricing"), 500);
    }
    $base_rate = 55.5;
    $apply->bind_param("dd", $base_rate, $multiplier);
    $apply->execute();
    $affected = $apply->affected_rows;
    $apply->close();
    admin_log($conn_admin, "info", "pricing", "Applied region " . $region["region"] . " (x" . $multiplier . ") to " . $affected . " parts");
    admin_respond(array("success" => true, "affected" => $affected, "message" => "Applied " . $region["region"] . " pricing to " . $affected . " parts"));
}

// ——— Analytics & monitoring ———
if ($action === "analytics") {
    $summary = array();
    $summary["events_by_type"] = array();
    $result = $conn_admin->query("SELECT event_type, COUNT(*) AS total FROM analytics_events WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY event_type ORDER BY total DESC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $summary["events_by_type"][] = $row;
        }
    }
    $summary["top_searches"] = array();
    $result = $conn_admin->query("SELECT detail, COUNT(*) AS total FROM analytics_events WHERE event_type = 'search' AND detail <> '' GROUP BY detail ORDER BY total DESC LIMIT 10");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $summary["top_searches"][] = $row;
        }
    }
    $summary["trending_builds"] = array();
    $result = $conn_admin->query("SELECT detail, COUNT(*) AS total FROM analytics_events WHERE event_type = 'build_save' AND detail <> '' GROUP BY detail ORDER BY total DESC LIMIT 10");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $summary["trending_builds"][] = $row;
        }
    }
    $summary["failed_queries"] = array();
    $result = $conn_admin->query("SELECT detail, COUNT(*) AS total FROM analytics_events WHERE event_type = 'compat_fail' AND detail <> '' GROUP BY detail ORDER BY total DESC LIMIT 10");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $summary["failed_queries"][] = $row;
        }
    }
    $summary["peak_hours"] = array();
    $result = $conn_admin->query("SELECT HOUR(created_at) AS hour_bucket, COUNT(*) AS total FROM analytics_events WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY hour_bucket ORDER BY hour_bucket ASC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $summary["peak_hours"][] = array("hour" => (int) $row["hour_bucket"], "total" => (int) $row["total"]);
        }
    }
    $summary["charts"] = admin_chart_data($conn_admin);
    $summary["api_health"] = array(
        "events_24h" => admin_scalar($conn_admin, "SELECT COUNT(*) FROM analytics_events WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"),
        "errors_24h" => admin_scalar($conn_admin, "SELECT COUNT(*) FROM system_logs WHERE level = 'error' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"),
        "warnings_24h" => admin_scalar($conn_admin, "SELECT COUNT(*) FROM system_logs WHERE level = 'warning' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"),
        "active_sessions" => admin_scalar($conn_admin, "SELECT COUNT(DISTINCT user_email) FROM auth_tokens WHERE expires_at > NOW()")
    );
    admin_respond(array("success" => true, "summary" => $summary));
}

if ($action === "logs_list") {
    $level = trim(admin_field($body, "level", ""));
    $limit = (int) admin_field($body, "limit", 100);
    if ($limit <= 0 || $limit > 500) {
        $limit = 100;
    }
    $sql = "SELECT id, level, source, message, created_at FROM system_logs";
    if ($level !== "") {
        $sql .= " WHERE level = ?";
    }
    $sql .= " ORDER BY id DESC LIMIT " . $limit;
    $stmt = $conn_admin->prepare($sql);
    if (!$stmt) {
        admin_respond(array("success" => false, "error" => "Failed to load logs"), 500);
    }
    if ($level !== "") {
        $stmt->bind_param("s", $level);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $logs = array();
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
    $stmt->close();
    admin_respond(array("success" => true, "logs" => $logs));
}

if ($action === "log_add") {
    $level = trim(admin_field($body, "level", "info"));
    $source = trim(admin_field($body, "source", "admin"));
    $message = trim(admin_field($body, "message", ""));
    if ($message === "") {
        admin_respond(array("success" => false, "error" => "Log message is required"), 400);
    }
    admin_log($conn_admin, $level, $source, $message);
    admin_respond(array("success" => true, "message" => "Log entry recorded"), 201);
}

if ($action === "logs_clear") {
    $older_than_days = (int) admin_field($body, "older_than_days", 0);
    if ($older_than_days > 0) {
        $stmt = $conn_admin->prepare("DELETE FROM system_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        if ($stmt) {
            $stmt->bind_param("i", $older_than_days);
            $stmt->execute();
            $removed = $stmt->affected_rows;
            $stmt->close();
        } else {
            $removed = 0;
        }
    } else {
        $conn_admin->query("DELETE FROM system_logs");
        $removed = $conn_admin->affected_rows;
    }
    admin_respond(array("success" => true, "removed" => (int) $removed));
}

admin_respond(array("success" => false, "error" => "Unsupported action"), 400);
