<?php
require '../websock2.php';

//Create file socket
$socket = new FileSocket;

//Create new request
//Note that httpbin currently shows array of attachments incorrectly
$request = WebRequest::createFromUrl('http://httpbin.org/post');

//Change method to POST
$request->setMethod(WebRequest::METHOD_POST);

//Get parameter manager
$params = $request->getParamManager();

//Add some parameters
$params['test_param'] = 123;
$params['test_array'] = ['a', 'b', 'c'];

//Now add some file attachments
//First is attachment made up from string
$params['test_file'] = new HttpContentAttachment('myfile.txt', 'contents of file');

//Second is real file attachment
$params['test_file_2'] = new HttpFileAttachment('test.txt', './test.txt');

//You can set content-type of attachment
$params['test_file_2']->setContentType('text/plain');

//It is also possible to create an array of attachments:
$params['array_of_attachments'] = [
	new HttpContentAttachment('myfile2.txt', 'first file in array'),
	new HttpContentAttachment('myfile3.txt', 'second file in array')
];

//Run request and echo response body contents (without headers)
echo $socket->sendRequest($request)->getBody();
