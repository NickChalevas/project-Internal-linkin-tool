<?php
header('Content-Type: application/json');

function getEnvVarImproved($filename, $varname) {
    if (!file_exists($filename)) {
        return null;
    }

    $lines = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            if (trim($name) === trim($varname)) { // Added trim here!
                return $value;
            }
        }
    }
    return null;
}

$API_KEY = getEnvVarImproved('.env', 'API_KEY');
$CSE_ID = getEnvVarImproved('.env', 'CSE_ID');
define('API_KEY', $API_KEY);
define('CSE_ID', $CSE_ID);

function googleCustomSearch($query) {
    $query = urlencode($query);
    $url = "https://www.googleapis.com/customsearch/v1?key=" . API_KEY . "&cx=" . CSE_ID . "&q=" . $query;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

$input = json_decode(file_get_contents('php://input'), true);
$domain = $input['domain'] ?? ($_GET['domain'] ?? '');
$keyword = $input['keyword'] ?? ($_GET['keyword'] ?? '');
$targetUrl = $input['targetUrl'] ?? ($_GET['targetUrl'] ?? '');

error_log("Received domain: " . $domain);
error_log("Received keyword: " . $keyword);
error_log("Received target URL: " . $targetUrl);

if (empty($domain) || empty($keyword) || empty($targetUrl)) {
    echo json_encode(['status' => 'error', 'message' => 'Domain, keyword, and targetUrl are required']);
    exit;
}

$query = "site:$domain $keyword";
$data = googleCustomSearch($query);

if (!isset($data['items'])) {
    echo json_encode(['status' => 'error', 'message' => 'No results found for the query']);
    exit;
}

function containsTargetLink($pageUrl, $targetUrl) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $pageUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$html) {
        return ['status' => false, 'error' => "Failed to fetch page (HTTP $httpCode)"];
    }

    return ['status' => true, 'containsLink' => strpos($html, htmlspecialchars_decode($targetUrl)) !== false];
}

$results = [];
$candidates = [];
$processedCount = 0;
$failedCount = 0;

foreach ($data['items'] as $item) {
    $pageUrl = $item['link'];
    $title = $item['title'];
    $snippet = $item['snippet'] ?? 'No snippet available';

    if ($pageUrl === $targetUrl) continue;

    $pageData = ['title' => $title, 'url' => $pageUrl, 'snippet' => $snippet];
    $linkCheck = containsTargetLink($pageUrl, $targetUrl);

    if ($linkCheck['status']) {
        $pageData['containsTargetLink'] = $linkCheck['containsLink'];
        $processedCount++;
        if (!$linkCheck['containsLink']) $candidates[] = $pageData;
    } else {
        $pageData['error'] = $linkCheck['error'];
        $failedCount++;
        $candidates[] = $pageData;
    }
    $results[] = $pageData;
}

echo json_encode([
    'status' => 'success',
    'data' => [
        'all_results' => $results,
        'candidates' => $candidates,
        'candidates_count' => count($candidates),
        'domain' => $domain,
        'keyword' => $keyword,
        'targetUrl' => $targetUrl,
        'pages_processed' => $processedCount,
        'pages_failed' => $failedCount
    ]
]);
?>
