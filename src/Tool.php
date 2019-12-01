<?php

namespace Kamly\DomainParser;


use Kamly\DomainParser\Exceptions\InvalidArgumentException;

class Tool
{
    /**
     * @param string $domain
     * @return string
     * @throws InvalidArgumentException
     */
    public static function normalizeDomain(string $domain)
    {
        if ($domain == '') {
            throw new InvalidArgumentException('Domain name cannot be empty');
        }

        // 可能传入  https://charmingkamly.cn/xxx https://139.199.179.114/xxx   charmingkamly.cn 139.199.179.114
        $tmp = parse_url($domain);
        $host = empty($tmp['host']) ? $domain : $tmp['host'];

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            // 如果 host 是 ip
            throw new InvalidArgumentException('Domain is ip not allow');
        }

        return $host;
    }
}