<?php
namespace Starryseer\Io\Main;

use Starryseer\Io\Select\Select;

include '../../vendor/autoload.php';

$server = new Select("tcp://0.0.0.0:8000",false);
$server->start();