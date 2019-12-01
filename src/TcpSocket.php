<?php

namespace Kamly\DomainParser;

use Kamly\DomainParser\Exceptions\HttpException;
use Kamly\DomainParser\Exceptions\InvalidArgumentException;

class TcpSocket
{

    const DNS_IP = 'dns_ip';
    const DNS_PORT = 'dns_port';
    const SOCKET = 'socket';
    const RCV_TIME = 'rcv_time';
    const SND_TIME = 'snd_time';
    const RETRY_TIME = 'retry_time';


    /**
     * resource
     * @var
     */
    private $socket;

    /**
     * array
     * @var
     */
    private $options = [
        'dns_ip' => '119.29.29.29',
        'dns_port' => 53,
        'socket' => [
            'rcv_time' => ['sec' => 1, 'usec' => 0], // 指定 socket 连接 接收超时时间
            'snd_time' => ['sec' => 1, 'usec' => 0], // 指定 socket 连接 发送超时时间
        ],
        'retry_time' => 3
    ];

    /**
     * 批量设置属性
     * @param array $options
     * @return $this
     */
    public function setOptions(array $options)
    {
        $this->options = array_merge($this->options, $options);

        return $this;
    }

    /**
     * 创建 socket
     * @throws \Exception
     */
    public function getSocket()
    {
        $this->initSocket(); // 初始化 socket

        $this->setSocketOptions(); // 设置 socket 属性

        return $this;
    }

    /**
     * 初始化 socket
     * @throws \Exception
     */
    protected function initSocket()
    {
        if ($this->socket != '') {
            return;
        }

        // 三个参数意义:
        // 指定哪个协议用在当前套接字上 IPv4 网络协议 ,
        // 用于选择套接字使用的类型 提供数据报文的支持(UDP协议即基于这种数据报文套接字) ,
        // 指定 domain 套接字下的具体协议 UDP
        $this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

        if ($this->socket == false) {
            $errorCode = socket_last_error();
            $errorMsg = socket_strerror($errorCode);
            throw new HttpException($errorMsg);
        }

        // 解决地址重用
        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
    }

    /**
     * 设置 socket 属性
     */
    protected function setSocketOptions()
    {
        // 接收超时
        socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, $this->options[TcpSocket::SOCKET][TcpSocket::RCV_TIME]);

        // 发送超时
        socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, $this->options[TcpSocket::SOCKET][TcpSocket::SND_TIME]);
    }

    /**
     * 发送
     * @param $msg
     * @throws \Exception
     */
    public function send($msg)
    {
        if (!$this->options[TcpSocket::DNS_IP] || !$this->options[TcpSocket::DNS_PORT] || !$this->socket || !$msg) {
            throw new InvalidArgumentException('dns_ip, port or msg is empty not allow');
        }

        socket_sendto($this->socket,
            $msg['msg'], $msg['len'], 0,
            $this->options[TcpSocket::DNS_IP],
            $this->options[TcpSocket::DNS_PORT]);
    }

    /**
     * 接收
     * @param int $retry_time
     * @return mixed
     * @throws HttpException
     */
    public function receive(int $retry_time = 0)
    {
        // 重试次数超过规定次则跳出
        if ($retry_time > $this->options[TcpSocket::RETRY_TIME]) {
            throw new HttpException('socket_recvfrom retry over 3 times');
        }

        $from = ''; // socket返回响应ip
        $port = 0; // socket返回响应端口

        $res = socket_recvfrom($this->socket, $buf, 1024, 0, $from, $port);

        if ($res == false) {
            $errorCode = socket_last_error();
            $errorMsg = socket_strerror($errorCode);
            throw new HttpException($errorMsg);
        }

        if ($from == "" && $port == "") {
            return $this->receive(++$retry_time); // 再次接受内容
        }

        return $buf;
    }

    public function __destruct()
    {
        socket_close($this->socket);
    }
}