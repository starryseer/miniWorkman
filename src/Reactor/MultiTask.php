<?php


namespace Starryseer\Io\Reactor;

use \Swoole\Event;

class MultiTask
{
    public $addr;
    public $socket;
    public $worker_pids = [];
    public $task_pids = [];
    public $msg_key;
    public $msg_queue;
    public $onConnect;
    public $onReceive;
    public $onTask;
    public $config = null;

    public function __construct($addr)
    {
        $this->addr = $addr;
        $this->initTask();
    }

    public function set($config)
    {
        $this->config = $config;
    }

    public function forkTask()
    {
        if(!isset($this->config['task_num']) or $this->config['task_num'] <=0)
            return;

        for($i=1;$i<=$this->config['task_num'];$i++)
        {
            $pid = \pcntl_fork();
            if($pid <0)
            {
                throw new \Exception('fork fail');
            }
            elseif($pid>0)
            {
                $this->task_pids[(int)$i] = $pid;
            }
            else
            {
                $this->receiveTask($i);
            }
        }
    }

    public function initTask()
    {
        try{
            //生成一个消息队列的key
            $this->msg_key = isset($this->config['msg_key'])?ftok($this->config['msg_key'], 'u'):ftok(__FILE__, 'u');
            //产生一个消息队列
            $this->msg_queue = msg_get_queue($this->msg_key);
        }
        catch (\Exception $e)
        {
            echo $e->getMessage();
        }


    }

    public function receiveTask($i)
    {
        while(true)
        {
            msg_receive($this->msg_queue,1,$i,1024,$data);
            if(is_callable($this->onTask))
                ($this->onTask)($this,$data);
        }
    }

    public function task($data,$task_id)
    {
        msg_send($this->msg_queue,$task_id,$data);
    }

    public function forkWorker()
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
                $this->worker_pids[(int)$i] = $pid;
            }
            else
            {
                $this->accept();
                exit;
            }
        }
    }

    public function fork()
    {
        $this->forkWorker();
        $this->forkTask();
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
        $this->stopTask();
        $this->stopWorker();
        if($flag)
            \posix_kill(\posix_getpid(),9);
    }

    public function stopWorker()
    {
        foreach($this->worker_pids as $pid)
        {
            \posix_kill($pid,9);
        }
        $this->worker_pids = [];
    }

    public function stopTask()
    {
        foreach($this->task_pids as $pid)
        {
            \posix_kill($pid,9);
        }
        $this->task_pids = [];
    }

    public function sigHandler($sig)
    {
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