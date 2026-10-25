<?php
// Chat, DM, community and post API. Loaded by index.php (requests to index.php?chat_api).

if (isset($_GET["chat_api"]) && $_SERVER["REQUEST_METHOD"] === "POST") {
  // Return TIMESTAMP columns in UTC; the browser formats them in Manila time.
  if (isset($conn) && $conn) $conn->query("SET time_zone = '+00:00'");
  $contains_foul_language = function ($text) {
    $text = strtolower((string) $text);
    $text = strtr($text, array("0" => "o", "1" => "i", "3" => "e", "4" => "a", "5" => "s", "@" => "a", "$" => "s"));
    $normalized = preg_replace('/[^a-z]+/', ' ', $text);
    $blocked_terms = array(
      "ass", "asshole", "bastard", "bitch", "bullshit", "crap", "cunt", "damn", "dick", "dumbass",
      "fag", "faggot", "fuck", "fucker", "fucking", "motherfucker", "piss", "prick", "shit", "slut", "whore",
      "bisakol", "bisaya baho", "bobo", "gago", "gagu", "hinayupak", "kantot", "leche", "lintik", "pakyu", "pota", "potang ina", "potangina", "potangina mo", "potanginamo",
      "puta", "putang ina", "putangina", "putangina mo", "putanginamo", "tang ina", "tangina", "tangina mo", "tanginamo", "tarantado", "tanga", "ulol", "yawa",
      "racism", "racist", "racial slur", "racial hate", "white supremacy", "white supremacist", "white power", "race superiority", "inferior race", "go back to your country", "hate crime",
      "chink", "coon", "kike", "nigger", "nigga", "spic", "wetback", "raghead", "towelhead",
      "threat", "threaten", "kill you", "kill him", "kill her", "kill them", "i will kill", "ill kill", "i want to kill", "i will hurt", "ill hurt", "hurt you", "murder", "assault", "shoot", "shooting", "stab", "stabbing", "bomb threat", "massacre", "genocide", "torture", "behead", "rape", "hang you", "beat you", "burn you",
      "patayin", "papatayin", "patay kita", "papatayin kita", "saktan kita", "sasaktan kita", "sasaksakin", "saksakin", "babarilin", "barilin", "bugbugin", "susunugin", "sunugin", "pagsamantalahan"
    );
    foreach ($blocked_terms as $term) {
      $term = trim($term);
      if (preg_match('/(^| )' . preg_quote($term, '/') . '( |$)/', $normalized)) return true;
    }
    return false;
  };
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
  $auth = $conn->prepare("SELECT t.user_email FROM auth_tokens t INNER JOIN users u ON u.email = t.user_email WHERE t.user_email = ? AND t.token_hash = ? AND t.expires_at > NOW() AND u.status <> 'banned' LIMIT 1");
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
  if (!$conn->query("CREATE TABLE IF NOT EXISTS community_post_reports (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, post_id BIGINT UNSIGNED NOT NULL, community VARCHAR(80) NOT NULL, reporter_email VARCHAR(190) NOT NULL, reason TEXT NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'pending', created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY uq_post_reporter (post_id, reporter_email), KEY idx_post_reports_status (status, created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8")) {
    $reply(array("success" => false, "error" => "Failed to prepare post reports table"), 500);
  }
  if (!$conn->query("CREATE TABLE IF NOT EXISTS community_notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    recipient_email VARCHAR(190) NOT NULL,
    actor_email VARCHAR(190) NULL,
    notification_type VARCHAR(24) NOT NULL,
    post_id BIGINT UNSIGNED NULL,
    community VARCHAR(80) NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_community_notifications_recipient (recipient_email, read_at, created_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8")) {
    $reply(array("success" => false, "error" => "Failed to prepare community notifications"), 500);
  }
  $create_notification = function ($recipient, $actor, $type, $post_id, $community_name, $message) use ($conn) {
    if (strtolower($recipient) === strtolower($actor)) return;
    $notification = $conn->prepare("INSERT INTO community_notifications (recipient_email, actor_email, notification_type, post_id, community, message) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$notification) return;
    $notification->bind_param("sssiss", $recipient, $actor, $type, $post_id, $community_name, $message);
    $notification->execute();
    $notification->close();
  };

  if ($action === "notifications_list") {
    $mark_read = !empty($body["mark_read"]);
    $unread_count = 0;
    $count_stmt = $conn->prepare("SELECT COUNT(*) FROM community_notifications WHERE recipient_email = ? AND read_at IS NULL");
    if ($count_stmt) {
      $count_stmt->bind_param("s", $email);
      $count_stmt->execute();
      $count_stmt->bind_result($unread_count);
      $count_stmt->fetch();
      $count_stmt->close();
    }
    $stmt = $conn->prepare("SELECT n.id, n.actor_email, COALESCE(u.full_name, 'CoreCraft Admin') AS actor_name, n.notification_type, n.post_id, n.community, n.message, n.created_at, n.read_at FROM community_notifications n LEFT JOIN users u ON u.email = n.actor_email WHERE n.recipient_email = ? ORDER BY n.id DESC LIMIT 100");
    if (!$stmt) $reply(array("success" => false, "error" => "Failed to load notifications"), 500);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = array();
    while ($row = $result->fetch_assoc()) $notifications[] = $row;
    $stmt->close();
    if ($mark_read && $unread_count > 0) {
      $read_stmt = $conn->prepare("UPDATE community_notifications SET read_at = NOW() WHERE recipient_email = ? AND read_at IS NULL");
      if ($read_stmt) {
        $read_stmt->bind_param("s", $email);
        $read_stmt->execute();
        $read_stmt->close();
      }
      $unread_count = 0;
    }
    $reply(array("success" => true, "notifications" => $notifications, "unread_count" => (int) $unread_count));
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
    $stmt = $conn->prepare("SELECT CASE WHEN sender_email = ? THEN recipient_email ELSE sender_email END AS peer_email, MAX(created_at) AS last_message, SUM(CASE WHEN recipient_email = ? AND read_at IS NULL THEN 1 ELSE 0 END) AS unread_count FROM private_messages WHERE sender_email = ? OR recipient_email = ? GROUP BY peer_email ORDER BY last_message DESC LIMIT 100");
    if (!$stmt) $reply(array("success" => false, "error" => "Failed to load conversations"), 500);
    $stmt->bind_param("ssss", $email, $email, $email, $email);
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
      $conversations[] = array("email" => $peer_email, "name" => $peer_name, "last_message" => $row["last_message"], "unread_count" => (int) $row["unread_count"]);
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
    $mark_read = $conn->prepare("UPDATE private_messages SET read_at = NOW() WHERE sender_email = ? AND recipient_email = ? AND read_at IS NULL");
    if ($mark_read) {
      $mark_read->bind_param("ss", $peer, $email);
      $mark_read->execute();
      $mark_read->close();
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
      "DELETE FROM community_post_reports WHERE community = ?",
      "DELETE FROM community_comment_likes WHERE comment_id IN (SELECT c.id FROM community_post_comments c INNER JOIN community_posts p ON p.id = c.post_id WHERE p.community = ?)",
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
  $conn->query("CREATE TABLE IF NOT EXISTS community_post_comments (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, post_id BIGINT UNSIGNED NOT NULL, parent_comment_id BIGINT UNSIGNED NULL, user_email VARCHAR(190) NOT NULL, comment TEXT NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY idx_post_comments (post_id, created_at), KEY idx_comment_replies (parent_comment_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8");
  $conn->query("CREATE TABLE IF NOT EXISTS community_comment_likes (comment_id BIGINT UNSIGNED NOT NULL, user_email VARCHAR(190) NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (comment_id, user_email), KEY idx_comment_likes_user (user_email)) ENGINE=InnoDB DEFAULT CHARSET=utf8");
  $comment_parent_column = $conn->query("SHOW COLUMNS FROM community_post_comments LIKE 'parent_comment_id'");
  if ($comment_parent_column && $comment_parent_column->num_rows === 0) {
    $conn->query("ALTER TABLE community_post_comments ADD COLUMN parent_comment_id BIGINT UNSIGNED NULL AFTER post_id, ADD KEY idx_comment_replies (parent_comment_id)");
  }
  // Posts expire after 7 days: remove images, comments, likes, reports, then the posts.
  $expired = $conn->query("SELECT id, image_path FROM community_posts WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY) LIMIT 500");
  if ($expired) {
    $expired_ids = array();
    $image_directory = realpath(__DIR__ . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "community_images");
    while ($row = $expired->fetch_assoc()) {
      $expired_ids[] = (int)$row["id"];
      $path = $row["image_path"];
      if ($path && $image_directory && preg_match('/^uploads\/community_images\/[A-Za-z0-9._-]+$/', $path)) {
        $file = realpath(__DIR__ . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $path));
        if ($file && strpos($file, $image_directory . DIRECTORY_SEPARATOR) === 0 && is_file($file)) @unlink($file);
      }
    }
    $expired->free();
    if (count($expired_ids) > 0) {
      $id_list = implode(",", $expired_ids);
      $conn->query("DELETE FROM community_comment_likes WHERE comment_id IN (SELECT id FROM community_post_comments WHERE post_id IN ($id_list))");
      $conn->query("DELETE FROM community_post_comments WHERE post_id IN ($id_list)");
      $conn->query("DELETE FROM community_post_likes WHERE post_id IN ($id_list)");
      $conn->query("DELETE FROM community_post_reports WHERE post_id IN ($id_list)");
      $conn->query("DELETE FROM community_posts WHERE id IN ($id_list)");
    }
  }

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
    if ($contains_foul_language($post_body)) $reply(array("success" => false, "error" => "This content is not allowed. Remove hateful, threatening, or violent language before publishing."), 400);
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
    $focus_post_id = isset($body["post_id"]) ? (int) $body["post_id"] : 0;
    $post_filter = $focus_post_id > 0 ? " AND p.id = ?" : "";
    $stmt = $conn->prepare("SELECT p.id, p.user_email, u.full_name AS sender_name, p.body, p.image_path, p.created_at, (SELECT COUNT(*) FROM community_post_likes l WHERE l.post_id = p.id) AS like_count, (SELECT COUNT(*) FROM community_post_likes l WHERE l.post_id = p.id AND l.user_email = ?) AS liked FROM community_posts p INNER JOIN users u ON u.email = p.user_email WHERE p.community = ?" . $post_filter . " ORDER BY p.id DESC LIMIT 50");
    if (!$stmt) $reply(array("success" => false, "error" => "Failed to load posts"), 500);
    if ($focus_post_id > 0) $stmt->bind_param("ssi", $email, $community, $focus_post_id);
    else $stmt->bind_param("ss", $email, $community);
    $stmt->execute();
    $result = $stmt->get_result();
    $posts = array();
    while ($row = $result->fetch_assoc()) {
      $post_id = (int) $row["id"];
      $row["comments"] = array();
        $comments_stmt = $conn->prepare("SELECT c.id, c.parent_comment_id, c.user_email, u.full_name AS sender_name, c.comment, c.created_at, (SELECT COUNT(*) FROM community_comment_likes cl WHERE cl.comment_id = c.id) AS like_count, (SELECT COUNT(*) FROM community_comment_likes cl WHERE cl.comment_id = c.id AND cl.user_email = ?) AS liked FROM community_post_comments c INNER JOIN users u ON u.email = c.user_email WHERE c.post_id = ? ORDER BY c.id ASC LIMIT 300");
      if ($comments_stmt) {
        $comments_stmt->bind_param("si", $email, $post_id);
        $comments_stmt->execute();
        $comments_result = $comments_stmt->get_result();
        $comment_indexes = array();
        while ($comment_row = $comments_result->fetch_assoc()) {
          $comment_row["replies"] = array();
          $comment_id = (int) $comment_row["id"];
          $parent_comment_id = (int) $comment_row["parent_comment_id"];
          if ($parent_comment_id > 0 && isset($comment_indexes[$parent_comment_id])) {
            $row["comments"][$comment_indexes[$parent_comment_id]]["replies"][] = $comment_row;
          } else if ($parent_comment_id === 0) {
            $comment_indexes[$comment_id] = count($row["comments"]);
            $row["comments"][] = $comment_row;
          }
        }
        $comments_stmt->close();
      }
      $posts[] = $row;
    }
    $stmt->close();
    $reply(array("success" => true, "posts" => $posts));
  }

  if ($action === "post_delete") {
    $post_id = isset($body["post_id"]) ? (int) $body["post_id"] : 0;
    if ($post_id < 1) $reply(array("success" => false, "error" => "Invalid post"), 400);

    $post = $conn->prepare("SELECT image_path FROM community_posts WHERE id = ? AND community = ? AND user_email = ? LIMIT 1");
    if (!$post) $reply(array("success" => false, "error" => "Failed to verify post ownership"), 500);
    $post->bind_param("iss", $post_id, $community, $email);
    if (!$post->execute()) {
      $post->close();
      $reply(array("success" => false, "error" => "Failed to verify post ownership"), 500);
    }
    $post->bind_result($image_path);
    if (!$post->fetch()) {
      $post->close();
      $reply(array("success" => false, "error" => "Post not found or you are not its author"), 404);
    }
    $post->close();

    $conn->autocommit(false);
    $cleanup_queries = array(
      "DELETE FROM community_comment_likes WHERE comment_id IN (SELECT id FROM community_post_comments WHERE post_id = ?)",
      "DELETE FROM community_post_comments WHERE post_id = ?",
      "DELETE FROM community_post_likes WHERE post_id = ?",
      "DELETE FROM community_post_reports WHERE post_id = ?"
    );
    foreach ($cleanup_queries as $cleanup_sql) {
      $cleanup = $conn->prepare($cleanup_sql);
      if (!$cleanup) {
        $conn->rollback();
        $conn->autocommit(true);
        $reply(array("success" => false, "error" => "Failed to prepare post cleanup"), 500);
      }
      $cleanup->bind_param("i", $post_id);
      if (!$cleanup->execute()) {
        $cleanup->close();
        $conn->rollback();
        $conn->autocommit(true);
        $reply(array("success" => false, "error" => "Failed to clean up post activity"), 500);
      }
      $cleanup->close();
    }

    $delete_post = $conn->prepare("DELETE FROM community_posts WHERE id = ? AND community = ? AND user_email = ?");
    if (!$delete_post) {
      $conn->rollback();
      $conn->autocommit(true);
      $reply(array("success" => false, "error" => "Failed to prepare post deletion"), 500);
    }
    $delete_post->bind_param("iss", $post_id, $community, $email);
    if (!$delete_post->execute() || $delete_post->affected_rows !== 1) {
      $delete_post->close();
      $conn->rollback();
      $conn->autocommit(true);
      $reply(array("success" => false, "error" => "Post could not be deleted"), 500);
    }
    $delete_post->close();
    if (!$conn->commit()) {
      $conn->rollback();
      $conn->autocommit(true);
      $reply(array("success" => false, "error" => "Post deletion could not be saved"), 500);
    }
    $conn->autocommit(true);

    if ($image_path && preg_match('/^uploads\/community_images\/[A-Za-z0-9._-]+$/', $image_path)) {
      $image_directory = realpath(__DIR__ . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "community_images");
      $image_file = $image_directory ? realpath(__DIR__ . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $image_path)) : false;
      if ($image_directory && $image_file && strpos($image_file, $image_directory . DIRECTORY_SEPARATOR) === 0 && is_file($image_file) && !@unlink($image_file)) {
        error_log("CoreCraft could not remove deleted community post image: " . $image_file);
      }
    }
    $reply(array("success" => true));
  }

  if ($action === "post_report") {
    $post_id = isset($body["post_id"]) ? (int) $body["post_id"] : 0;
    $reason = trim(isset($body["reason"]) ? $body["reason"] : "");
    if ($post_id < 1) $reply(array("success" => false, "error" => "Invalid post"), 400);
    if ($reason === "" || strlen($reason) > 1000) $reply(array("success" => false, "error" => "Please provide a reason of 1 to 1000 characters"), 400);

    $post_check = $conn->prepare("SELECT user_email FROM community_posts WHERE id = ? AND community = ? LIMIT 1");
    if (!$post_check) $reply(array("success" => false, "error" => "Failed to verify post"), 500);
    $post_check->bind_param("is", $post_id, $community);
    if (!$post_check->execute()) {
      $post_check->close();
      $reply(array("success" => false, "error" => "Failed to verify post"), 500);
    }
    $post_check->bind_result($post_author);
    if (!$post_check->fetch()) {
      $post_check->close();
      $reply(array("success" => false, "error" => "Post was not found in this community"), 404);
    }
    $post_check->close();
    if (strtolower($post_author) === $email) {
      $reply(array("success" => false, "error" => "You cannot report your own post"), 400);
    }

    $report = $conn->prepare("INSERT INTO community_post_reports (post_id, community, reporter_email, reason) VALUES (?, ?, ?, ?)");
    if (!$report) $reply(array("success" => false, "error" => "Failed to prepare post report"), 500);
    $report->bind_param("isss", $post_id, $community, $email, $reason);
    if (!$report->execute()) {
      $duplicate = $report->errno === 1062;
      $report->close();
      $reply(array("success" => false, "error" => $duplicate ? "You have already reported this post" : "Post report could not be saved"), $duplicate ? 409 : 500);
    }
    $report->close();
    $reply(array("success" => true));
  }

  if ($action === "post_comment_delete") {
    $comment_id = isset($body["comment_id"]) ? (int) $body["comment_id"] : 0;
    $post_id = isset($body["post_id"]) ? (int) $body["post_id"] : 0;
    if ($comment_id < 1 || $post_id < 1) $reply(array("success" => false, "error" => "Invalid comment"), 400);
    $check = $conn->prepare("SELECT c.parent_comment_id FROM community_post_comments c INNER JOIN community_posts p ON p.id = c.post_id WHERE c.id = ? AND c.post_id = ? AND p.community = ? AND c.user_email = ? LIMIT 1");
    if (!$check) $reply(array("success" => false, "error" => "Failed to verify comment ownership"), 500);
    $check->bind_param("iiss", $comment_id, $post_id, $community, $email);
    $check->execute();
    $check->bind_result($parent_comment_id);
    if (!$check->fetch()) {
      $check->close();
      $reply(array("success" => false, "error" => "Comment not found or you are not its author"), 404);
    }
    $check->close();
    $likes = $conn->prepare("DELETE FROM community_comment_likes WHERE comment_id IN (SELECT id FROM community_post_comments WHERE post_id = ? AND (id = ? OR parent_comment_id = ?))");
    if ($likes) { $likes->bind_param("iii", $post_id, $comment_id, $comment_id); $likes->execute(); $likes->close(); }
    if ($parent_comment_id === null) {
      $replies = $conn->prepare("DELETE FROM community_post_comments WHERE parent_comment_id = ? AND post_id = ?");
      if ($replies) { $replies->bind_param("ii", $comment_id, $post_id); $replies->execute(); $replies->close(); }
    }
    $stmt = $conn->prepare("DELETE FROM community_post_comments WHERE id = ? AND post_id = ? AND user_email = ?");
    if (!$stmt) $reply(array("success" => false, "error" => "Failed to prepare comment deletion"), 500);
    $stmt->bind_param("iis", $comment_id, $post_id, $email);
    if (!$stmt->execute()) { $stmt->close(); $reply(array("success" => false, "error" => "Comment could not be deleted"), 500); }
    $stmt->close();
    $reply(array("success" => true));
  }

  if ($action === "post_comment_like") {
    $comment_id = isset($body["comment_id"]) ? (int) $body["comment_id"] : 0;
    $post_id = isset($body["post_id"]) ? (int) $body["post_id"] : 0;
    if ($comment_id < 1 || $post_id < 1) $reply(array("success" => false, "error" => "Invalid comment"), 400);
    $verify = $conn->prepare("SELECT c.id FROM community_post_comments c INNER JOIN community_posts p ON p.id = c.post_id WHERE c.id = ? AND c.post_id = ? AND p.community = ? LIMIT 1");
    if (!$verify) $reply(array("success" => false, "error" => "Failed to verify comment"), 500);
    $verify->bind_param("iis", $comment_id, $post_id, $community);
    $verify->execute();
    $verify->store_result();
    $exists = $verify->num_rows === 1;
    $verify->close();
    if (!$exists) $reply(array("success" => false, "error" => "Comment was not found in this community"), 404);
    $check = $conn->prepare("SELECT comment_id FROM community_comment_likes WHERE comment_id = ? AND user_email = ? LIMIT 1");
    $check->bind_param("is", $comment_id, $email);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
      $check->close();
      $stmt = $conn->prepare("DELETE FROM community_comment_likes WHERE comment_id = ? AND user_email = ?");
      $liked = false;
    } else {
      $check->close();
      $stmt = $conn->prepare("INSERT IGNORE INTO community_comment_likes (comment_id, user_email) VALUES (?, ?)");
      $liked = true;
    }
    if (!$stmt) $reply(array("success" => false, "error" => "Failed to update comment heart"), 500);
    $stmt->bind_param("is", $comment_id, $email);
    if (!$stmt->execute()) { $stmt->close(); $reply(array("success" => false, "error" => "Comment heart could not be saved"), 500); }
    $stmt->close();
    $count = $conn->prepare("SELECT COUNT(*) FROM community_comment_likes WHERE comment_id = ?");
    $like_count = 0;
    if ($count) { $count->bind_param("i", $comment_id); $count->execute(); $count->bind_result($like_count); $count->fetch(); $count->close(); }
    $reply(array("success" => true, "liked" => $liked, "like_count" => (int) $like_count));
  }

  if ($action === "post_like" || $action === "post_comment" || $action === "post_comment_reply") {
    $post_id = isset($body["post_id"]) ? (int) $body["post_id"] : 0;
    $post_check = $conn->prepare("SELECT user_email FROM community_posts WHERE id = ? AND community = ? LIMIT 1");
    if (!$post_check) $reply(array("success" => false, "error" => "Failed to verify post"), 500);
    $post_check->bind_param("is", $post_id, $community);
    $post_check->execute();
    $post_check->bind_result($post_owner_email);
    if (!$post_check->fetch()) {
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
      if ($liked) {
        $actor_stmt = $conn->prepare("SELECT full_name FROM users WHERE email = ? LIMIT 1");
        $actor_name = "Someone";
        if ($actor_stmt) {
          $actor_stmt->bind_param("s", $email);
          $actor_stmt->execute();
          $actor_stmt->bind_result($found_actor_name);
          if ($actor_stmt->fetch()) $actor_name = $found_actor_name;
          $actor_stmt->close();
        }
        $create_notification($post_owner_email, $email, "like", $post_id, $community, $actor_name . " liked your post.");
      }
      $stmt->close();
      $reply(array("success" => true, "liked" => $liked));
    }
    $comment = trim(isset($body["comment"]) ? $body["comment"] : "");
    if ($comment === "" || strlen($comment) > 1000) $reply(array("success" => false, "error" => "Comments must be between 1 and 1000 characters"), 400);
    if ($contains_foul_language($comment)) $reply(array("success" => false, "error" => "This content is not allowed. Remove hateful, threatening, or violent language before commenting."), 400);
    $parent_comment_id = null;
    if ($action === "post_comment_reply") {
      $parent_comment_id = isset($body["parent_comment_id"]) ? (int) $body["parent_comment_id"] : 0;
      $parent_check = $conn->prepare("SELECT id FROM community_post_comments WHERE id = ? AND post_id = ? AND parent_comment_id IS NULL LIMIT 1");
      if (!$parent_check) $reply(array("success" => false, "error" => "Failed to verify parent comment"), 500);
      $parent_check->bind_param("ii", $parent_comment_id, $post_id);
      $parent_check->execute(); $parent_check->store_result();
      $parent_exists = $parent_check->num_rows === 1;
      $parent_check->close();
      if (!$parent_exists) $reply(array("success" => false, "error" => "Comment to reply to was not found"), 404);
    }
    if ($action === "post_comment_reply") {
      $stmt = $conn->prepare("INSERT INTO community_post_comments (post_id, parent_comment_id, user_email, comment) VALUES (?, ?, ?, ?)");
      if (!$stmt) $reply(array("success" => false, "error" => "Failed to prepare reply"), 500);
      $stmt->bind_param("iiss", $post_id, $parent_comment_id, $email, $comment);
    } else {
      $stmt = $conn->prepare("INSERT INTO community_post_comments (post_id, parent_comment_id, user_email, comment) VALUES (?, NULL, ?, ?)");
      if (!$stmt) $reply(array("success" => false, "error" => "Failed to prepare comment"), 500);
      $stmt->bind_param("iss", $post_id, $email, $comment);
    }
    if (!$stmt->execute()) {
      $stmt->close();
      $reply(array("success" => false, "error" => "Comment could not be posted"), 500);
    }
    $stmt->close();
    $actor_stmt = $conn->prepare("SELECT full_name FROM users WHERE email = ? LIMIT 1");
    $actor_name = "Someone";
    if ($actor_stmt) {
      $actor_stmt->bind_param("s", $email);
      $actor_stmt->execute();
      $actor_stmt->bind_result($found_actor_name);
      if ($actor_stmt->fetch()) $actor_name = $found_actor_name;
      $actor_stmt->close();
    }
    $comment_preview = preg_replace('/\\s+/', ' ', $comment);
    if (strlen($comment_preview) > 120) $comment_preview = substr($comment_preview, 0, 117) . "...";
    $notification_text = $action === "post_comment_reply"
      ? $actor_name . " replied to a comment on your post: " . $comment_preview
      : $actor_name . " commented on your post: " . $comment_preview;
    $create_notification($post_owner_email, $email, "comment", $post_id, $community, $notification_text);
    if ($action === "post_comment_reply" && (int) $parent_comment_id > 0) {
      $parent_author_stmt = $conn->prepare("SELECT user_email FROM community_post_comments WHERE id = ? LIMIT 1");
      if ($parent_author_stmt) {
        $parent_author_stmt->bind_param("i", $parent_comment_id);
        $parent_author_stmt->execute();
        $parent_author_stmt->bind_result($parent_author_email);
        if ($parent_author_stmt->fetch() && strtolower($parent_author_email) !== strtolower($post_owner_email)) {
          $create_notification($parent_author_email, $email, "comment", $post_id, $community, $actor_name . " replied to your comment: " . $comment_preview);
        }
        $parent_author_stmt->close();
      }
    }
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
