<?php
require '../websock2.php';

define('EOL', php_sapi_name() === 'cli' ? PHP_EOL : '<br>');

//Create HTTP request manager and supply it with socket
$manager = new HttpRequestManager(new FileSocket);

//Set up redirection interception
$manager->setOnRedirectCallback(function(WebRequest $original_request, WebResponse $response,
	WebRequest $new_request, $http_code, HttpRequestManager $manager)
{
	echo 'We are redirected to: ' . $new_request->getFullAddress() . EOL;
	return true; //Return false to abort redirect and return from runRequest
});

//This request will redirect us 6 times
echo $manager->runRequest(WebRequest::createFromUrl('http://httpbin.org/redirect/6'))->getBody();
