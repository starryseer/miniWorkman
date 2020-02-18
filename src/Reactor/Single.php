<?php


namespace Starryseer\Io\Reactor;

use \Swoole\Event;

class Single
{
    public $socket;
    public $onConnect;
    public $onReceive;

    public function __construct($addr)
    {
        $this->socket = \stream_socket_server($addr);
    }

    public function start()
    {
        $this->accept();
    }

    public function accept()
    {
        Event::add($this->socket, $this->_accept());
    }

    public function _accept()
    {
        return function($socket) {
            $conn = @stream_socket_accept($this->socket);
            if(is_resource($conn))
            {
                if(is_callable($this->onConnect))
                    ($this->onConnect)($this,$conn);
                Event::add($conn,$this->receive());
            }
        };
    }

    public function receive()
    {
        return function($socket)
        {
            if(!is_resource($socket) or feof($socket))
            {
                swoole_event_del($socket);
                fclose($socket);
                return;
            }
            $data = fread($socket, 1024);
            if(is_callable($this->onReceive))
                ($this->onReceive)($this,$socket,$data);

        };
    }

    public function send($conn, $content){
        $http_resonse = "HTTP/1.1 200 OK\r\n";
        $http_resonse .= "Content-Type: text/html;charset=UTF-8\r\n";
        $http_resonse .= "Connection: keep-alive\r\n";
        $http_resonse .= "Server: php socket server\r\n";
        $http_resonse .= "Content-length: ".strlen($content)."\r\n\r\n";
        $http_resonse .= $content;
        fwrite($conn, $http_resonse);
    }
}