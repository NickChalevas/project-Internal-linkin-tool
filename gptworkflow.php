<?php
header('Content-Type: application/json');

// Load API keys from environment variables
function getEnvVar($filename, $varname) {
    if (!file_exists($filename)) {
        return null;
    }
    
    $lines = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            if ($name === $varname) {
                return $value;
            }
        }
    }
    return null;
}
$openaiApiKey = getEnvVar('.env', 'OPENAI_API_KEY');
$phpApiUrl = 'http://localhost/project-Internal-linkin-tool/process.php';

// Function to fetch SERP data from the PHP Scraper API
function fetchSerpData($domain, $keyword, $targetUrl) {
    global $phpApiUrl;

    $payload = json_encode([
        'domain' => $domain,
        'keyword' => $keyword,
        'targetUrl' => $targetUrl
    ]);

    $options = [
        'http' => [
            'header'  => "Content-Type: application/json\r\n",
            'method'  => 'POST',
            'content' => $payload
        ]
    ];

    $context = stream_context_create($options);
    $response = file_get_contents($phpApiUrl, false, $context);

    if ($response === FALSE) {
        $error = error_get_last();
        return ['status' => 'error', 'message' => 'PHP API Error: ' . $error['message']];
    }

    return json_decode($response, true);
}

// Function to call OpenAI GPT API
function callGptApi($prompt) {
    global $openaiApiKey;

    $data = [
        'model' => 'gpt-4',
        'messages' => [
            ['role' => 'system', 'content' => 'You are an SEO expert specialized in internal linking strategies.'],
            ['role' => 'user', 'content' => $prompt]
        ],
        'max_tokens' => 1000
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $openaiApiKey
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        return ['status' => 'error', 'message' => 'cURL Error: ' . curl_error($ch)];
    }
    curl_close($ch);

    return json_decode($response, true);
}

// Handle API requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get input data from the request body
    $input = json_decode(file_get_contents('php://input'), true);

    if (isset($input['domain']) && isset($input['keyword']) && isset($input['targetUrl'])) {
        $domain = $input['domain'];
        $keyword = $input['keyword'];
        $targetUrl = $input['targetUrl'];
        $customPrompt = $input['customPrompt'] ?? null;

        // Step 1: Fetch SERP data from PHP Scraper API (which now includes content analysis)
        $serpData = fetchSerpData($domain, $keyword, $targetUrl);

        if ($serpData['status'] === 'success') {
            // Step 2: Prepare GPT prompt with the enhanced data
            if ($customPrompt) {
                // Use custom prompt with placeholders replaced
                $prompt = $customPrompt;
                $prompt = str_replace('{domain}', $domain, $prompt);
                $prompt = str_replace('{keyword}', $keyword, $prompt);
                $prompt = str_replace('{targetUrl}', $targetUrl, $prompt);
                $prompt = str_replace('{candidates}', json_encode($serpData['data']['candidates'], JSON_PRETTY_PRINT), $prompt);
            } else {
                // Use default prompt
                $prompt = "Analyze the following data for internal linking opportunities:\n";
                $prompt .= "Domain: $domain\n";
                $prompt .= "Keyword: $keyword\n";
                $prompt .= "Target URL to link to: $targetUrl\n\n";
                
                // Add information about pages without existing links to the target
                $prompt .= "Pages that don't already link to the target URL (good candidates for linking):\n";
                $prompt .= json_encode($serpData['data']['candidates'], JSON_PRETTY_PRINT) . "\n\n";
                
                $prompt .= "For each candidate page above, please provide:\n";
                $prompt .= "URL: [URL of the candidate page]\n";
                $prompt .= "1. Which existing sentences could be modified to include a link with the keyword '$keyword'.\n";
                $prompt .= "2. Suggested new content/paragraphs to add that would make it natural to include the link.\n";
                $prompt .= "3. 2-3 variations of anchor text for each linking opportunity.\n";
                $prompt .= "4. Rate each opportunity from 1-10 based on relevance and naturalness.\n";
                $prompt .= "Format your response in a clear, structured way that can be easily parsed.";
            }

            // Step 3: Call OpenAI GPT API
            $gptResponse = callGptApi($prompt);

            if (isset($gptResponse['choices'][0]['message']['content'])) {
                // Extract GPT's response
                $recommendations = $gptResponse['choices'][0]['message']['content'];
                echo json_encode([
                    'status' => 'success', 
                    'candidates' => $serpData['data']['candidates'],
                    'recommendations' => $recommendations
                ]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to get recommendations from GPT']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => $serpData['message']]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Domain, keyword, and targetUrl are required']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>