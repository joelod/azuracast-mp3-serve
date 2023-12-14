<?php

// include dotenv
require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$getID3 = new getID3;

$serverIp = $_ENV['SERVER_IP'];
$serverPort = $_ENV['SERVER_PORT'];
$serverMountPoint = $_ENV['SERVER_MOUNT_POINT'];
$username = $_ENV['USERNAME'];
$password = $_ENV['PASSWORD'];
$filePath = $_ENV['FILE_PATH'];

// Get the bitrate of the MP3 file
$file = $getID3->analyze($filePath);
$bitrate = $file['audio']['bitrate'];

if ($serverIp == null || $serverPort == null || $serverMountPoint == null || $username == null || $password == null || $filePath == null) {
    die('Please set all environment variables.');
}

// Open the MP3 file for reading
$fileHandle = fopen($filePath, 'r');
$fileSize = filesize($filePath);
$chunkSize = ($bitrate * 1024) / 8; // size of each chunk in bytes

if (!$fileHandle) {
    die('Unable to open the MP3 file.');
}

// Create cURL session
$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, "http://$serverIp:$serverPort$serverMountPoint");
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Host: ' . $serverIp . ':' . $serverPort,
    'Authorization: Basic ' . base64_encode($username . ':' . $password),
    'Transfer-Encoding: chunked',
    'Content-Type: ' . 'audio/mpeg',
    'Ice-Public: ' . '1',
    'Ice-Name: ' . 'FT-033',
    'Ice-Description: ' . '',
    'Ice-URL: ' . 'https://radioparanoia1000.com',
    'Connection: Keep-Alive',
    'Content-Length: ' . $fileSize,
));

curl_setopt($ch, CURLOPT_VERBOSE, true);
curl_setopt($ch, CURLOPT_STDERR, fopen('php://stderr', 'w'));

// Tell cURL that we want to send a PUT request
curl_setopt($ch, CURLOPT_PUT, true);

// Tell cURL that we want to send the contents of $fileHandle to the server
curl_setopt($ch, CURLOPT_INFILE, $fileHandle);


// We're going to "PUT" this information
// curl_setopt($ch, CURLOPT_INFILESIZE, filesize($filePath));
curl_setopt($ch, CURLOPT_INFILESIZE, $chunkSize);

/*
// Execute cURL session
$response = curl_exec($ch);

// Check for errors
if (curl_errno($ch)) {
    echo 'Curl error: ' . curl_error($ch);
} else {
    echo 'File successfully sent to Icecast server.';
}

print_r($response);

// Close cURL session
curl_close($ch);

// Close the MP3 file handle
fclose($fileHandle);
*/

while (!feof($fileHandle)) {
    $data = fread($fileHandle, $chunkSize);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_exec($ch);
    
    // Wait for 1 second
    sleep(1);
}

fclose($fileHandle);
curl_close($ch);
?>
