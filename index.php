<?php

require __DIR__ . '/vendor/autoload.php';

use FFMpeg\FFMpeg;
use FFMpeg\Format\Audio\Mp3;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Set Icecast server details
$serverIp = $_ENV['SERVER_IP'];
$serverPort = $_ENV['SERVER_PORT'];
$serverMountPoint = $_ENV['SERVER_MOUNT_POINT'];
$username = $_ENV['USERNAME'];
$password = $_ENV['PASSWORD'];
$originalFilePath = $_ENV['FILE_PATH'];
$convertedFilePath = __DIR__ . '/converted.mp3';

if ($serverIp == null || $serverPort == null || $username == null || $password == null || $originalFilePath == null) {
    die('Please set all environment variables.');
}

// Convert the MP3 file to a constant bitrate of $bitrate VBR MP3 file
$bitrate = 128;
$convert = false;
$ffmpeg = FFMpeg::create();

if ($convert) {
    $audio = $ffmpeg->open($originalFilePath);
    $format = new Mp3();
    $format->setAudioKiloBitrate($bitrate);

    echo '[' . date('H:i:s') . '] Converting MP3 file to ' . $bitrate . ' kbps MP3 file...' . PHP_EOL;
    $audio->save($format, $convertedFilePath);
    echo '[' . date('H:i:s') . '] Converted MP3 file to ' . $bitrate . ' kbps MP3 file! Took ' . round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 2) . ' seconds.' . PHP_EOL;


    // Open the converted MP3 file for reading
    $fileHandle = fopen($convertedFilePath, 'r');
    $fileSize = filesize($convertedFilePath);
} else {
    // Open the original MP3 file for reading
    $fileHandle = fopen($originalFilePath, 'r');
    $fileSize = filesize($originalFilePath);
}

if (!$fileHandle) {
    die('Unable to open the converted MP3 file.');
}

// Create a socket connection
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if ($socket === false) {
    die('Unable to create socket: ' . socket_strerror(socket_last_error()));
}

// Connect to the Icecast server
$connection = socket_connect($socket, $serverIp, $serverPort);
if ($connection === false) {
    die('Unable to connect to Icecast server: ' . socket_strerror(socket_last_error()));
}

// Send headers
$headers = "PUT $serverMountPoint HTTP/1.1" . PHP_EOL;
$headers .= "Host: $serverIp:$serverPort" . PHP_EOL;
$headers .= "Authorization: Basic " . base64_encode("$username:$password") . "" . PHP_EOL;
$headers .= "User-Agent: PHP Icecast Client" . PHP_EOL;
$headers .= "Content-Type: audio/mpeg" . PHP_EOL;
$headers .= "Ice-Public: 0" . PHP_EOL;
$headers .= "Ice-Name: Teststream" . PHP_EOL;
$headers .= "Ice-Title: Teststream" . PHP_EOL;
$headers .= "Title: Teststream" . PHP_EOL;

$headers .= "Ice-Description: This is just a simple test stream" . PHP_EOL;
$headers .= "Ice-URL: http://radioparanoia1000.com" . PHP_EOL;
$headers .= "Ice-Genre: Ambient" . PHP_EOL;
$headers .= "Ice-Bitrate: $bitrate" . PHP_EOL;
$headers .= "Expect: 100-continue" . PHP_EOL . PHP_EOL;

// Send headers
socket_write($socket, $headers, strlen($headers));

// Read the response
$response = socket_read($socket, 4096);

// Check for "HTTP/1.1 100 Continue" response
if (strpos($response, "HTTP/1.1 100 Continue") === false && strpos($response, "HTTP/1.1 200 OK") === false) {
    die('Unexpected response: ' . $response);
}

echo $response . PHP_EOL;

$bytesPerSecond = ($bitrate * 1000) / 8;
// $seconds = $fileSize / $bytesPerSecond;

// Send the MP3 file in constant $bitrate kbps chunks
while (!feof($fileHandle)) {
    $chunk = fread($fileHandle, $bytesPerSecond);
    socket_write($socket, $chunk, strlen($chunk));
    echo '[' . date('H:i:s') . '] Sent ' . round(ftell($fileHandle) / $fileSize * 100, 2) . '% of the MP3 file...' . PHP_EOL;
    sleep(1);
}

// Close the file handle
fclose($fileHandle);

// Close the socket connection
socket_close($socket);

// Delete the converted file after streaming
if ($convert) {
    unlink($convertedFilePath);
}
