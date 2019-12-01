
## Dns

目的：通过 socket 请求 指定 DNS 服务器，获取需要解析域名的 IP 地址

解决痛点：php 解决 gethostbyname 没法指定 dns 服务器地址

## 目前

处理 DNS协议的类型 1 和 5 

待续处理 ipv6 ?

## 安装

```sh
$ composer require kamly/domain-parser -vvv
```

## 使用

```php
<?php

require_once './vendor/autoload.php';

use Kamly\DomainParser\Manager;

// 正确用法 指定域名
var_dump((new Manager())->resolve('charmingkamly.cn')); 

// 正确用法 指定网址
var_dump((new Manager())->resolve('https://charmingkamly.cn/test.php')); 

// 正确用法 指定网址 ， 具体 DNS 服务器 和 相关参数
var_dump((new Manager())->resolve('https://charmingkamly.cn/test.php', [
    'dns_ip' => '119.29.29.29',
    'dns_port' => '53', // default: 53
    'socket' => [
        'rcv_time' => ['sec' => 0.5, 'usec' => 0], // 指定 socket 连接 接收超时时间
        'snd_time' => ['sec' => 0.5, 'usec' => 0], // 指定 socket 连接 发送超时时间
    ],
    'retry_time' => 3, // 尝试接受包的次数
]));

/*
 * 具体 output 内容
array(4) {
  ["status"]=>
  int(1)
  ["ra"]=>
  int(1)
  ["resnum"]=>
  int(1)
  ["list"]=>
  array(1) {
    [0]=>
    array(3) {
      ["qtype"]=>   // DNS协议的类型
      int(1)
      ["ttl"]=>     // time to live 表示资源记录可以缓存的时间
      int(600)
      ["ip"]=>      // 解析具体内容
      string(15) "139.199.179.114"
    }
  }
}
*/

// 错误用法 指定具体 IP 地址
try {
    (new Manager())->resolve('https://139.199.179.114/test.php');   // error reason
} catch (Exception $exception) {
    var_dump($exception->getMessage()); 
}
// Domain is ip not allow

// 错误用法 DNS 服务器有误，默认使用 腾讯的公共 DNS 服务器 - 119.29.29.29
try {
    (new Manager())->resolve('https://charmingkamly.cn/test.php', [
        'dns_ip' => '192.168.1.2',  // error reason
        'dns_port' => 53,
        'socket' => [
            'rcv_time' => ['sec' => 0, 'usec' => 1],
            'snd_time' => ['sec' => 0, 'usec' => 1],
        ]
    ]);
} catch (Exception $exception) {
    var_dump($exception->getMessage());
}
// Resource temporarily unavailable
// [公共DNS可以在这里选其中一个 - 知乎](https://www.zhihu.com/question/32229915)
```

## 参考

[szulilin/php-dns](https://github.com/szulilin/php-dns)
