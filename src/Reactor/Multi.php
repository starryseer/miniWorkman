<?php


namespace Starryseer\Io\Reactor;

use \Swoole\Event;

class Multi
{
    public $addr;
    public $socket;
    public $worker_pids = [];
    public $onConnect;
    public $onReceive;
    public $config = null;

    public function __construct($addr)
    {
        $this->addr = $addr;
    }

    public function set($config)
    {
        $this->config = $config;
    }

    public function fork()
    {
        if(!isset($this->config['worker_num']) or $this->config['worker_num'] <=0)
            $this->config['worker_num'] = 1;
        for($i=1;$i<=$this->config['worker_num'];$i++)
        {
            $pid = \pcntl_fork();
            if($pid <0)
            {
                throw new \Exception('fork fail');
            }
            elseif($pid>0)
            {
                $this->worker_pids[(int)$pid] = $pid;
                var_dump($this->worker_pids);
            }
            else
            {
                $this->accept();
                exit;
            }
        }
    }

    public function createSocket()
    {
        try{
            $context = stream_context_create([
                'socket' => [
                    // 设置等待资源的个数
                    'backlog' => '102400',
                ],
            ]);
            // 设置端口可以重复监听
            \stream_context_set_option($context, 'socket', 'so_reuseport', 1);

            // 传递一个资源的文本 context
            return $this->socket = stream_socket_server($this->addr , $errno , $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);

        }
        catch (\Exception $e)
        {
            echo $e->getMessage();
        }

    }

    public function start()
    {
        $this->fork();
        $this->monitorWorkersForLinux();
    }

    public function monitorWorkersForLinux()
    {
        pcntl_signal(SIGUSR1, [$this, 'sigHandler'],false);
        pcntl_signal(SIGINT, [$this, 'sigHandler'], false);
//        pcntl_signal(SIGKILL, [$this, 'sigHandler']);
        $status = 0;
        while(true)
        {
            \pcntl_signal_dispatch();
            \pcntl_wait($status);
            \pcntl_signal_dispatch();
        }
    }

    public function reload(){
        $this->stop();
        $this->fork();
    }

    public function stop($flag = false){
        echo $this->worker_pids;
        foreach($this->worker_pids as $pid)
        {
            \posix_kill($pid,9);
        }
        $this->worker_pids = [];
        if($flag)
            \posix_kill(\posix_getpid(),9);
    }

    public function sigHandler($sig)
    {
        echo $sig;
        switch ($sig) {
            case SIGUSR1:
                //重启
                $this->reload();
                break;
            case SIGINT:
                // 停止
                $this->stop(true);
                break;
        }
    }

    public function accept()
    {
        $this->createSocket();
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