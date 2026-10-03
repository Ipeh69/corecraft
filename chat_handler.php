<?php
// Chat, DM, community and post API. Loaded by index.php (requests to index.php?chat_api).

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
