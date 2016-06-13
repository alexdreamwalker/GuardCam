<?php

/*
* Server class for operations with arduino
* may be fullfilled in future
*/

class Server {
	private $address; //the address of server
	private $port; //port of server
	private $master; //master socket
	private $sockets; //array for client's sockets
	private $clients; //array of clients
	private $arduino_port; //port for arduino
	private $video_port; //port of video streaming

	function __construct($address, $port, $video_port, $arduino_port) { //main server constructor
		$this->address = $address;
		$this->port = $port;
		$this->video_port = $video_port;
		$this->arduino_port = $arduino_port;
		$this->console("Starting server on {$this->address}:{$this->port}");

		//socket init. From google fork
		$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

		if (!is_resource($socket))
			$this->console("socket_create() failed: ".socket_strerror(socket_last_error()), true);

		if (!socket_bind($socket, $this->address, $this->port))
			$this->console("socket_bind() failed: ".socket_strerror(socket_last_error()), true);

		if(!socket_listen($socket, 20))
			$this->console("socket_listen() failed: ".socket_strerror(socket_last_error()), true);
		$this->master = $socket;
		$this->sockets = array($socket);
		$this->console("Server started on {$this->address}:{$this->port}");
	}

	public function run() {
		$this->console("Start running...");
		while(true) {
			$changed_sockets = $this->sockets;
			@socket_select($changed_sockets, $write = NULL, $except = NULL, 1);
			foreach($changed_sockets as $socket) {
				if($socket == $this->master) {
					if(($acceptedSocket = socket_accept($this->master)) < 0) {
						$this->console("Socket error: ".socket_strerror(socket_last_error($acceptedSocket)));
					}
					else {
						$this->connect($acceptedSocket);
					}
				}
				else {
					$this->console("Finding the socket that associated to the client...");
					$client = $this->getClientBySocket($socket);
					if($client) {
						$this->console("Receiving data from the client");
						$bytes = @socket_recv($socket, $data, 2048, MSG_DONTWAIT);
						if(!$client->getHandshake()) {
							$this->console("Doing the handshake");
							if($this->handshake($client, $data))
								$this->startProcess($client);
						}
						elseif($bytes === 0) {
							$this->disconnect($client);
						}
						else {
							// When received data from client
							$this->action($client, $data);
						}
					}
				}
			}
		}
	}

	private function connect($socket) { //connect new client to socket
		$this->console("Creation of client on socket {$socket}");
		$client = new Client(uniqid(), $socket);
		$this->clients[] = $client;
		$this->sockets[] = $socket;
		$this->console("Client #{$client->getId()} is created on socket {$client->getSocket()}");
	}

	private function disconnect($client) { //close the connection of selected client
		$this->console("Disconnecting client #{$client->getId()}");
		$i = array_search($client, $this->clients);
		$j = array_search($client->getSocket(), $this->sockets);
		if($j >= 0) {
			array_splice($this->sockets, $j, 1);
			socket_close($client->getSocket());
			$this->console("Socket closed");
		}
		
		if($i >= 0)
			array_splice($this->clients, $i, 1);
		$this->console("Client #{$client->getId()} disconnected");
	}

	private function handshake($client, $headers) { //do the handshake
		$this->console("Getting client WebSocket version...");
		if(preg_match("/Sec-WebSocket-Version: (.*)\r\n/", $headers, $match))
			$version = $match[1];
		else {
			$this->console("The client doesn't support WebSocket");
			return false;
		}
		
		$this->console("Client WebSocket version is {$version}, (required: 13)");
		if($version == 13) {
			// Extract header variables
			$this->console("Getting headers...");
			if(preg_match("/GET (.*) HTTP/", $headers, $match))
				$root = $match[1];
			if(preg_match("/Host: (.*)\r\n/", $headers, $match))
				$host = $match[1];
			if(preg_match("/Origin: (.*)\r\n/", $headers, $match))
				$origin = $match[1];
			if(preg_match("/Sec-WebSocket-Key: (.*)\r\n/", $headers, $match))
				$key = $match[1];
			
			$this->console("Client headers are:");
			$this->console("\t- Root: ".$root);
			$this->console("\t- Host: ".$host);
			$this->console("\t- Origin: ".$origin);
			$this->console("\t- Sec-WebSocket-Key: ".$key);
			
			$this->console("Generating Sec-WebSocket-Accept key...");
			$acceptKey = $key.'258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
			$acceptKey = base64_encode(sha1($acceptKey, true));

			$upgrade = "HTTP/1.1 101 Switching Protocols\r\n".
					   "Upgrade: websocket\r\n".
					   "Connection: Upgrade\r\n".
					   "Sec-WebSocket-Accept: $acceptKey".
					   "\r\n\r\n";
			
			$this->console("Sending this response to the client #{$client->getId()}:\r\n".$upgrade);
			socket_write($client->getSocket(), $upgrade);
			$client->setHandshake(true);
			$this->console("Handshake is successfully done!");
			return true;
		}
		else {
			$this->console("WebSocket version 13 required (the client supports version {$version})");
			return false;
		}
	}

