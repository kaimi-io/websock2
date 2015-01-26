<?php
require '../websock2.php';

//Create file socket
$socket = new FileSocket;

//Create SOCKS5 proxy
//You can also create HttpProxy, Socks4AProxy and Socks4Proxy
$proxy = new Socks5Proxy('proxy.address.here', 3128);

//Set authentication data
$proxy->setAuth('login', 'password');

//Assign socket to proxy
$proxy->setSocket($socket);

//Run request via proxy and echo response body contents (without headers)
echo $proxy->sendRequest(WebRequest::createFromUrl('http://php.net'))->getBody();
