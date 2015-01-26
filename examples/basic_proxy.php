<?php
require '../websock2.php';

//Create file socket
$socket = new FileSocket;

//Create HTTP proxy
//You can also create Socks5Proxy, Socks4AProxy and Socks4Proxy
$proxy = new HttpProxy('proxy.address.here', 3128);

//Assign socket to proxy
$proxy->setSocket($socket);

//Run request via proxy and echo response body contents (without headers)
echo $proxy->sendRequest(WebRequest::createFromUrl('http://php.net'))->getBody();