	private function getClientBySocket($socket) {
		foreach($this->clients as $client)
			if($client->getSocket() == $socket) {
				$this->console("Client found");
				return $client;
			}
		return false;
	}

	private function action($client, $action) { //get message from client
		$action = $this->unmask($action);
		$this->console("Performing action: ".$action);
		if($action == "exit" || $action == "quit") {
			$this->console("Killing a child process");
			posix_kill($client->getPid(), SIGTERM);
			$this->console("Process {$client->getPid()} is killed!");
		}
		else if($action == "photo") {
			$this->console("URL: http://{$this->address}:{$this->video_port}/?action=snapshot");
			$image = imagecreatefromjpeg("http://{$this->address}:{$this->video_port}/?action=snapshot");
			$date = date("Y-m-d H-i-s");
			imagejpeg($image, "photos/".$date.".jpg");
			imagedestroy($image);
		}
		else if($action == "left") $this->sendToSerial(2);
		else if($action == "right") $this->sendToSerial(3);
		else if($action == "up") $this->sendToSerial(4);
		else if($action == "down") $this->sendToSerial(5);
	}

	private function sendToSerial($msg) { //send message to arduino serial port
  		$fp = fopen($this->arduino_port, "r+");
  		fwrite($fp, $msg);
  		fclose($fp);
	}

	private function startProcess($client) { //start a child process for pushing data
		$this->console("Start a child process");
		$pid = pcntl_fork();
		if($pid == -1) {
			die('could not fork');
		}
		elseif($pid) { // process
			$client->setPid($pid);
		}
		else {
			// we are the child
			while(true) {
				//arduino read code and sent to client
				$fp = fopen($this->arduino_port, "w+");
				$data = fread($fp, 10);
				if(!empty($data)) $this->console("Arduino says :".$data);
				if($data == 1) {
					foreach($this->clients as $client)
						$this->send($client, "motion");
				}
				fclose($fp);
			}
		}
	}

	private function send($client, $text) { //send a text to client
		$this->console("Send '".$text."' to client #{$client->getId()}");
		$text = $this->encode($text);
		if(socket_write($client->getSocket(), $text, strlen($text)) === false) {
			$this->console("Unable to write to client #{$client->getId()}'s socket");
			$this->disconnect($client);
		}
	}

	private function encode($text) { //encode a text before sending to client
		// 0x1 text frame (FIN + opcode)
		$b1 = 0x80 | (0x1 & 0x0f);
		$length = strlen($text);
		
		if($length <= 125)
			$header = pack('CC', $b1, $length);
		elseif($length > 125 && $length < 65536)
			$header = pack('CCS', $b1, 126, $length);
		elseif($length >= 65536)
			$header = pack('CCN', $b1, 127, $length);
		
		return $header.$text;
	}

	private function unmask($payload) { //unmask a message, received from client
		$length = ord($payload[1]) & 127;

		if($length == 126) {
			$masks = substr($payload, 4, 4);
			$data = substr($payload, 8);
		}
		elseif($length == 127) {
			$masks = substr($payload, 10, 4);
			$data = substr($payload, 14);
		}
		else {
			$masks = substr($payload, 2, 4);
			$data = substr($payload, 6);
		}

		$text = '';
		for ($i = 0; $i < strlen($data); ++$i) {
			$text .= $data[$i] ^ $masks[$i%4];
		}
		return $text;
	}

	private function console($text, $exit = false) { //print text to term
		$text = date('[Y-m-d H:i:s] ').$text."\r\n";
		if($exit)
			die($text);
		echo $text;
	}

}