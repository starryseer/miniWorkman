<?php
namespace Starryseer\Io\Main;

use Starryseer\Io\Reactor\MultiTask;

include '../../vendor/autoload.php';

$server = new MultiTask("tcp://0.0.0.0:8000");
$server->set([
    'worker_num'=>4,
    'task_num'=>4
]);
$server->onConnect = function($socket,$client){
    echo 'connect success'."\n";
};
$server->onReceive = function($socket,$client,$data){
    echo 'receive:'.$data."\n";
    $socket->task('hello task',1);
    $socket->send($client,'hello');
};
$server->onTask = function($socket,$data){
    echo 'task:'.$data."\n";
};
$server->start();