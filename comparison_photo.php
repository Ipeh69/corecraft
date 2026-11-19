<?php
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$query = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
if ($query === '' || strlen($query) > 180) {
    http_response_code(400);
    echo json_encode(array('error' => 'Enter a product name under 180 characters.'));
    exit;
}

// PC Express uses Shopify's product search suggestion endpoint. Search on the
// server so the browser does not depend on PC Express enabling cross-origin API access.
$searchUrl = 'https://pcx.com.ph/search/suggest.json?' . http_build_query(array(
    'q' => $query,
    'resources[type]' => 'product',
    'resources[limit]' => '8',
    'resources[options][unavailable_products]' => 'show',
    'resources[options][fields]' => 'title,product_type,variants.title,vendor'
));
$ch = curl_init($searchUrl);
curl_setopt_array($ch, array(
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 3,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_TIMEOUT => 18,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_HTTPHEADER => array('Accept: application/json'),
    CURLOPT_USERAGENT => 'CoreCraft Part Comparison/1.0'
));
$response = curl_exec($ch);
$curlError = curl_error($ch);
$statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $statusCode < 200 || $statusCode >= 300) {
    http_response_code(502);
    echo json_encode(array('error' => 'PC Express product search is temporarily unavailable.'));
    exit;
}

$payload = json_decode($response, true);
$products = isset($payload['resources']['results']['products']) && is_array($payload['resources']['results']['products'])
    ? $payload['resources']['results']['products'] : array();
$queryTerms = preg_split('/[^a-z0-9]+/i', strtolower($query), -1, PREG_SPLIT_NO_EMPTY);
$bestProduct = null;
$bestScore = 0;
foreach ($products as $product) {
    if (!is_array($product)) continue;
    $title = isset($product['title']) ? trim((string)$product['title']) : '';
    $image = '';
    if (isset($product['image']) && is_string($product['image'])) $image = trim($product['image']);
    elseif (isset($product['image']['url'])) $image = trim((string)$product['image']['url']);
    if ($image === '' && isset($product['featured_image']) && is_string($product['featured_image'])) $image = trim($product['featured_image']);
    elseif ($image === '' && isset($product['featured_image']['url'])) $image = trim((string)$product['featured_image']['url']);
    if ($image === '' && isset($product['images'][0])) {
        $image = is_array($product['images'][0]) && isset($product['images'][0]['url'])
            ? trim((string)$product['images'][0]['url']) : trim((string)$product['images'][0]);
    }
    if ($title === '' || $image === '') continue;

    $normalizedTitle = strtolower($title);
    $matchedTerms = 0;
    foreach ($queryTerms as $term) {
        if (strlen($term) < 2) continue;
        if (strpos($normalizedTitle, $term) !== false) $matchedTerms++;
    }
    $score = count($queryTerms) ? $matchedTerms / count($queryTerms) : 0;
    if ($score > $bestScore) {
        $bestScore = $score;
        $bestProduct = $product;
    }
}

if ($bestProduct && $bestScore >= 0.45) {
    $image = '';
    if (isset($bestProduct['image']) && is_string($bestProduct['image'])) $image = trim($bestProduct['image']);
    elseif (isset($bestProduct['image']['url'])) $image = trim((string)$bestProduct['image']['url']);
    if ($image === '' && isset($bestProduct['featured_image']) && is_string($bestProduct['featured_image'])) $image = trim($bestProduct['featured_image']);
    elseif ($image === '' && isset($bestProduct['featured_image']['url'])) $image = trim((string)$bestProduct['featured_image']['url']);
    if ($image === '' && isset($bestProduct['images'][0])) {
        $image = is_array($bestProduct['images'][0]) && isset($bestProduct['images'][0]['url'])
            ? trim((string)$bestProduct['images'][0]['url']) : trim((string)$bestProduct['images'][0]);
    }
    if (strpos($image, '//') === 0) $image = 'https:' . $image;
    elseif (strpos($image, '/') === 0) $image = 'https://pcx.com.ph' . $image;
    $productUrl = isset($bestProduct['url']) ? trim((string)$bestProduct['url']) : '';
    if (strpos($productUrl, '//') === 0) $productUrl = 'https:' . $productUrl;
    elseif ($productUrl !== '' && strpos($productUrl, '/') === 0) $productUrl = 'https://pcx.com.ph' . $productUrl;
    echo json_encode(array('photo' => array(
        'url' => $image,
        'title' => (string)$bestProduct['title'],
        'source' => 'PC Express',
        'productUrl' => $productUrl
    )));
    exit;
}

echo json_encode(array('photo' => null));
