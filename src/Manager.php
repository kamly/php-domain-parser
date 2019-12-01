<?php

namespace Kamly\DomainParser;


class Manager
{
    protected $socket = '';

    public function __construct()
    {
        $this->socket = (new TcpSocket())->getSocket();
    }

    /**
     * @param string $domain
     * @param array $options
     * @return array
     * @throws Exceptions\HttpException
     */
    public function resolve(string $domain, array $options = [])
    {
        $this->socket->setOptions($options)->getSocket(); // 获取 socket

        $parser = new DomainTcpParser($domain); // 初始化 DomainTcpParser 类

        $this->socket->send($parser->encode());  // 编码发送的内容 + 发送

        return $parser->decode($this->socket->receive()); // 接受 + 解码接收到的内容
    }
}