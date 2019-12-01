<?php

namespace Kamly\DomainParser;

/**
 * 通过 socket 请求 指定 DNS 服务器，获取需要解析域名的 IP 地址
 * Class Dns
 */
class DomainTcpParser

{
    /** @var string */
    private $domain;

    /**  @var int */
    private $query_length = 0;

    /**
     * 初始化类
     * DomainTcpParser constructor.
     * @param $domain
     * @throws \Exception
     */
    public function __construct($domain)
    {
        $this->domain = Tool::normalizeDomain($domain); // 格式化 domain
    }

    /**
     * 编码需要发送的内容，返回二进制内容
     * @return mixed
     */
    public function encode()
    {
        /*
         * Header format 12b :
         * Message ID 2b  0 - 65535-1 10进制
         * FLAG => QR|opcode|AA|TC|RD|RA|Z|RCODE 2b 0x0100 16进制
         * QDCOUNT   2b  0x0001
         * ANCOUNT   2b  0x0000
         * NSCOUNT   2b  0x0000
         * ARCOUNT   2b  0x0000
         */
        // 短整型（16位） 转 二进制
        $domain_str = pack('n6', rand(1, 10000), 0x0100, 0x0001, 0x0000, 0x0000, 0x0000);
        $this->query_length = 12; // 12字节，后面会直接乘8变成字 1b->8bit

        /*
         * Question
         * QNAME
         * 03 77 77 77 05 61 70 70 6c 65 03 63 6f 6d 00
         * 3www5apple3com0
         * QTYPE 0x0001
         * QCLASS 0x0001
         */
        $domain_array = explode(".", $this->domain);
        foreach ($domain_array as $k => $v) {
            $str_len = strlen($v);
            $domain_str .= pack("C", $str_len); // 数字 转 二进制
            $this->query_length += 1;
            for ($i = 0; $i < $str_len; $i++) {
                $char = ord($v[$i]); // 字符 转换 ASCII值
                $domain_str .= pack('C', $char);  // ASCII值 转 二进制
                $this->query_length += 1;
            }
        }
        $domain_str .= pack('C', 0);  // 结束
        $this->query_length += 1;

        // 短整型（16位） 转 二进制
        $domain_str .= pack("n2", 0x0001, 0x0001);
        $this->query_length += 4;

        $encode_len = $this->query_length * 8;

        $encode['msg'] = $domain_str;
        $encode['len'] = $encode_len;

        // var_dump(bin2hex($encode['msg'])); // 二进制 转 十六进制

        return $encode;
    }

