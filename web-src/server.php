#!/usr/bin/php -q
<?php

error_reporting(E_ALL);
require_once "client.class.php";
require_once "server.class.php";
set_time_limit(0);

//initialize input params

if(count($argv) < 4) {
	echo "invalid number of input params";
	exit();
}

$nl = "\r\n";
$address = $argv[1];
$port = $argv[2];
$video_port = $argv[3];
$arduino_port = $argv[4];

exec("export LD_LIBRARY_PATH=/usr/lib/mjpg-streamer");
exec('mjpg_streamer -i "input_uvc.so -y -d /dev/video0 -r 640x480 -f 15" -o "output_http.so -w /srv/www/htdocs -p '.$video_port.'" > /dev/null 2>/dev/null &');

//initialize server

$server = new Server($address, $port, $video_port, $arduino_port);
$server->run();

?>