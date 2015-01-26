<?php
require '../websock2.php';

define('EOL', php_sapi_name() === 'cli' ? PHP_EOL : '<br>');

//Create file socket
$socket = new FileSocket;

//Create request
$request = WebRequest::createFromUrl('http://httpbin.org/digest-auth/auth-int/user/passwd');

//Run request
//This request will end with Unauthorized response
$response = $socket->sendRequest($request);

echo $response->getHeadersData() . EOL . EOL;

//Now we will authenticate manually
$response_headers = new HttpHeaderManager($response->getHeadersData());

//Check authentication method
if($response_headers->getAuthenticationType() !== HttpHeaderManager::HTTP_AUTHENTICATION_DIGEST)
	die('Incorrect authentication method!');

//We need to set boundary manually, as in case of digest authentication server may request MD5 checksum of all
//request data.
$request->setBoundary(md5(mt_rand()));
$request->setDigestAuthenticationCredentials($response_headers->getAuthenticationOptions(), 'user', 'passwd');

//This is needed, as httpbin checks for this cookie (it is set on first request)
$request->getHeaderManager()->replaceHeader('Cookie', 'fake=fake_value');

//Run request again, we are now authenticated
echo $socket->sendRequest($request)->getHeadersData();

//This ALL could be performed in two lines of code also. See basic_http_authentication.php example.