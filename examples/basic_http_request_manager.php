<?php
require '../websock2.php';

//Create HTTP request manager and supply it with socket
$manager = new HttpRequestManager(new FileSocket);

//Run request and echo response body contents (without headers)
//This call will automatically process HTTP redirections and set up referers
//(but not yet process cookies, as HttpCookieManager is not set for HttpRequestManager in this example)
echo $manager->runRequest(WebRequest::createFromUrl('http://php.net'))->getBody();
