<?php
include "database.php";

if (isset($_GET["chat_api"]) && $_SERVER["REQUEST_METHOD"] === "POST") {
  header("Content-Type: application/json; charset=UTF-8");
  $content_type = isset($_SERVER["CONTENT_TYPE"]) ? $_SERVER["CONTENT_TYPE"] : "";
  $is_multipart = stripos($content_type, "multipart/form-data") !== false;
  $body = $is_multipart ? $_POST : json_decode(file_get_contents("php://input"), true);
  if (!is_array($body)) {
    $body = array();
  }
  $email = strtolower(trim(isset($body["email"]) ? $body["email"] : ""));
  $token = trim(isset($body["auth_token"]) ? $body["auth_token"] : "");
  $action = isset($body["action"]) ? $body["action"] : "";
  $community = trim(isset($body["community"]) ? $body["community"] : "");
  $default_communities = array(
    array("name" => "Recommendation Builds", "description" => "Share and discover recommended PC builds"),
    array("name" => "Part Pricing", "description" => "Deals and store prices"),
    array("name" => "Troubleshooting", "description" => "Fix PC problems together"),
    array("name" => "Build Showcase", "description" => "Share your finished build")
  );
  $reply = function ($payload, $status = 200) {
    http_response_code($status);
    echo json_encode($payload);
    exit;
  };

  if (!$conn || !is_object($conn)) {
    $reply(array("success" => false, "error" => "The account database is unavailable. Check config.php database settings and make sure MySQL is running."), 503);
  }

  if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $token === "") {
    $reply(array("success" => false, "error" => "Please sign in again"), 401);
  }
  $token_hash = hash("sha256", $token);
  $auth = $conn->prepare("SELECT user_email FROM auth_tokens WHERE user_email = ? AND token_hash = ? AND expires_at > NOW() LIMIT 1");
  if (!$auth) {
    $reply(array("success" => false, "error" => "Failed to verify account session"), 500);
  }
  $auth->bind_param("ss", $email, $token_hash);
  $auth->execute();
  $auth->store_result();
  if ($auth->num_rows !== 1) {
    $auth->close();
    $reply(array("success" => false, "error" => "Your session expired. Please log in again"), 401);
  }
  $auth->close();

  $create_communities_sql = "CREATE TABLE IF NOT EXISTS communities (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(80) NOT NULL,
    description VARCHAR(255) NOT NULL,
    created_by VARCHAR(190) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_community_name (name)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
  if (!$conn->query($create_communities_sql)) {
    $reply(array("success" => false, "error" => "Failed to prepare communities table"), 500);
  }
  $seed = $conn->prepare("INSERT IGNORE INTO communities (name, description, created_by) VALUES (?, ?, ?)");
  if ($seed) {
    foreach ($default_communities as $default) {
      $system_email = "system@corecraft.local";
      $seed->bind_param("sss", $default["name"], $default["description"], $system_email);
      $seed->execute();
    }
    $seed->close();
  }

  if ($action === "dm_user_search") {
    $search = trim(isset($body["query"]) ? $body["query"] : "");
    if (strlen($search) < 2) $reply(array("success" => true, "users" => array()));
    $pattern = "%" . $search . "%";
    $stmt = $conn->prepare("SELECT full_name, email FROM users WHERE email <> ? AND (full_name LIKE ? OR email LIKE ?) ORDER BY full_name ASC LIMIT 10");
    if (!$stmt) $reply(array("success" => false, "error" => "Failed to search users"), 500);
    $stmt->bind_param("sss", $email, $pattern, $pattern);
    $stmt->execute();
    $result = $stmt->get_result();
    $users = array();
    while ($row = $result->fetch_assoc()) $users[] = $row;
    $stmt->close();
    $reply(array("success" => true, "users" => $users));
  }

  $create_dm_sql = "CREATE TABLE IF NOT EXISTS private_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sender_email VARCHAR(190) NOT NULL,
    recipient_email VARCHAR(190) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_dm_sender (sender_email, created_at),
    KEY idx_dm_recipient (recipient_email, created_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
  if (!$conn->query($create_dm_sql)) $reply(array("success" => false, "error" => "Failed to prepare private messages"), 500);

  if ($action === "dm_conversations") {
    $stmt = $conn->prepare("SELECT CASE WHEN sender_email = ? THEN recipient_email ELSE sender_email END AS peer_email, MAX(created_at) AS last_message FROM private_messages WHERE sender_email = ? OR recipient_email = ? GROUP BY peer_email ORDER BY last_message DESC LIMIT 100");
    if (!$stmt) $reply(array("success" => false, "error" => "Failed to load conversations"), 500);
    $stmt->bind_param("sss", $email, $email, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $conversations = array();
    while ($row = $result->fetch_assoc()) {
      $peer_email = $row["peer_email"];
      $user_stmt = $conn->prepare("SELECT full_name FROM users WHERE email = ? LIMIT 1");
      $peer_name = $peer_email;
      if ($user_stmt) {
        $user_stmt->bind_param("s", $peer_email);
        $user_stmt->execute();
        $user_stmt->bind_result($found_name);
        if ($user_stmt->fetch()) $peer_name = $found_name;
        $user_stmt->close();
      }
      $conversations[] = array("email" => $peer_email, "name" => $peer_name, "last_message" => $row["last_message"]);
    }
    $stmt->close();
    $reply(array("success" => true, "conversations" => $conversations));
  }

  if ($action === "dm_messages" || $action === "dm_send") {
    $peer = strtolower(trim(isset($body["peer_email"]) ? $body["peer_email"] : ""));
    if (!filter_var($peer, FILTER_VALIDATE_EMAIL) || $peer === $email) $reply(array("success" => false, "error" => "Choose another registered user"), 400);
    $exists = $conn->prepare("SELECT email FROM users WHERE email = ? LIMIT 1");
    if (!$exists) $reply(array("success" => false, "error" => "Failed to verify recipient"), 500);
    $exists->bind_param("s", $peer);
    $exists->execute();
    $exists->store_result();
    if ($exists->num_rows !== 1) {
      $exists->close();
      $reply(array("success" => false, "error" => "That user account was not found"), 404);
    }
    $exists->close();
    if ($action === "dm_send") {
      $message = trim(isset($body["message"]) ? $body["message"] : "");
      if ($message === "" || strlen($message) > 2000) $reply(array("success" => false, "error" => "Message must be between 1 and 2000 characters"), 400);
      $stmt = $conn->prepare("INSERT INTO private_messages (sender_email, recipient_email, message) VALUES (?, ?, ?)");
      if (!$stmt) $reply(array("success" => false, "error" => "Failed to prepare private message"), 500);
      $stmt->bind_param("sss", $email, $peer, $message);
      if (!$stmt->execute()) {
        $stmt->close();
        $reply(array("success" => false, "error" => "Private message could not be sent"), 500);
      }
      $stmt->close();
      $reply(array("success" => true));
    }
    $stmt = $conn->prepare("SELECT sender_email, recipient_email, message, created_at FROM private_messages WHERE (sender_email = ? AND recipient_email = ?) OR (sender_email = ? AND recipient_email = ?) ORDER BY id DESC LIMIT 100");
    if (!$stmt) $reply(array("success" => false, "error" => "Failed to load private messages"), 500);
    $stmt->bind_param("ssss", $email, $peer, $peer, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $messages = array();
    while ($row = $result->fetch_assoc()) $messages[] = $row;
    $stmt->close();
    $reply(array("success" => true, "messages" => array_reverse($messages)));
  }

  if ($action === "community_list") {
    $result = $conn->query("SELECT name, description, created_by FROM communities ORDER BY created_at ASC, name ASC");
    $communities = array();
    if ($result) {
      while ($row = $result->fetch_assoc()) $communities[] = $row;
    }
    $reply(array("success" => true, "communities" => $communities));
  }

  if ($action === "community_create") {
    $name = trim(isset($body["name"]) ? $body["name"] : "");
    $description = trim(isset($body["description"]) ? $body["description"] : "");
    if ($name === "" || strlen($name) > 80 || $description === "" || strlen($description) > 255) {
      $reply(array("success" => false, "error" => "Community name and description are required. Name: 1-80 characters; description: 1-255 characters."), 400);
    }
    $stmt = $conn->prepare("INSERT INTO communities (name, description, created_by) VALUES (?, ?, ?)");
    if (!$stmt) $reply(array("success" => false, "error" => "Failed to prepare community creation"), 500);
    $stmt->bind_param("sss", $name, $description, $email);
    if (!$stmt->execute()) {
      $duplicate = $stmt->errno === 1062;
      $stmt->close();
      $reply(array("success" => false, "error" => $duplicate ? "A community with that name already exists" : "Community could not be created"), $duplicate ? 409 : 500);
    }
    $stmt->close();
    $reply(array("success" => true, "community" => array("name" => $name, "description" => $description, "created_by" => $email)), 201);
  }

  if ($action === "community_delete") {
    $conn->autocommit(false);
    $delete_community = $conn->prepare("DELETE FROM communities WHERE name = ? AND created_by = ? AND created_by <> 'system@corecraft.local'");
    if (!$delete_community) {
      $conn->rollback();
      $reply(array("success" => false, "error" => "Failed to prepare community deletion"), 500);
    }
    $delete_community->bind_param("ss", $community, $email);
    if (!$delete_community->execute()) {
      $delete_community->close();
      $conn->rollback();
      $reply(array("success" => false, "error" => "Community deletion failed"), 500);
    }
    $deleted = $delete_community->affected_rows;
    $delete_community->close();
    if ($deleted !== 1) {
      $conn->rollback();
      $reply(array("success" => false, "error" => "Only the community creator can delete this community"), 403);
    }

    $cleanup_queries = array(
      "DELETE FROM community_post_comments WHERE post_id IN (SELECT id FROM community_posts WHERE community = ?)",
      "DELETE FROM community_post_likes WHERE post_id IN (SELECT id FROM community_posts WHERE community = ?)",
      "DELETE FROM community_posts WHERE community = ?",
      "DELETE FROM community_messages WHERE community = ?"
    );
    foreach ($cleanup_queries as $cleanup_sql) {
      $cleanup = $conn->prepare($cleanup_sql);
      if (!$cleanup) {
        $conn->rollback();
        $reply(array("success" => false, "error" => "Failed to clean up community content"), 500);
      }
      $cleanup->bind_param("s", $community);
      if (!$cleanup->execute()) {
        $cleanup->close();
        $conn->rollback();
        $reply(array("success" => false, "error" => "Failed to clean up community content"), 500);
      }
      $cleanup->close();
    }
    $conn->commit();
    $conn->autocommit(true);
    $reply(array("success" => true));
  }

  $community_check = $conn->prepare("SELECT name FROM communities WHERE name = ? LIMIT 1");
  if (!$community_check) $reply(array("success" => false, "error" => "Failed to validate community"), 500);
  $community_check->bind_param("s", $community);
  $community_check->execute();
  $community_check->store_result();
  if ($community_check->num_rows !== 1) {
    $community_check->close();
    $reply(array("success" => false, "error" => "Community does not exist"), 404);
  }
  $community_check->close();

  $create_community_sql = "CREATE TABLE IF NOT EXISTS community_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    community VARCHAR(80) NOT NULL,
    sender_email VARCHAR(190) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_community_messages (community, created_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
  if (!$conn->query($create_community_sql)) {
    $reply(array("success" => false, "error" => "Failed to prepare community messages table"), 500);
  }

  $conn->query("CREATE TABLE IF NOT EXISTS community_posts (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, community VARCHAR(80) NOT NULL, user_email VARCHAR(190) NOT NULL, body TEXT NOT NULL, image_path VARCHAR(255) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY idx_posts_community (community, created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8");
  $conn->query("CREATE TABLE IF NOT EXISTS community_post_likes (post_id BIGINT UNSIGNED NOT NULL, user_email VARCHAR(190) NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (post_id, user_email), KEY idx_post_likes_user (user_email)) ENGINE=InnoDB DEFAULT CHARSET=utf8");
  $conn->query("CREATE TABLE IF NOT EXISTS community_post_comments (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, post_id BIGINT UNSIGNED NOT NULL, user_email VARCHAR(190) NOT NULL, comment TEXT NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY idx_post_comments (post_id, created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8");

  if ($action === "post_create") {
    $post_body = trim(isset($body["message"]) ? $body["message"] : "");
    $image_path = null;
    if (isset($_FILES["image"]) && $_FILES["image"]["error"] !== UPLOAD_ERR_NO_FILE) {
      $file = $_FILES["image"];
      if ($file["error"] !== UPLOAD_ERR_OK || $file["size"] > 5 * 1024 * 1024) $reply(array("success" => false, "error" => "Image upload failed or exceeds the 5 MB limit"), 400);
      $image_info = @getimagesize($file["tmp_name"]);
      $mime = is_array($image_info) && isset($image_info["mime"]) ? $image_info["mime"] : "";
      $extensions = array("image/jpeg" => "jpg", "image/png" => "png", "image/gif" => "gif", "image/webp" => "webp");
      if (!isset($extensions[$mime])) $reply(array("success" => false, "error" => "Use a JPEG, PNG, GIF, or WebP image"), 400);
      $upload_dir = __DIR__ . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "community_images";
      if (!is_dir($upload_dir) && !@mkdir($upload_dir, 0755, true)) $reply(array("success" => false, "error" => "Image upload folder could not be created"), 500);
      $filename = sha1(uniqid("corecraft_", true) . mt_rand()) . "." . $extensions[$mime];
      if (!move_uploaded_file($file["tmp_name"], $upload_dir . DIRECTORY_SEPARATOR . $filename)) $reply(array("success" => false, "error" => "Could not save the uploaded image"), 500);
      $image_path = "uploads/community_images/" . $filename;
    }
    if ($post_body === "" && $image_path === null) $reply(array("success" => false, "error" => "Write a post or attach an image"), 400);
    if (strlen($post_body) > 2000) $reply(array("success" => false, "error" => "Posts are limited to 2000 characters"), 400);
    $stmt = $conn->prepare("INSERT INTO community_posts (community, user_email, body, image_path) VALUES (?, ?, ?, ?)");
    if (!$stmt) $reply(array("success" => false, "error" => "Failed to prepare post"), 500);
    $stmt->bind_param("ssss", $community, $email, $post_body, $image_path);
    if (!$stmt->execute()) {
      $stmt->close();
      $reply(array("success" => false, "error" => "Post could not be published"), 500);
    }
    $stmt->close();
    $reply(array("success" => true));
  }

  if ($action === "post_list") {
    $stmt = $conn->prepare("SELECT p.id, p.user_email, u.full_name AS sender_name, p.body, p.image_path, p.created_at, (SELECT COUNT(*) FROM community_post_likes l WHERE l.post_id = p.id) AS like_count, (SELECT COUNT(*) FROM community_post_likes l WHERE l.post_id = p.id AND l.user_email = ?) AS liked FROM community_posts p INNER JOIN users u ON u.email = p.user_email WHERE p.community = ? ORDER BY p.id DESC LIMIT 50");
    if (!$stmt) $reply(array("success" => false, "error" => "Failed to load posts"), 500);
    $stmt->bind_param("ss", $email, $community);
    $stmt->execute();
    $result = $stmt->get_result();
    $posts = array();
    while ($row = $result->fetch_assoc()) {
      $post_id = (int) $row["id"];
      $row["comments"] = array();
      $comments_stmt = $conn->prepare("SELECT c.user_email, u.full_name AS sender_name, c.comment, c.created_at FROM community_post_comments c INNER JOIN users u ON u.email = c.user_email WHERE c.post_id = ? ORDER BY c.id ASC LIMIT 100");
      if ($comments_stmt) {
        $comments_stmt->bind_param("i", $post_id);
        $comments_stmt->execute();
        $comments_result = $comments_stmt->get_result();
        while ($comment_row = $comments_result->fetch_assoc()) $row["comments"][] = $comment_row;
        $comments_stmt->close();
      }
      $posts[] = $row;
    }
    $stmt->close();
    $reply(array("success" => true, "posts" => $posts));
  }

  if ($action === "post_like" || $action === "post_comment") {
    $post_id = isset($body["post_id"]) ? (int) $body["post_id"] : 0;
    $post_check = $conn->prepare("SELECT id FROM community_posts WHERE id = ? AND community = ? LIMIT 1");
    if (!$post_check) $reply(array("success" => false, "error" => "Failed to verify post"), 500);
    $post_check->bind_param("is", $post_id, $community);
    $post_check->execute();
    $post_check->store_result();
    if ($post_check->num_rows !== 1) {
      $post_check->close();
      $reply(array("success" => false, "error" => "Post was not found in this community"), 404);
    }
    $post_check->close();
    if ($action === "post_like") {
      $check = $conn->prepare("SELECT post_id FROM community_post_likes WHERE post_id = ? AND user_email = ? LIMIT 1");
      $check->bind_param("is", $post_id, $email);
      $check->execute();
      $check->store_result();
      if ($check->num_rows > 0) {
        $check->close();
        $stmt = $conn->prepare("DELETE FROM community_post_likes WHERE post_id = ? AND user_email = ?");
        $liked = false;
      } else {
        $check->close();
        $stmt = $conn->prepare("INSERT IGNORE INTO community_post_likes (post_id, user_email) VALUES (?, ?)");
        $liked = true;
      }
      $stmt->bind_param("is", $post_id, $email);
      $stmt->execute();
      $stmt->close();
      $reply(array("success" => true, "liked" => $liked));
    }
    $comment = trim(isset($body["comment"]) ? $body["comment"] : "");
    if ($comment === "" || strlen($comment) > 1000) $reply(array("success" => false, "error" => "Comments must be between 1 and 1000 characters"), 400);
    $stmt = $conn->prepare("INSERT INTO community_post_comments (post_id, user_email, comment) VALUES (?, ?, ?)");
    if (!$stmt) $reply(array("success" => false, "error" => "Failed to prepare comment"), 500);
    $stmt->bind_param("iss", $post_id, $email, $comment);
    if (!$stmt->execute()) {
      $stmt->close();
      $reply(array("success" => false, "error" => "Comment could not be posted"), 500);
    }
    $stmt->close();
    $reply(array("success" => true));
  }

  // Remove custom communities that have had no messages for five days.
  $conn->query("DELETE communities FROM communities LEFT JOIN community_messages ON community_messages.community = communities.name AND community_messages.created_at >= DATE_SUB(NOW(), INTERVAL 5 DAY) LEFT JOIN community_posts ON community_posts.community = communities.name AND community_posts.created_at >= DATE_SUB(NOW(), INTERVAL 5 DAY) WHERE communities.created_by <> 'system@corecraft.local' AND communities.created_at < DATE_SUB(NOW(), INTERVAL 5 DAY) AND community_messages.id IS NULL AND community_posts.id IS NULL");

  $conn->query("DELETE FROM community_messages WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");

  if ($action === "community_messages") {
    $stmt = $conn->prepare("SELECT messages.sender_email, users.full_name AS sender_name, messages.message, messages.created_at FROM community_messages messages INNER JOIN users ON users.email = messages.sender_email WHERE messages.community = ? AND messages.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) ORDER BY messages.created_at ASC, messages.id ASC LIMIT 200");
    if (!$stmt) {
      $reply(array("success" => false, "error" => "Failed to load community messages"), 500);
    }
    $stmt->bind_param("s", $community);
    $stmt->execute();
    $result = $stmt->get_result();
    $messages = array();
    while ($row = $result->fetch_assoc()) {
      $messages[] = $row;
    }
    $stmt->close();
    $reply(array("success" => true, "messages" => $messages));
  }

  if ($action === "community_send") {
    $message = trim(isset($body["message"]) ? $body["message"] : "");
    if ($message === "" || strlen($message) > 2000) {
      $reply(array("success" => false, "error" => "Message must be between 1 and 2000 characters"), 400);
    }
    $stmt = $conn->prepare("INSERT INTO community_messages (community, sender_email, message) VALUES (?, ?, ?)");
    if (!$stmt) {
      $reply(array("success" => false, "error" => "Failed to prepare message"), 500);
    }
    $stmt->bind_param("sss", $community, $email, $message);
    if (!$stmt->execute()) {
      $stmt->close();
      $reply(array("success" => false, "error" => "Message could not be sent"), 500);
    }
    $stmt->close();
    $reply(array("success" => true));
  }
  $reply(array("success" => false, "error" => "Unsupported action"), 400);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>CoreCraft — Build Your Dream PC (1)</title>
  <script src="https://accounts.google.com/gsi/client" async defer></script>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="style.css?v=<?php echo filemtime('style.css'); ?>">
</head>
<body>
<!-- NAV -->
<nav>
  <a class="logo" onclick="showPage('home')">
    <div class="logo-icon"><svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="logoGrad" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#00e5ff"/><stop offset="100%" stop-color="#7c3aed"/></linearGradient></defs><circle cx="32" cy="32" r="26" fill="rgba(0,229,255,.08)"/><circle cx="32" cy="32" r="18" fill="rgba(255,255,255,.05)" stroke="white" stroke-width="1.6"/><path d="M32 6v8M32 50v8M6 32h8M50 32h8M16.97 16.97l5.66 5.66M41.37 41.37l5.66 5.66M16.97 47.03l5.66-5.66M41.37 22.63l5.66-5.66" stroke="url(#logoGrad)" stroke-width="1.8" stroke-linecap="round"/><rect x="22" y="22" width="20" height="20" rx="4" fill="rgba(0,229,255,.15)" stroke="white" stroke-width="1.6"/><path d="M28 28h8v8h-8z" fill="url(#logoGrad)" opacity=".7"/><path d="M32 24c-3 0-4 1.5-4 4s1.5 4 4 4 4-1.5 4-4-1-4-4-4z" stroke="white" stroke-width="1.6" fill="none" stroke-linecap="round"/><path d="M31 30h4" stroke="white" stroke-width="1.6" stroke-linecap="round"/></svg></div>
    <div class="logo-text">Core<span>Craft</span></div>
  </a>
  <ul>
    <li><a onclick="showPage('compatibility')">Compatibility Checker</a></li>
    <li><a onclick="showPage('build')">Build Recs</a></li>
    <li><a onclick="showPage('pricing')">Part Pricing</a></li>
    <li><a onclick="showPage('troubleshoot')">Troubleshoot</a></li>
    <li><a onclick="showPage('PHchat')">PH Community</a></li>
  </ul>
  <div class="nav-actions">
    <button class="btn-login" id="nav-login-btn" onclick="showPage('login')">👤 Login</button>
    <div class="user-dropdown" id="user-dropdown">
      <button class="user-pill" onclick="toggleDropdown()" id="user-pill">
        <div class="user-avatar" id="user-avatar">J</div>
        <span id="user-displayname">Juan</span> ▾
      </button>
      <div class="dropdown-menu hidden" id="dropdown-menu">
        <div class="dropdown-header">
          <div class="dropdown-name" id="dropdown-name">Juan Dela Cruz</div>
          <div class="dropdown-email" id="dropdown-email">juan@example.com</div>
        </div>
        <button class="dropdown-item" onclick="showPage('profile');closeDropdown()">👤 My Profile</button>
        <button class="dropdown-item logout" onclick="handleLogout()">🚪 Log Out</button>
      </div>
    </div>
    <button class="btn-start" onclick="showPage('compatibility')">Start Building</button>
  </div>
  <button class="hamburger" onclick="toggleMenu()" id="hamburger" aria-label="Menu">
    <span></span><span></span><span></span>
  </button>
</nav>
<!-- Mobile Menu -->
<div class="mobile-menu" id="mobile-menu">
  <a onclick="showPage('compatibility');closeMenu()">⚡ Compatibility Checker</a>
  <a onclick="showPage('build');closeMenu()">💡 Build Recommendations</a>
  <a onclick="showPage('pricing');closeMenu()">📍 Part Pricing by Location</a>
  <a onclick="showPage('troubleshoot');closeMenu()">🛠️ Troubleshooting</a>
  <a onclick="showPage('PHchat');closeMenu()">🌐 PH Community</a>
  <div class="mobile-menu-actions">
    <button class="btn-login" onclick="showPage('login');closeMenu()">👤 Login</button>
    <button class="btn-start" onclick="showPage('compatibility');closeMenu()">⚡ Start Building</button>
  </div>
</div>
<!-- ===== HOME ===== -->
<div id="page-home" class="page active">
  <div class="hero">
    <div class="badge" style="margin-bottom:28px;"><span class="pulse"></span> Built for Filipino PC Builders</div>
    <h1 class="hero-title">Build Your<br><span class="gradient">Dream PC</span><br>Without Mistakes</h1>
    <p class="hero-sub">CoreCraft checks compatibility, recommends builds, finds local prices, and helps troubleshoot — so beginners can build with confidence.</p>
  </div>
  <div class="section-wrap" style="padding-top:0">
    <div class="label-tag">5 Core Features</div>
    <div class="section-title">Everything You Need to Build</div>
    <div class="section-sub">Tools designed specifically for first-time PC builders in the Philippines.</div>
    <div class="grid-2">
      <div class="feature-card">
        <div class="feature-icon" style="background:rgba(0,229,255,.1)">🔍</div>
        <div class="feature-title">Compatibility Checker</div>
        <div class="feature-desc">Select your CPU, motherboard, RAM, GPU, storage, and PSU — instantly see if they all work together with a full compatibility report.</div>
      </div>
      <div class="feature-card">
        <div class="feature-icon" style="background:rgba(124,58,237,.12)">💡</div>
        <div class="feature-title">Build Recommendations</div>
        <div class="feature-desc">Choose your budget and use case — gaming, office, or students — and get curated build recommendations from database.</div>
      </div>
      <div class="feature-card">
        <div class="feature-icon" style="background:rgba(255,107,53,.1)">📍</div>
        <div class="feature-title">Pricing by Location</div>
        <div class="feature-desc">Find PC component shops and repair technicians by Philippine city. Shop locations do not confirm live stock or prices.</div>
      </div>
      <div class="feature-card">
        <div class="feature-icon" style="background:rgba(34,197,94,.1)">🛠️</div>
        <div class="feature-title">Basic Troubleshooting</div>
        <div class="feature-desc">AI-powered troubleshooting that diagnoses boot issues, crashes, and overheating, then gives step-by-step fixes instantly.</div>
      </div>
      <div class="feature-card" style="grid-column:1/-1;justify-self:center;max-width:520px">
        <div class="feature-icon" style="background:rgba(99,102,241,.12)">🌐</div>
        <div class="feature-title">PH Community</div>
        <div class="feature-desc">Chat with builders across the Philippines for parts advice, local pricing tips, and instant support from the community.</div>
      </div>
    </div>
  </div>
  <div class="cta-box">
    <div class="cta-title">Ready to Build<br>Your First PC?</div>
    <p style="color:var(--muted);margin-bottom:32px;">Join thousands of beginners who built successfully with CoreCraft.</p>
    <button class="btn-primary" onclick="showPage('compatibility')">⚡ Start Building</button>
  </div>
  <footer>
    <span>© 2026 CoreCraft · BS Information Technology Capstone</span>
    <div class="footer-links"><a href="about.html">About</a><a href="contact.html">Contact</a><a href="privacy.html">Privacy</a></div>
  </footer>
</div>
<!-- ===== COMPATIBILITY ===== -->
<div id="page-compatibility" class="page">
  <div class="section-wrap" style="padding-top:20px">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:28px">
      <button class="back-link" onclick="showPage('home')">← Back to Home</button>
    </div>
    <div style="margin-bottom:32px">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
        <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#00e5ff,#7c3aed);display:flex;align-items:center;justify-content:center;font-size:20px">🤖</div>
        <div style="font-family:'Syne',sans-serif;font-size:28px;font-weight:800">AI Compatibility Checker</div>
      </div>
      <p style="color:var(--muted);font-size:15px;line-height:1.6;margin-top:12px">Enter your PC parts below to check socket compatibility, RAM support, and power requirements. Any issues will include a clear explanation on the affected component cards.</p>
    </div>
    <div class="card" style="padding:32px;margin-bottom:24px">
        <div class="compat-grid" style="margin-bottom:20px">
        <div class="compat-field">
          <label class="compat-label">CPU</label>
          <div class="compat-row">
            <input type="text" id="compat-cpu" placeholder="e.g. Intel Core i5-13400F, AMD Ryzen 5 7600X" onkeydown="if(event.key==='Enter')runCompatCheck()" />
            <button type="button" class="btn-secondary compat-btn" onclick="openComponentSelector('cpu')">Pick CPU</button>
          </div>
        </div>
        <div class="compat-field">
          <label class="compat-label">Motherboard</label>
          <div class="compat-row">
            <input type="text" id="compat-mb" placeholder="e.g. MSI B760M Mortar, ASUS ROG Strix B650-A" onkeydown="if(event.key==='Enter')runCompatCheck()" />
            <button type="button" class="btn-secondary compat-btn" onclick="openComponentSelector('motherboard')">Pick MB</button>
          </div>
        </div>
        <div class="compat-field">
          <label class="compat-label">RAM</label>
          <div class="compat-row">
            <input type="text" id="compat-ram" placeholder="e.g. 16GB DDR5 5200MHz, 32GB DDR4 3600MHz" onkeydown="if(event.key==='Enter')runCompatCheck()" />
            <button type="button" class="btn-secondary compat-btn" onclick="openComponentSelector('ram')">Pick RAM</button>
          </div>
        </div>
        <div class="compat-field">
          <label class="compat-label">GPU</label>
          <div class="compat-row">
            <input type="text" id="compat-gpu" placeholder="e.g. RTX 4060, RX 7600, or None (iGPU)" onkeydown="if(event.key==='Enter')runCompatCheck()" />
            <button type="button" class="btn-secondary compat-btn" onclick="openComponentSelector('gpu')">Pick GPU</button>
          </div>
        </div>
        <div class="compat-field">
          <label class="compat-label">Storage</label>
          <div class="compat-row">
            <input type="text" id="compat-storage" placeholder="e.g. 1TB NVMe Gen4 SSD, 2TB HDD" onkeydown="if(event.key==='Enter')runCompatCheck()" />
            <button type="button" class="btn-secondary compat-btn" onclick="openComponentSelector('storage')">Pick Storage</button>
          </div>
        </div>
        <div class="compat-field">
          <label class="compat-label">PSU</label>
          <div class="compat-row">
            <input type="text" id="compat-psu" placeholder="e.g. Corsair 650W 80+ Gold, Seasonic 750W" onkeydown="if(event.key==='Enter')runCompatCheck()" />
            <button type="button" class="btn-secondary compat-btn" onclick="openComponentSelector('psu')">Pick PSU</button>
          </div>
        </div>
        <div class="compat-field" style="grid-column:1/-1">
          <label class="compat-label">PC Case (Optional)</label>
          <div class="compat-row">
            <input type="text" id="compat-case" placeholder="e.g. Lian Li Lancool 216, NZXT H510" onkeydown="if(event.key==='Enter')runCompatCheck()" />
          </div>
        </div>
        <div class="compat-field" style="grid-column:1/-1">
          <label class="compat-label">Price Store or City (Optional)</label>
          <input id="compat-price-store" class="chat-textarea" type="text" placeholder="e.g. Dynaquest Gilmore, PC Express Cebu, or Manila" />
          <div style="font-size:12px;color:var(--muted);margin-top:8px">Gemini will estimate for this store or location. Confirm the current listing before buying.</div>
        </div>
      </div>
      <div class="compat-actions">
        <button class="btn-gradient" onclick="runCompatCheck()">✓ Check Compatibility</button>
        <button class="btn-secondary" onclick="saveCurrentBuild()">💾 Save Build</button>
      </div>
      <div class="compat-header" style="margin-top:24px">
        <div class="compat-status" id="compat-status">✓ 0 of 6 Parts Compatible</div>
      </div>
      <div id="components-grid" class="grid-3" style="margin-top:24px;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));"></div>
      <div id="ai-compat-result" class="card" style="display:none;margin-top:24px;padding:24px"></div>
      <div id="budget-list" style="margin-top:20px"></div>
    </div>
  </div>
  <footer>
    <span>© 2026 CoreCraft · BS Information Technology Capstone</span>
    <div class="footer-links"><a href="about.html">About</a><a href="contact.html">Contact</a><a href="privacy.html">Privacy</a></div>
  </footer>
</div>
<!-- ===== BUILD RECOMMENDATIONS ===== -->
<div id="page-build" class="page">
  <div class="section-wrap" style="padding-top:20px">
    <button class="back-link" onclick="showPage('home')" style="margin-bottom:28px">← Back to Home</button>
    <div class="label-tag">Feature 2</div>
    <div class="section-title">Build Recommendations</div>
    <div class="section-sub">Database-powered build lists by category. Click a category to explore builds.</div>
    <div class="build-tabs">
      <button class="build-tab active" data-type="gaming" onclick="switchBuild('gaming',this)">🎮 Gaming</button>
      <button class="build-tab" data-type="office" onclick="switchBuild('office',this)">💼 Office</button>
      <button class="build-tab" data-type="students" onclick="switchBuild('students',this)">🎓 Students</button>
      <button class="build-tab" data-type="streaming" onclick="switchBuild('streaming',this)">📡 Streaming</button>
      <button class="build-tab" data-type="editing" onclick="switchBuild('editing',this)">🎬 Video Editing</button>
      <button class="build-tab" data-type="workstation" onclick="switchBuild('workstation',this)">🖥️ Workstation</button>
      <button class="build-tab" data-type="home" onclick="switchBuild('home',this)">🏠 Home/Office</button>
    </div>
    <div class="budget-filter card">
      <div class="budget-filter-copy"><strong>Find a build within your budget</strong><span>Recommendations start at ₱20,000.</span></div>
      <label for="build-budget">Maximum budget</label>
      <div class="budget-input-wrap"><span>₱</span><input id="build-budget" type="number" min="20000" step="1000" oninput="renderBuilds(getActiveBuildType())" /></div>
      <button type="button" class="btn-secondary" onclick="document.getElementById('build-budget').value='';renderBuilds(getActiveBuildType())">Clear</button>
    </div>
    <div id="build-content"></div>
    <!-- User-contributed build ideas removed per request -->
  </div>
  <footer>
    <span>© 2026 CoreCraft · BS Information Technology Capstone</span>
    <div class="footer-links"><a href="about.html">About</a><a href="contact.html">Contact</a><a href="privacy.html">Privacy</a></div>
  </footer>
</div>
<!-- ===== PRICING AI ===== -->
<div id="page-pricing" class="page">
  <div class="section-wrap" style="padding-top:20px">
    <button class="back-link" onclick="showPage('home')" style="margin-bottom:28px">← Back to Home</button>
    <div class="label-tag">Feature 3 · AI Powered</div>
    <div class="section-title">Component Pricing by Location</div>
    <div class="section-sub">Find computer-parts shops across the Philippines, then ask Gemini to interpret stock information you provide.</div>
    <div class="pricing-map-layout">
      <div class="map-panel">
        <div class="map-toolbar">
          <div>
            <div class="map-panel-title">PC component technicians</div>
            <div class="map-panel-sub">Only PC repair and computer-service technician listings inside the Philippines are shown. Narrow the search by city if needed.</div>
          </div>
          <div class="map-search-row">
            <input id="shop-search" class="chat-textarea" type="text" placeholder="Optional city or area (e.g. Manila, Cebu)" onkeydown="if(event.key==='Enter')searchPhilippinesShops()" />
            <button class="btn-secondary map-search-btn" type="button" onclick="searchPhilippinesShops()">Find technicians</button>
          </div>
        </div>
        <div id="philippines-map" class="philippines-map" aria-label="Map of PC component repair technicians in the Philippines"></div>
        <div class="map-legend"><span class="technician-legend-dot" aria-hidden="true"></span><span>PC component technician</span><span class="map-legend-note">OpenStreetMap listing</span></div>
        <div id="map-status" class="map-status">Loading Leaflet map of the Philippines...</div>
        <div id="shop-results" class="shop-results" aria-live="polite"></div>
      </div>
      <div class="stock-panel card">
        <div class="map-panel-title">Gemini stock assistant</div>
        <p class="map-panel-sub">Paste a shop link, listing, or stock message. Gemini will summarize it and mark anything it cannot verify.</p>
        <label class="compat-label" for="stock-part">Part requested</label>
        <input id="stock-part" class="stock-input" type="text" placeholder="e.g. RTX 4060 8GB" />
        <label class="compat-label" for="stock-location">Preferred city or shop</label>
        <input id="stock-location" class="stock-input" type="text" placeholder="e.g. Gilmore, Quezon City" />
        <label class="compat-label" for="stock-source">Stock information or shop link</label>
        <textarea id="stock-source" class="stock-input stock-source" rows="5" placeholder="Paste the seller's current listing, message, or URL here..."></textarea>
        <button id="stock-check-btn" class="btn-gradient" type="button" onclick="checkPartStock()">Ask Gemini to check stock</button>
        <div id="stock-result" class="stock-result" aria-live="polite"></div>
      </div>
    </div>
    <div class="ai-page-wrap">
      <div class="chat-wrap">
        <div class="chat-messages" id="pricing-messages"></div>
        <div class="quick-bar">
          <div class="quick-label">Quick Questions:</div>
          <div class="quick-btns">
            <button class="quick-btn" onclick="setInput('pricing','RTX 4060 price in Manila')">RTX 4060 in Manila</button>
            <button class="quick-btn" onclick="setInput('pricing','Ryzen 5 5600 price Cebu')">Ryzen 5 5600 in Cebu</button>
            <button class="quick-btn" onclick="setInput('pricing','16GB DDR4 RAM price Davao')">RAM price in Davao</button>
            <button class="quick-btn" onclick="setInput('pricing','Best GPU deals near me')">Best GPU deals</button>
            <button class="quick-btn" onclick="setInput('pricing','Budget gaming PC parts price list')">Budget parts list</button>
          </div>
        </div>
        <div class="chat-input-wrap">
          <div class="chat-row" style="flex-direction:column;align-items:stretch">
            <input id="pricing-location" class="chat-textarea" type="text" placeholder="Your shopping location (e.g. Manila, Cebu, Davao)" />
            <div class="chat-row" style="margin-top:10px">
              <textarea id="pricing-input" class="chat-textarea" rows="2" placeholder="Ask about component prices... e.g. 'RTX 4060 price'" onkeydown="handleKey(event,'pricing')"></textarea>
              <button class="send-btn" id="pricing-send" onclick="sendChat('pricing')">Send</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <footer>
    <span>© 2026 CoreCraft · BS Information Technology Capstone</span>
    <div class="footer-links"><a href="about.html">About</a><a href="contact.html">Contact</a><a href="privacy.html">Privacy</a></div>
  </footer>
</div>
<!-- ===== TROUBLESHOOT AI ===== -->
<div id="page-troubleshoot" class="page">
  <div class="section-wrap" style="padding-top:20px">
    <button class="back-link" onclick="showPage('home')" style="margin-bottom:28px">← Back to Home</button>
    <div class="label-tag">Feature 4 · AI Powered</div>
    <div class="section-title">Basic Troubleshooting</div>
    <div class="section-sub">Tell the bot your PC problem and get step-by-step repair guidance to fix it.</div>
    <div class="ai-page-wrap">
      <div class="chat-wrap">
        <div class="chat-messages" id="trouble-messages"></div>
        <div class="quick-bar">
          <div class="quick-label">Basic Troubleshooting:</div>
          <div class="quick-btns">
            <button class="quick-btn" onclick="setInput('trouble','My PC won\'t turn on at all')">PC won't turn on</button>
            <button class="quick-btn" onclick="setInput('trouble','No display or black screen on boot')">No display / black screen</button>
            <button class="quick-btn" onclick="setInput('trouble','PC keeps restarting randomly')">Random restarts</button>
            <button class="quick-btn" onclick="setInput('trouble','PC is overheating and shutting down')">Overheating</button>
            <button class="quick-btn" onclick="setInput('trouble','Blue screen of death BSOD error')">BSOD blue screen</button>
            <button class="quick-btn" onclick="setInput('trouble','PC is very slow and lagging')">PC slow / lagging</button>
          </div>
        </div>
        <div class="chat-input-wrap">
          <div class="chat-row">
            <textarea id="trouble-input" class="chat-textarea" rows="2" placeholder="Describe your PC problem and I will help you fix it..." onkeydown="handleKey(event,'trouble')"></textarea>
            <button class="send-btn" id="trouble-send" onclick="sendChat('trouble')">Send</button>
          </div>
        </div>
      </div>
      <div class="ai-info-cards">
        <div class="ai-info-card"><div class="ai-info-emoji">🔍</div><div class="ai-info-num">Diagnosis</div><div class="ai-info-sub">Identify root cause</div></div>
        <div class="ai-info-card"><div class="ai-info-emoji">🛠️</div><div class="ai-info-num">Step-by-step</div><div class="ai-info-sub">Clear fix instructions</div></div>
        <div class="ai-info-card"><div class="ai-info-emoji">⚡</div><div class="ai-info-num">Instant</div><div class="ai-info-sub">AI answers fast</div></div>
      </div>
    </div>
  </div>
  <footer>
    <span>© 2026 CoreCraft · BS Information Technology Capstone</span>
    <div class="footer-links"><a href="about.html">About</a><a href="contact.html">Contact</a><a href="privacy.html">Privacy</a></div>
  </footer>
</div>
<!-- ===== PHCHAT ===== -->
<div id="page-PHchat" class="page">
  <div class="section-wrap ph-community-shell" style="padding-top:20px">
    <button class="back-link" onclick="showPage('home')" style="margin-bottom:28px">← Back to Home</button>
    <div class="label-tag">Feature 5 · Community</div>
    <div class="section-title">PH Community</div>
    <div class="section-sub">Share builds and concerns, connect with PC builders, and message members across the Philippines.</div>
    <div class="phchat-tabs" role="tablist">
      <button class="phchat-tab active" type="button" role="tab" aria-selected="true" onclick="switchPHChatView('posts',this)">Posts</button>
      <button class="phchat-tab phchat-icon-tab" type="button" role="tab" aria-label="Private messages" aria-selected="false" title="Private messages" onclick="switchPHChatView('messages',this)">
        <svg viewBox="0 0 24 24" width="19" height="19" aria-hidden="true" focusable="false"><path d="M3.5 6.75A2.75 2.75 0 0 1 6.25 4h11.5a2.75 2.75 0 0 1 2.75 2.75v10.5A2.75 2.75 0 0 1 17.75 20h-11.5A2.75 2.75 0 0 1 3.5 17.25V6.75Zm2.1-.9 6.4 5.1 6.4-5.1H5.6Zm12.9 1.9-5.88 4.69a1 1 0 0 1-1.24 0L5.5 7.75v9.5c0 .41.34.75.75.75h11.5c.41 0 .75-.34.75-.75v-9.5Z" fill="currentColor"/></svg>
      </button>
    </div>
    <section id="phchat-posts-view" class="phchat-view active">
      <div class="phchat-feed-layout">
        <aside class="card phchat-community-picker">
          <div class="phchat-sidebar-heading">Your communities</div>
          <div id="PHchat-feed-communities" class="phchat-feed-communities"></div>
          <button type="button" class="phchat-create-community" onclick="openCreateCommunity()"><span aria-hidden="true">＋</span> Create community</button>
          <label class="sr-only" for="feed-community">Post destination</label>
          <select id="feed-community" class="phchat-hidden-select" aria-label="Post destination" onchange="loadCommunityPosts()"></select>
        </aside>
        <div class="phchat-feed-main">
          <div class="phchat-feed-toolbar"><strong>Community posts</strong><span class="phchat-sort-label">Latest</span></div>
          <div class="card phchat-composer">
            <textarea id="community-post-text" class="chat-textarea" rows="3" maxlength="2000" placeholder="Share a PC concern, build, or question with this community..."></textarea>
            <div class="phchat-composer-actions"><label class="phchat-image-picker">Add photo <input id="community-post-image" type="file" accept="image/jpeg,image/png,image/gif,image/webp" onchange="previewCommunityImage(this)"></label><span id="community-image-name" class="phchat-muted">Images up to 5 MB</span><button id="community-post-submit" class="send-btn" type="button" onclick="publishCommunityPost()">Post</button></div>
            <div id="community-post-preview" class="phchat-image-preview"></div>
            <div id="community-post-status" class="phchat-status" role="status"></div>
          </div>
          <div id="community-post-list" class="phchat-post-list" aria-live="polite"></div>
        </div>
      </div>
    </section>
    <section id="phchat-messages-view" class="phchat-view">
      <div class="phchat-dm-layout">
        <aside class="card phchat-dm-sidebar">
          <strong>Find a member</strong>
          <input id="dm-user-search" class="chat-textarea" type="search" placeholder="Search name or email" oninput="searchDMUsers()">
          <div id="dm-search-results" class="dm-user-results"></div>
          <strong class="dm-conversations-heading">Conversations</strong>
          <div id="dm-conversation-list" class="dm-user-results"></div>
        </aside>
        <div class="chat-wrap phchat-dm-panel">
          <div class="chat-header phchat-dm-header"><strong id="dm-active-name">Select a member to message</strong></div>
          <div id="dm-messages" class="chat-messages" aria-live="polite"></div>
          <div class="chat-input-wrap"><div class="chat-row"><textarea id="dm-message-input" class="chat-textarea" rows="2" maxlength="2000" placeholder="Write a private message..." onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendPrivateMessage()}"></textarea><button id="dm-send-button" class="send-btn" type="button" onclick="sendPrivateMessage()" disabled>Send</button></div></div>
        </div>
      </div>
    </section>
  </div>
  <footer>
    <span>© 2026 CoreCraft · BS Information Technology Capstone</span>
    <div class="footer-links"><a href="about.html">About</a><a href="contact.html">Contact</a><a href="privacy.html">Privacy</a></div>
  </footer>
</div>
<!-- ===== LOGIN ===== -->
<div id="page-login" class="page">
  <div class="login-bg-blob blob1"></div>
  <div class="login-bg-blob blob2"></div>
  <div class="login-grid"></div>
  <div class="login-card-wrap">
    <div class="login-brand">
      <div class="login-logo"><span class="login-logo-icon"><svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="loginGrad" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#00e5ff"/><stop offset="100%" stop-color="#7c3aed"/></linearGradient></defs><circle cx="32" cy="32" r="26" fill="rgba(0,229,255,.08)"/><circle cx="32" cy="32" r="18" fill="rgba(255,255,255,.05)" stroke="white" stroke-width="1.6"/><path d="M32 6v8M32 50v8M6 32h8M50 32h8M16.97 16.97l5.66 5.66M41.37 41.37l5.66 5.66M16.97 47.03l5.66-5.66M41.37 22.63l5.66-5.66" stroke="url(#loginGrad)" stroke-width="1.8" stroke-linecap="round"/><rect x="22" y="22" width="20" height="20" rx="4" fill="rgba(0,229,255,.15)" stroke="white" stroke-width="1.6"/><path d="M28 28h8v8h-8z" fill="url(#loginGrad)" opacity=".7"/><path d="M32 24c-3 0-4 1.5-4 4s1.5 4 4 4 4-1.5 4-4-1-4-4-4z" stroke="white" stroke-width="1.6" fill="none" stroke-linecap="round"/><path d="M31 30h4" stroke="white" stroke-width="1.6" stroke-linecap="round"/></svg></span><span class="login-logo-text">CoreCraft</span></div>
      <p class="login-tagline" id="login-tagline">Welcome back, PC Builder!</p>
    </div>
    <div class="login-box">
      <div class="login-tabs">
        <button class="login-tab active-tab" id="tab-login" onclick="switchTab('login')">Login</button>
        <button class="login-tab" id="tab-signup" onclick="switchTab('signup')">Sign Up</button>
      </div>
      <div class="login-form">
        <div class="hidden-field form-group" id="field-name">
          <label class="form-label">Full Name</label>
          <div class="input-wrap"><span class="input-icon">👤</span><input class="form-input signup-focus" id="name-input" type="text" autocomplete="name" placeholder="Juan Dela Cruz"/></div>
        </div>
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <div class="input-wrap"><span class="input-icon">✉️</span><input class="form-input" id="email-input" type="email" autocomplete="email" placeholder="juan@example.com"/></div>
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <div class="input-wrap">
            <span class="input-icon">🔒</span>
            <input class="form-input" type="password" id="pw-input" autocomplete="current-password" placeholder="••••••••"/>
            <button class="pw-toggle" onclick="togglePw()" id="pw-eye" type="button">👁</button>
          </div>
        </div>
        <div class="hidden-field form-group" id="field-confirm">
          <label class="form-label">Confirm Password</label>
          <div class="input-wrap"><span class="input-icon">🔒</span><input class="form-input signup-focus" id="confirm-input" type="password" autocomplete="new-password" placeholder="••••••••"/></div>
        </div>
        <div class="remember-row" id="remember-row">
          <label class="remember-label"><input id="remember-input" type="checkbox" style="accent-color:var(--cyan)"/> Remember me</label>
          <button class="forgot-btn" type="button">Forgot password?</button>
        </div>
        <button class="submit-btn submit-login" id="submit-btn" onclick="handleLogin()" type="button">Login</button>
        <div class="login-status" id="login-status" role="status" aria-live="polite"></div>
        <div class="security-note">Passwords are salted and hashed in your browser before being stored.</div>
        <div class="divider"><div class="divider-line"></div><span class="divider-text">or continue with</span><div class="divider-line"></div></div>
        <div class="social-grid google-social-grid">
          <div class="google-signin-slot" id="google-signin-button" aria-label="Continue with Google"></div>
        </div>
      </div>
    </div>
    <div class="login-footer"><button class="back-link" onclick="showPage('home')">← Back to Home</button></div>
  </div>
</div>
<!-- ===== USER PROFILE ===== -->
<div id="page-profile" class="page">
  <div class="section-wrap" style="padding-top:24px;max-width:860px">
    <button class="back-link" onclick="showPage('home')" style="margin-bottom:28px">← Back to Home</button>
    <!-- Profile Header Card -->
    <div style="background:linear-gradient(135deg,rgba(124,58,237,.15),rgba(0,229,255,.08));border:1px solid rgba(0,229,255,.2);border-radius:24px;padding:36px;margin-bottom:24px;display:flex;align-items:center;gap:28px;flex-wrap:wrap">
      <div id="profile-avatar-big" style="width:88px;height:88px;border-radius:50%;background:linear-gradient(135deg,var(--purple),var(--cyan));display:flex;align-items:center;justify-content:center;font-size:34px;font-weight:800;color:#fff;flex-shrink:0;box-shadow:0 0 0 4px rgba(0,229,255,.2)">J</div>
      <div style="flex:1;min-width:200px">
        <div id="profile-name-big" style="font-family:'Syne',sans-serif;font-size:26px;font-weight:800;letter-spacing:-.5px;margin-bottom:4px">Juan Dela Cruz</div>
        <div id="profile-email-big" style="color:var(--muted);font-size:14px;margin-bottom:12px">juan@example.com</div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <span class="badge"><span class="pulse"></span> Active Builder</span>
          <span style="display:inline-flex;align-items:center;gap:6px;background:rgba(124,58,237,.12);border:1px solid rgba(124,58,237,.3);color:#a78bfa;padding:5px 14px;border-radius:999px;font-size:13px;font-weight:500">🏅 Member since 2026</span>
        </div>
      </div>
      <button onclick="toggleEditMode()" id="edit-profile-btn" style="background:transparent;border:1px solid var(--border);color:var(--text);padding:10px 20px;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;transition:all .2s;font-family:'DM Sans',sans-serif;white-space:nowrap" onmouseover="this.style.borderColor='var(--cyan)';this.style.color='var(--cyan)'" onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--text)'">✏️ Edit Profile</button>
    </div>
    <button class="btn-secondary" onclick="openLoadBuildsModal()" style="width:100%;padding:20px;margin-bottom:24px;font-size:17px;font-weight:700">📂 Saved Builds</button>
    <div class="grid-2" style="margin-bottom:24px">
      <!-- Account Info -->
      <div class="card" style="padding:28px" id="info-view-panel">
        <div style="font-family:'Syne',sans-serif;font-size:16px;font-weight:700;margin-bottom:20px;display:flex;align-items:center;gap:8px">👤 Account Info</div>
        <div style="display:flex;flex-direction:column;gap:14px">
          <div>
            <div style="font-size:12px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px">Full Name</div>
            <div id="view-name" style="font-size:15px;font-weight:500">Juan Dela Cruz</div>
          </div>
          <div>
            <div style="font-size:12px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px">Email</div>
            <div id="view-email" style="font-size:15px;font-weight:500">juan@example.com</div>
          </div>
        </div>
      </div>
      <!-- Edit Form (hidden by default) -->
      <div class="card" style="padding:28px;display:none" id="info-edit-panel">
        <div style="font-family:'Syne',sans-serif;font-size:16px;font-weight:700;margin-bottom:20px">✏️ Edit Profile</div>
        <div style="display:flex;flex-direction:column;gap:14px">
          <div>
            <label style="font-size:12px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:6px">Full Name</label>
            <input id="edit-name" class="chat-textarea" style="width:100%;resize:none;border-radius:8px;padding:10px 14px" placeholder="Your name"/>
          </div>
          <div>
            <label style="font-size:12px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:6px">Email</label>
            <input id="edit-email" class="chat-textarea" type="email" style="width:100%;resize:none;border-radius:8px;padding:10px 14px" placeholder="your@email.com"/>
          </div>
          <div style="display:flex;gap:10px;margin-top:4px">
            <button onclick="saveProfile()" style="flex:1;background:var(--cyan);color:#000;border:none;padding:11px;border-radius:8px;font-weight:700;font-size:14px;cursor:pointer;font-family:'DM Sans',sans-serif">Save Changes</button>
            <button onclick="toggleEditMode()" style="flex:1;background:transparent;color:var(--muted);border:1px solid var(--border);padding:11px;border-radius:8px;font-weight:600;font-size:14px;cursor:pointer;font-family:'DM Sans',sans-serif">Cancel</button>
          </div>
        </div>
      </div>
    </div>
    <!-- Recent Activity -->
    <div class="card" style="padding:28px;margin-bottom:24px">
      <div style="font-family:'Syne',sans-serif;font-size:16px;font-weight:700;margin-bottom:20px">🕒 Recent Activity</div>
      <div id="recent-activity-list" style="display:flex;flex-direction:column;gap:0"></div>
    </div>
    <!-- Danger Zone -->
    <div style="background:rgba(248,113,113,.05);border:1px solid rgba(248,113,113,.2);border-radius:16px;padding:24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px">
      <div>
        <div style="font-size:15px;font-weight:700;color:#f87171;margin-bottom:4px">Log Out</div>
        <div style="font-size:13px;color:var(--muted)">Sign out of your CoreCraft account on this device.</div>
      </div>
      <button onclick="handleLogout()" style="background:rgba(248,113,113,.12);border:1px solid rgba(248,113,113,.3);color:#f87171;padding:10px 22px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .2s" onmouseover="this.style.background='rgba(248,113,113,.22)'" onmouseout="this.style.background='rgba(248,113,113,.12)'">🚪 Log Out</button>
    </div>
  </div>
  <footer>
    <span>© 2026 CoreCraft · BS Information Technology Capstone</span>
    <div class="footer-links"><a href="about.html">About</a><a href="contact.html">Contact</a><a href="privacy.html">Privacy</a></div>
  </footer>
</div>
<!-- Compatibility Modal -->
<div class="modal-overlay" id="compat-modal">
  <div class="modal-box">
    <div class="modal-title">🔍 Compatibility Report</div>
    <div id="modal-content"></div>
    <button class="modal-close" onclick="closeModal()">Close</button>
  </div>
</div>
<div class="modal-overlay" id="component-modal">
  <div class="modal-box">
    <div class="modal-title" id="component-modal-title">Select Component</div>
    <div id="component-list" style="max-height:400px;overflow-y:auto;margin:20px 0;"></div>
    <div style="display:flex;gap:10px;">
      <button class="modal-close" style="flex:1" onclick="closeComponentModal()">Cancel</button>
      <button class="modal-close" style="flex:1;background:var(--cyan);color:#000;border-color:var(--cyan)" onclick="selectComponent()">Select</button>
    </div>
  </div>
</div>
<div class="modal-overlay" id="build-idea-modal">
  <div class="modal-box">
    <div class="modal-title">💡 Add a Build Idea</div>
    <div style="display:flex;flex-direction:column;gap:14px">
      <input id="idea-title" class="chat-textarea" type="text" placeholder="Build name e.g. 1080p Gaming" />
      <select id="idea-category" class="chat-textarea" style="max-width:260px">
        <option value="gaming">🎮 Gaming</option>
        <option value="office">💼 Office</option>
        <option value="workstation">🖥️ Workstation</option>
        <option value="other">🔖 Other</option>
      </select>
      <div class="idea-components-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <input id="idea-cpu" class="chat-textarea" type="text" placeholder="CPU (optional) e.g. Ryzen 5 5600X" />
        <input id="idea-motherboard" class="chat-textarea" type="text" placeholder="Motherboard (optional) e.g. B550-F" />
        <input id="idea-ram" class="chat-textarea" type="text" placeholder="RAM (optional) e.g. 16GB DDR4 3200" />
        <input id="idea-gpu" class="chat-textarea" type="text" placeholder="GPU (optional) e.g. RTX 4060" />
        <input id="idea-storage" class="chat-textarea" type="text" placeholder="Storage (optional) e.g. 1TB NVMe" />
        <input id="idea-psu" class="chat-textarea" type="text" placeholder="PSU (optional) e.g. 650W 80+ Gold" />
        <input id="idea-case" class="chat-textarea" type="text" placeholder="PC Case (optional) e.g. Lian Li Lancool 216" />
      </div>
      <textarea id="idea-description" class="chat-textarea" rows="4" placeholder="Describe your build idea and goals..."></textarea>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <button class="btn-gradient" style="flex:1;min-width:140px" onclick="saveBuildIdea()">Send</button>
        <button class="btn-secondary" style="flex:1;min-width:140px" onclick="closeBuildIdeaModal()">Cancel</button>
      </div>
    </div>
  </div>
</div>
<div class="modal-overlay" id="saved-builds-modal">
  <div class="modal-box">
    <div class="modal-title">📂 Saved Builds</div>
    <div id="saved-builds-modal-list" style="max-height:400px;overflow-y:auto;margin:20px 0;"></div>
    <button class="modal-close" style="width:100%" onclick="closeSavedBuildsModal()">Close</button>
  </div>
</div>
<div class="modal-overlay" id="create-community-modal">
  <div class="modal-box">
    <div class="modal-title">💬 Create a community</div>
    <p style="color:var(--muted);font-size:13px;line-height:1.6;margin-bottom:18px">Start a room for a shared PC issue, upgrade, or build topic.</p>
    <div style="display:flex;flex-direction:column;gap:12px">
      <label class="compat-label" for="community-name-input">Community name</label>
      <input id="community-name-input" class="chat-textarea" type="text" maxlength="80" placeholder="e.g. AM4 Upgrade Help" />
      <label class="compat-label" for="community-description-input">What is this community about?</label>
      <textarea id="community-description-input" class="chat-textarea" rows="4" maxlength="255" placeholder="e.g. Help each other troubleshoot AM4 builds"></textarea>
      <div id="create-community-status" style="font-size:13px;color:#fca5a5;min-height:18px"></div>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <button class="btn-gradient" style="flex:1;min-width:140px" type="button" onclick="submitCreateCommunity()">Create community</button>
        <button class="btn-secondary" style="flex:1;min-width:140px" type="button" onclick="closeCreateCommunity()">Cancel</button>
      </div>
    </div>
  </div>
</div>
<div class="modal-overlay" id="delete-community-modal" role="dialog" aria-modal="true" aria-labelledby="delete-community-title">
  <div class="modal-box">
    <div class="modal-title" id="delete-community-title">Delete community?</div>
    <p style="color:var(--muted);line-height:1.6">Are you sure you want to delete <strong id="delete-community-name" style="color:var(--text)"></strong>? Its posts, likes, comments, and messages will also be permanently deleted.</p>
    <div id="delete-community-status" role="status" style="min-height:18px;margin-top:12px;color:#fca5a5;font-size:13px"></div>
    <div style="display:flex;gap:10px;margin-top:16px">
      <button type="button" class="btn-secondary" style="flex:1" onclick="closeDeleteCommunityModal()">Cancel</button>
      <button type="button" id="confirm-delete-community-button" style="flex:1;padding:12px;border:1px solid #ef4444;border-radius:8px;background:#ef4444;color:#fff;font-weight:700;cursor:pointer" onclick="confirmDeletePHCommunity()">Delete community</button>
    </div>
  </div>
</div>
  <script>window.CORECRAFT_GOOGLE_CLIENT_ID=<?php echo json_encode(CORECRAFT_GOOGLE_CLIENT_ID); ?>;</script>
  <script src="script.js?v=<?php echo filemtime('script.js'); ?>"></script>
</body>
</html>