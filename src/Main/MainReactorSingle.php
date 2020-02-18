<?php
namespace Starryseer\Io\Main;

use Starryseer\Io\Reactor\Single;

include '../../vendor/autoload.php';

$server = new Single("tcp://0.0.0.0:8000");
$server->onConnect = function($socket,$client){
    echo 'connect success'."\n";
};
$server->onReceive = function($socket,$client,$data){
    echo 'receive:'.$data."\n";
    $socket->send($client,'hello');
};
$server->start();