    /**
     * 解码收到的内容
     * @param $code
     * @return array
     */
    public function decode($code)
    {
        // 设置默认返回值
        $ret = [
            'status' => 0,
            'ra' => 0,
            'resnum' => 0,
            'list' => [],
        ];

        $code = bin2hex($code); // 把二进制转为十六进制

        // 前面都是一样，多了一个 Answer

        // Message ID
        $id = substr($code, 0, 4);

        // FLAG
        $flag = substr($code, 4, 4);
        $flag = base_convert($flag, 16, 2); // 重新将十六进制字符串转换为二进制
        // 判断最后 4bit是否为0。获取最后4位，然后从二进制转十进制进行判断。
        $rcode = substr($flag, -4);
        $rcode = base_convert($rcode, 2, 10);
        if ($rcode != 0) {
            return $ret; // 解析失败
        }
        $ret['status'] = 1;//解析成功

        // 判断是否支持 支持递归查询。
        $ret['ra'] = $flag[8] == 1 ? 1 : 0; // 1支持递归查询，0不支持递归查询

        // 回答数量 ANCOUNT
        $ret['resnum'] = hexdec(substr($code, 12, 4)); // 截取内容，然后十六进制转十进制

        // query 的长度  1个b有2位所以乘2，
        $query_length = $this->query_length * 2;

        // 需要根据回答数量进行区分
        for ($i = 0; $i < $ret['resnum']; $i++) {
            $data_length = 0;

            // 根据DNS协议的类型进行处理  十六进制转十进制
            $qtype = hexdec(substr($code, $query_length + 4, 4));

            switch ($qtype) {
                case 1:
                    // DNS协议的类型
                    $ret['list'][$i]['qtype'] = 1;
                    // class in 表示RDATA的类
                    $class = hexdec(substr($code, $query_length + 8, 4));
                    // time to live 表示资源记录可以缓存的时间
                    $ret['list'][$i]['ttl'] = hexdec(substr($code, $query_length + 12, 8)); // 截取内容，然后十六进制转十进制
                    // data_length 表示RDATA的长度
                    $data_length = hexdec(substr($code, $query_length + 20, 4)); // 截取内容，然后十六进制转十进制
                    // rdata
                    $rdata_data = substr($code, $query_length + 24, $data_length * 2);

                    // 解析具体内容
                    $ret['list'][$i]['ip'] = $this->decode_ip($rdata_data);
                    break;
                case 5:
                    // DNS协议的类型
                    $ret['list'][$i]['qtype'] = 5;
                    // class in 表示RDATA的类
                    $class = hexdec(substr($code, $query_length + 8, 4));
                    // time to live 表示资源记录可以缓存的时间
                    $ret['list'][$i]['ttl'] = hexdec(substr($code, $query_length + 12, 8)); // 截取内容，然后十六进制转十进制
                    // data_length 表示RDATA的长度
                    $data_length = hexdec(substr($code, $query_length + 20, 4)); // 截取内容，然后十六进制转十进制
                    // rdata
                    $rdata_data = substr($code, $query_length + 24, $data_length * 2);

                    // 解析具体内容
                    $ret['list'][$i]['cname'] = $this->decode_cname($code, $rdata_data);
                    break;
                default:
                    break;
            }
            // 跟新 $query_length
            $query_length = $query_length + 4 + 4 + 4 + 8 + 4 + $data_length * 2;
        }
        return $ret;
    }

    /**
     * 解码cname，还不清楚原理
     * @param $code
     * @param $rdata_data
     * @return string
     */
    public function decode_cname($code, $rdata_data)
    {
        $domain = '';

        for ($i = 0; $i < strlen($rdata_data); $i = $i + 2) {
            $num = hexdec($rdata_data[$i] . $rdata_data[$i + 1]);
            if (48 < $num && $num < 122) {
                $domain .= pack("h*", $rdata_data[$i + 1] . $rdata_data[$i]); // 十六进制字符串 转 二进制
            } else {
                if ($rdata_data[$i + 1] . $rdata_data[$i] == '0c') {
                    // 存在标记位，在query里面解码内容
                    $position = hexdec($rdata_data[$i + 2] . $rdata_data[$i + 3]) * 2; // 开始位置
                    $offset = substr($code, $position); // 先截断
                    $end = strpos($offset, '00'); // 结束位置
                    $offset_domain = substr($code, $position, $end); // 截断最后的位置

                    for ($k = 0; $k < strlen($offset_domain); $k = $k + 2) {
                        $offset_num = hexdec($offset_domain[$k] . $offset_domain[$k + 1]);
                        if (48 < $offset_num && $offset_num < 122) {
                            $domain .= pack("h*", $offset_domain[$k + 1] . $offset_domain[$k]); // 十六进制字符串 转 二进制
                        } else {
                            $domain .= '.';
                        }
                    }
                } else {
                    $domain .= '.';
                }
            }
        }
        return trim($domain, '.');
    }

    /**
     * 解码IP
     * @param $rdata_data
     * @return string
     */
    public function decode_ip($rdata_data)
    {
        $ip = '';
        for ($i = 0; $i < strlen($rdata_data); $i = $i + 2) {
            $ip .= hexdec($rdata_data[$i] . $rdata_data[$i + 1]) . '.';
        }
        return trim($ip, '.'); // 移除两侧
    }
}