<?php
include "database.php";
include "chat_handler.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>CoreCraft — Build Your Dream PC</title>
  <script src="https://accounts.google.com/gsi/client" async defer></script>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="style.css?v=<?php echo filemtime('style.css'); ?>">
</head>
<body>
<?php include __DIR__ . '/sections/nav.php'; ?>
<?php include __DIR__ . '/sections/home.php'; ?>
<?php include __DIR__ . '/sections/compatibility.php'; ?>
<?php include __DIR__ . '/sections/build.php'; ?>
<?php include __DIR__ . '/sections/pricing.php'; ?>
<?php include __DIR__ . '/sections/troubleshoot.php'; ?>
<?php include __DIR__ . '/sections/phchat.php'; ?>
<?php include __DIR__ . '/sections/login.php'; ?>
<?php include __DIR__ . '/sections/profile.php'; ?>
S  <script>window.CORECRAFT_GOOGLE_CLIENT_ID=<?php echo json_encode(CORECRAFT_GOOGLE_CLIENT_ID); ?>;</script>
  <script src="script.js?v=<?php echo filemtime('script.js'); ?>"></script>
</body>
</html>
