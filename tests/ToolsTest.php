<?php

use Kamly\DomainParser\Tool;
use Kamly\DomainParser\Exceptions\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ToolsTest extends TestCase
{
    public function testNormalizeDomainWithEmptyDomain()
    {
        $tools = new Tool();

        // 断言会抛出此异常类
        $this->expectException(InvalidArgumentException::class);
        // 断言异常消息为  内容不能多但能少
        $this->expectExceptionMessage('Domain name cannot be empty');

        $tools->normalizeDomain('');
    }

    public function testNormalizeDomainWithInvalidDomain()
    {
        $tools = new Tool();

        try {
            $tools->normalizeDomain('127.0.0.1');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals('Domain is ip not allow', $e->getMessage());
            return;
        }

        // 没抓到异常就算失败
        $this->fail('An expected exception has not been raised.');
    }

    public function testNormalizeDomain()
    {
        $tools = new Tool();

        $this->assertIsString($tools->normalizeDomain('qq.com'));
    }

}