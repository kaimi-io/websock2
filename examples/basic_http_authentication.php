<?php
require '../websock2.php';

//Create request manager, supply it with socket and cookie manager
$manager = new HttpRequestManager(new FileSocket, new HttpCookieManager);

//Add authentication data for all realms
$manager->addAuthData(null, 'user', 'passwd');

//Run request and echo response body contents (without headers)
//This will automatically process basic authentication
echo $manager->runRequest(WebRequest::createFromUrl('http://httpbin.org/basic-auth/user/passwd'))->getBody();

//Run request and echo response body contents (without headers)
//This will automatically process digest "auth-int" authentication
echo $manager->runRequest(WebRequest::createFromUrl('http://httpbin.org/digest-auth/auth-int/user/passwd'))->getBody();
