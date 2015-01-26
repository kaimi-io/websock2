<?php
require '../websock2.php';

define('EOL', php_sapi_name() === 'cli' ? PHP_EOL : '<br>');

//Create file socket
$socket = new FileSocket;

//This will set timeout to 60 seconds
//By default, timeout is calculated for total time spent in socket functions
$socket->setTimeout(60);

//It is possible to change timeout calculation mode

//Now timeout will be calculated for EACH socket function separately
//This means, each socket function call will have 60 seconds to complete
$socket->setTimeoutMode(NetworkSocket::TIMEOUT_MODE_EVERY_OPERATION);

//Now timeout will be calculated for total time of socket usage, from open to last socket operation
//This means, if you call open, then do sleep(10) and then call read, read operation will have only 50 seconds to complete
//(guessing connect was completed immediately) and all operations after read will have even less time to complete (50 - time for read).
$socket->setTimeoutMode(NetworkSocket::TIMEOUT_MODE_TOTAL);

//Go to default mode
$socket->setTimeoutMode(NetworkSocket::TIMEOUT_MODE_SUM_OF_OPERATIONS);

//Run request and echo response headers
echo $socket->sendRequest(WebRequest::createFromUrl('http://php.net'))->getHeadersData();


//There is another sockets implementation:
$socket = new LowLevelSocket;

try
{
//This one is able to track timeouts very accurately and break exactly when timeout occures
//But it is not able to open HTTPS
//So, this call will throw error
echo $socket->sendRequest(WebRequest::createFromUrl('https://php.net'))->getBody();
}
catch(WebRequestException $e)
{
	echo EOL . EOL . 'Error: ' . $e->getMessage();
}
