<?php


namespace Starryseer\Io\Select;


class Select
{
    public $socket;
    public $read_socket = [];
    public function __construct($addr,$block=false)
    {
        $this->socket = \stream_socket_server($addr);
        if($block)
            \stream_set_blocking($this->socket,$block);
        $this->read_socket[(int)$this->socket]=$this->socket;
    }

    public function start()
    {
        $write = $except = [];

        while(true)
        {
            $read = $this->read_socket;
            if(\stream_select($read,$write,$except,0)>0)
            {
                foreach($read as $read_so)
                {
                    if($read_so == $this->socket )
                        $this->accept();
                    else
                        $this->sendMessage($read_so);
                }
            }
        }

    }

    public function accept()
    {
        $socket_client = \stream_socket_accept($this->socket);
        $this->read_socket[(int)$socket_client] = $socket_client;
    }

    public function sendMessage($read_so)
    {
        if(!is_resource($read_so))
        {
            $this->closeSocket($read_so);
            return;
        }

        $content = \fread($read_so,99);
        if(empty($content))
        {
            $this->closeSocket($read_so);
            return;
        }

        echo $content."\n";
        $this->send($read_so,$content);
    }

    public function closeSocket($socket)
    {
        unset($this->read_socket[(int)$socket]);
        \fclose($socket);
    }

    public function send($read_so,$content)
    {
        $response = "HTTP/1.1 200 OK\r\n";
        $response .= "Content-Type: text/html;charset=UTF-8\r\n";
        $response .= "Connection: keep-alive\r\n";
        $response .= "Content-length: ".strlen($content)."\r\n\r\n";
        $response .= $content;
        fwrite($read_so, $response);
    }
}