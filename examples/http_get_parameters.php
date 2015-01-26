<?php
require '../websock2.php';

//Create file socket
$socket = new FileSocket;

//Run request and echo response body contents (without headers)
//WebRequest::createFromUrl automatically urlencodes request URI path, parameter names and values
//All parameters will be sent with GET method
echo $socket->sendRequest(
	WebRequest::createFromUrl('https://php.net/manual/add-note.php?sect=function.fopen&redirect=https://php.net/manual/en/function.fopen.php')
)->getBody();
