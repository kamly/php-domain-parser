<?php

namespace Kamly\DomainParser;

use Kamly\DomainParser\Exceptions\Exception;
use Kamly\DomainParser\Exceptions\HttpException;

class Manager
{
    protected $socket = '';
    protected $RETRY_TIME = 5;

    public function __construct()
    {
        $this->socket = (new TcpSocket())->getSocket();
    }

    /**
     * @param string $domain
     * @param array $options
     * @return array
     * @throws HttpException
     */
    public function resolve(string $domain, array $options = [], int $retry_time = 0)
    {
        // 重试次数超过规定次则跳出
        if ($retry_time > $this->RETRY_TIME) {
            throw new HttpException('resolve retry over 5 times');
        }

        $this->socket->setOptions($options)->getSocket(); // 获取 socket

        $parser = new DomainTcpParser($domain); // 初始化 DomainTcpParser 类

        $this->socket->send($parser->encode());  // 编码发送的内容 + 发送

        try {
            $msg = $this->socket->receive();
        } catch (Exception $e) {
            if ($e->getMessage() == "Resource temporarily unavailable") {
                return $this->resolve($domain, $options, ++$retry_time);
            }
            throw new HttpException($e->getMessage());
        }

        return $parser->decode($msg); // 接受 + 解码接收到的内容
    }
}