<?php
require '../websock2.php';

//Create file socket
$socket = new FileSocket;

//Create SOCKS5 proxy
$proxy1 = new Socks5Proxy('proxy.address.here', 3128);

//You can set authentication data for any proxies in chain
$proxy1->setAuth('login', 'password');

//Create SOCKS4a proxy
$proxy2 = new Socks4aProxy('socks4a.proxy.address', 808);

//Create HTTP proxy
$proxy3 = new HttpProxy('http.proxy.address', 777);

//Build proxy chain
//This will create such chain:
//computer -> http.proxy.address:777 -> socks4a.proxy.address:808 -> proxy.address.here:3128 -> http://php.net
$proxy3->setSocket($proxy2);
$proxy2->setSocket($proxy1);
$proxy1->setSocket($socket);

//Run request via proxy chain and echo response body contents (without headers)
echo $proxy3->sendRequest(WebRequest::createFromUrl('http://php.net'))->getBody();
