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

if ($serverIp == null || $serverPort == null || $serverMountPoint == null || $username == null || $password == null || $originalFilePath == null) {
    die('Please set all environment variables.');
}

// Convert the MP3 file to a constant bitrate of $bitrate VBR MP3 file
$bitrate = 192;
$ffmpeg = FFMpeg::create();
$audio = $ffmpeg->open($originalFilePath);
$format = new Mp3();
$format->setAudioKiloBitrate($bitrate);

echo '[' . date('H:i:s') . '] Converting MP3 file to ' . $bitrate . ' kbps MP3 file...' . PHP_EOL;
$audio->save($format, $convertedFilePath);
echo '[' . date('H:i:s') . '] Converted MP3 file to ' . $bitrate . ' kbps MP3 file! Took ' . round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 2) . ' seconds.' . PHP_EOL;


// Open the converted MP3 file for reading
$fileHandle = fopen($convertedFilePath, 'r');
$fileSize = filesize($convertedFilePath);

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
$headers = "PUT $serverMountPoint HTTP/1.1\r\n";
$headers .= "Host: $serverIp:$serverPort\r\n";
$headers .= "Authorization: Basic " . base64_encode("$username:$password") . "\r\n";
$headers .= "User-Agent: PHP Icecast Client\r\n";
$headers .= "Content-Type: audio/mpeg\r\n";
$headers .= "Ice-Public: 1\r\n";
$headers .= "Ice-Name: Teststream\r\n";
$headers .= "Ice-Description: This is just a simple test stream\r\n";
$headers .= "Ice-URL: http://radioparanoia1000.com\r\n";
$headers .= "Ice-Genre: Ambient\r\n";
$headers .= "Expect: 100-continue\r\n\r\n";

// Send headers
socket_write($socket, $headers, strlen($headers));

// Read the response
$response = socket_read($socket, 4096);

// Check for "HTTP/1.1 100 Continue" response
if (strpos($response, "HTTP/1.1 100 Continue") === false && strpos($response, "HTTP/1.1 200 OK") === false) {
    die('Unexpected response: ' . $response);
}

// Send the MP3 file in constant $bitrate kbps chunks
while ($data = fread($fileHandle, 24567)) {
    socket_write($socket, $data, strlen($data));
    echo '[' . date('H:i:s') . '] Sending ' . strlen($data) . ' bytes of data!' . PHP_EOL;
    usleep(1000000); // Sleep for 1 second
}

// Close the file handle
fclose($fileHandle);

// Close the socket connection
socket_close($socket);

// Delete the converted file after streaming
unlink($convertedFilePath);
