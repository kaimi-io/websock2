<?php
require '../websock2.php';

//Create file socket
$socket = new FileSocket;

//Create new request
$request = WebRequest::createFromUrl('http://httpbin.org/post');

//Change method to POST
//If you comment out this line, request will be sent with GET method
$request->setMethod(WebRequest::METHOD_POST);

//Get parameter manager
$params = $request->getParamManager();

//Add some parameters
$params['test_param'] = 123;
$params['test_array'] = ['a', 'b', 'c'];

//Run request and echo response body contents (without headers)
echo $socket->sendRequest($request)->getBody();
