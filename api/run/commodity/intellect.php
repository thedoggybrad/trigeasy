<?php

// Replace with your repository owner, repository name, and workflow name
$repositoryOwner = 'thedoggybrad';
$repositoryName = 'easylist-mirror';
$workflowName = 'updater.yml';

// GitHub Personal Access Token with repo scope (replace with your actual token)
$token = $_ENV['SUSI_NIYA'];

// Username and Password for basic authentication
$username = 'admin';
$password = 'adenium101';

// Check for authentication
if (!isset($_SERVER['PHP_AUTH_USER']) || $_SERVER['PHP_AUTH_USER'] !== $username || $_SERVER['PHP_AUTH_PW'] !== $password) {
    header('WWW-Authenticate: Basic realm="GitHub Workflow Trigger"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Authentication required.';
    exit;
}

// GitHub API URLs
$apiUrl = "https://api.github.com/repos/{$repositoryOwner}/{$repositoryName}/actions/workflows";
$workflowUrl = "{$apiUrl}/{$workflowName}/dispatches";
$commitUrl = "https://api.github.com/repos/{$repositoryOwner}/{$repositoryName}/commits?per_page=1";

// Function to make HTTP requests using stream_socket_client
function makeRequest($url, $token, $method = 'GET', $data = null) {
    $parsedUrl = parse_url($url);
    $host = $parsedUrl['host'];
    $path = $parsedUrl['path'] . (isset($parsedUrl['query']) ? '?' . $parsedUrl['query'] : '');

    $headers = [
        "Authorization: Bearer $token",
        "Content-Type: application/json",
        "Accept: application/vnd.github.v3+json",
        "User-Agent: Your-App-Name", // Replace with your User-Agent header value
        "Connection: close"
    ];

    if ($method === 'POST') {
        $headers[] = "Content-Length: " . strlen($data);
    }

    $headerString = implode("\r\n", $headers);
    $fp = stream_socket_client("ssl://$host:443", $errno, $errstr);

    if (!$fp) {
        echo "Error: $errstr ($errno)\n";
        return false;
    }

    $request = "$method $path HTTP/1.1\r\n" .
               $headerString . "\r\n\r\n" .
               ($data ? $data : '');

    fwrite($fp, $request);
    $response = '';

    while (!feof($fp)) {
        $response .= fread($fp, 1024);
    }
    fclose($fp);

    return $response;
}

// Retrieve the last commit information
$result = makeRequest($commitUrl, $token);

if ($result === false) {
    echo "Failed to retrieve commit information.\n";
    exit;
}

// Split the response into headers and body
list($headers, $body) = explode("\r\n\r\n", $result, 2);
$httpCode = null;

foreach (explode("\r\n", $headers) as $header) {
    if (preg_match('{HTTP\/\S*\s(\d{3})}', $header, $match)) {
        $httpCode = (int)$match[1];
        break;
    }
}

if ($httpCode === 200) {
    $commits = json_decode($body, true);

    if (!empty($commits)) {
        $lastCommitTimestamp = strtotime($commits[0]['commit']['committer']['date']);
        $currentTimestamp = time();
        $timeDiffMinutes = round(($currentTimestamp - $lastCommitTimestamp) / 60);

        if ($timeDiffMinutes >= 1) {
            // Safeguard to prevent accidental failures
            $payload = [
                'ref' => 'main', // Replace with the desired branch or commit reference
                'inputs' => (object) [], // Ensure that inputs is an object, even if empty
            ];

            $result = makeRequest($workflowUrl, $token, 'POST', json_encode($payload));
            list($headers, $body) = explode("\r\n\r\n", $result, 2);
            $httpCode = null;

            foreach (explode("\r\n", $headers) as $header) {
                if (preg_match('{HTTP\/\S*\s(\d{3})}', $header, $match)) {
                    $httpCode = (int)$match[1];
                    break;
                }
            }

            if ($httpCode === 204) {
                echo "Workflow run successfully triggered.\n";
            } else {
                echo "Failed to trigger workflow run. HTTP code: {$httpCode}\n";
                echo "Response: {$body}\n";
            }
        } else {
            echo "The last commit is not 1 minute ago or higher.\n";
        }
    } else {
        echo "No commits found in the repository.\n";
    }
} else {
    echo "Failed to retrieve commit information. HTTP code: {$httpCode}\n";
    echo "Response: {$body}\n";
}
?>
