<?php
require '../websock2.php';

define('EOL', php_sapi_name() === 'cli' ? PHP_EOL : '<br>');

//Create file socket
$sock = new FileSocket;

//Set up callback which is called when response headers are received
$sock->setOnReceiveHeadersCallback(function($headers, HttpSocket $sock, WebRequest $request)
{
	echo 'Headers for request ' . $request->getFullAddress() . ' have been received!' . EOL;
	$headers = new HttpHeaderManager($headers);
	//Echo page content-type
	$type = $headers->getHeader('Content-Type');
	if($type !== null)
		echo 'Content-type = ' . $type . EOL;
	
	return true; //Return false to abort body request and return headers from sendRequest function
});

//Set up callback which is called when part of response body is received
//When this callback is specified, NetworkSocket::sendRequest function will always return just headers string
//This will allow to still use HttpRequestManager for automatic redirection, cookies and authentication processing
//This allows to download huge amount of data and save it to file without taking too much RAM
//Also allows to check response for presence of some substring without downloading full response to string
$sock->setOnReceiveBodyCallback(function($headers, $data_part, HttpSocket $sock, WebRequest $request)
{
	echo 'Received part of response body (' . strlen($data_part) . ')!' . EOL;
	return true; //Return false to abort body request and return from sendRequest function
});

//Run request and echo nothing, as all processing is inside callbacks
$sock->sendRequest(WebRequest::createFromUrl('http://php.net'));

echo 'Done!';