<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

/*
 * Generate example IPv4 and IPv6 address ranges for VPN clients.
 *
 * IPv4:
 * Random value for the second and third octet, e.g: 10.53.129.0/25
 *
 * IPv6:
 * The IPv6 address is generated according to RFC 4193 (Global ID), it results
 * in a /64 network.
 */

$showIpFour = true;
$showIpSix = true;
foreach ($argv as $arg) {
    if ('-6' === $arg) {
        $showIpFour = false;
    }
    if ('-4' === $arg) {
        $showIpSix = false;
    }
}

if ($showIpFour) {
    $ipFourPrefix = sprintf(
    '10.%s.%s.0/24',
        hexdec(bin2hex(random_bytes(1))),
        hexdec(bin2hex(random_bytes(1)))
    );
    echo $ipFourPrefix.PHP_EOL;
}

if ($showIpSix) {
    $ipSixPrefix = sprintf(
        'fd%s:%s:%s:%s::/64',
        bin2hex(random_bytes(1)),
        bin2hex(random_bytes(2)),
        bin2hex(random_bytes(2)),
        bin2hex(random_bytes(2))
    );
    echo $ipSixPrefix.PHP_EOL;
}
