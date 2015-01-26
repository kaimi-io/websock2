<?php
require '../websock2.php';

//Create file socket
$socket = new FileSocket;

//Run request and echo response body contents (without headers)
echo $socket->sendRequest(WebRequest::createFromUrl('http://php.net'))->getBody();
