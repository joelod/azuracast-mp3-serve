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

// Create a socket connection
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if ($socket === false) {
    die('Unable to create socket: ' . socket_strerror(socket_last_error()));
}

// Connect to the server
$connection = socket_connect($socket, $serverIp, $serverPort);
if ($connection === false) {
    die('Unable to connect to server: ' . socket_strerror(socket_last_error()));
}

// Send headers
$headers  = 'PUT ' . $serverMountPoint . ' HTTP/1.1' . PHP_EOL;
$headers .= 'Host: ' . $serverIp . ':' . $serverPort . PHP_EOL;
$headers .= 'Authorization: Basic ' . base64_encode($username.":".$password) . PHP_EOL;
$headers .= 'Transfer-Encoding: chunked' . PHP_EOL;
$headers .= 'Content-Type: audio/mpeg' . PHP_EOL;
$headers .= 'Ice-Public: 1' . PHP_EOL;
$headers .= 'Ice-Name: FT-033' . PHP_EOL;
$headers .= 'Ice-Description: ?' . PHP_EOL;
$headers .= 'Ice-URL: https://radioparanoia1000.com' . PHP_EOL;
$headers .= 'Ice-Genre: ambient' . PHP_EOL;
$headers .= 'Ice-Bitrate: ' . $bitrate . PHP_EOL;
$headers .= 'Expect: 100-continue' . PHP_EOL . PHP_EOL;

// Send headers
socket_write($socket, $headers, strlen($headers));

// Read the response

$response = socket_read($socket, 4096);

echo $response;
// Send the MP3 file in chunks

$contents = '';

while (!feof($fileHandle)) {
    echo 'Sending chunk...' . PHP_EOL;
    $contents .= fread($fileHandle, 8192);

    echo 'Chunk size: ' . strlen($contents) . PHP_EOL;

    $chunk = dechex(strlen($contents)) . PHP_EOL . $contents . PHP_EOL;

    // echo 'Chunk: ' . $chunk . PHP_EOL;

    socket_write($socket, $chunk, strlen($chunk));

    echo 'Waiting for response...' . PHP_EOL;

    $response = socket_read($socket, 4096);

    echo 'Response: ' . $response . PHP_EOL;

    sleep(1);
}



// Send the last chunk
$chunk = '0' . PHP_EOL . PHP_EOL;
socket_write($socket, $chunk, strlen($chunk));

// Close the socket
socket_close($socket);

// Close the file
fclose($fileHandle);

?>
