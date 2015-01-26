<?php
require '../websock2.php';

define('EOL', php_sapi_name() === 'cli' ? PHP_EOL : '<br>');

//Create HTTP request manager, supply it with socket and cookie manager
$manager = new HttpRequestManager(new FileSocket, new HttpCookieManager);

//ALl calls to runRequest now will automatically process HTTP redirections and track cookies

//Add some cookies
$manager->runRequest(WebRequest::createFromUrl('http://httpbin.org/cookies/set?cookie_name=cookie_value&one_more=789'));

//And more
$manager->runRequest(WebRequest::createFromUrl('http://httpbin.org/cookies/set?third_cookie=third_value'));

//Now change the value of a cookie
$manager->runRequest(WebRequest::createFromUrl('http://httpbin.org/cookies/set?third_cookie=changed'));

//And delete one cookie
$manager->runRequest(WebRequest::createFromUrl('http://httpbin.org/cookies/delete?cookie_name'));

//Add our own cookie from code
$manager->getCookieManager()->addCookie(new HttpCookie('code_cookie', '12345'));

//Add one more cookie from code,
//but this will be for different domain, so it will not be shown on page
$cookie = new HttpCookie('other_domain_cookie', '12345');
$cookie->setDomain('php.net');
$manager->getCookieManager()->addCookie($cookie);

//Enumerate cookies and list them
$manager->getCookieManager()->filterCookies(function(HttpCookie $cookie)
{
	echo 'We have a cookie: ' . $cookie->getName() . '=' . $cookie->getValue()
		. ' for domain: [' . $cookie->getDomain()
		. '] and path: [' . $cookie->getPath() . ']' . EOL;
	
	//Lets remove "one_more" cookie!
	if($cookie->getName() === 'one_more')
		return HttpCookieManager::FILTER_REMOVE_COOKIE_NEXT;
	
	return HttpCookieManager::FILTER_NEXT;
});

echo EOL;

//Run request and echo response body contents (without headers)
//This will show something like { "cookies": { "code_cookie": "12345", "third_cookie": "changed" } } 
echo $manager->runRequest(WebRequest::createFromUrl('http://httpbin.org/cookies'))->getBody();
