<?php

// Replace with your repository owner, repository name, and workflow name
$repositoryOwner = 'thedoggybrad';
$repositoryName = 'easylist-mirror';
$workflowName = 'updater.yml';

// GitHub Personal Access Token with repo scope (replace with your actual token)
$token = $_ENV['SUSI_NIYA'];

// Username and Password for basic authentication (replace with your desired username and password)
$username = 'admin';
$password = 'adenium101';

// Check if username and password are provided in the request
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

// Retrieve the last commit information
$ch = curl_init($commitUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json',
    'Accept: application/vnd.github.v3+json',
    'User-Agent: Your-App-Name' // Replace with your User-Agent header value
]);

$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $commits = json_decode($result, true);

    if (!empty($commits)) {
        $lastCommitTimestamp = strtotime($commits[0]['commit']['committer']['date']);
        $currentTimestamp = time();
        $timeDiffMinutes = round(($currentTimestamp - $lastCommitTimestamp) / 60);

        if ($timeDiffMinutes >= 1) {
            // Just a safeguard to prevent an accidental failure and multiple syncing workflows at once (but still not perfect)
            $payload = json_encode([
                'ref' => 'main', // Replace with the desired branch or commit reference
                'inputs' => (object) [], // Ensure that inputs is an object, even if empty
            ]);

            $ch = curl_init($workflowUrl);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
                'Accept: application/vnd.github.v3+json',
                'User-Agent: Your-App-Name' // Replace with your User-Agent header value
            ]);

            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 204) {
                echo "Workflow run successfully triggered.\n";
            } else {
                echo "Failed to trigger workflow run. HTTP code: {$httpCode}\n";
                echo "Response: {$result}\n";
            }
        } else {
            echo "The last commit is not 1 minute ago or higher.\n";
        }
    } else {
        echo "No commits found in the repository.\n";
    }
} else {
    echo "Failed to retrieve commit information. HTTP code: {$httpCode}\n";
    echo "Response: {$result}\n";
}
?>
