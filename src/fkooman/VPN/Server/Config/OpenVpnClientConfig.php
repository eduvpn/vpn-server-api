<?php
/**
 * Copyright 2015 François Kooman <fkooman@tuxed.net>.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace fkooman\VPN\Server\Config;

class OpenVpnClientConfig extends ClientConfig
{
    public function get($commonName)
    {
        $configData = parent::get($commonName);

        if (false === $configData) {
            return ['disable'];
        }

        $configDataArray = [
            sprintf('ifconfig-push %s %s', $configData['v4'], $configData['v4_netmask']),
            sprintf('ifconfig-ipv6-push %s/64 %s', $configData['v6'], $configData['v6_gw']),
        ];

        if ($configData['default_gw']) {
            $configDataArray[] = 'push "redirect-gateway def1 bypass-dhcp"';

            # for Windows clients we need this extra route to mark the TAP adapter as 
            # trusted and as having "Internet" access to allow the user to set it to 
            # "Home" or "Work" to allow accessing file shares and printers
            $configDataArray[] = 'push "route 0.0.0.0 0.0.0.0"';

            # for iOS we need this OpenVPN 2.4 "ipv6" flag to redirect-gateway
            # See https://docs.openvpn.net/docs/openvpn-connect/openvpn-connect-ios-faq.html
            $configDataArray[] = 'push "redirect-gateway ipv6"';

            # we use 2000::/3 instead of ::/0 because it seems to break on native IPv6 
            # networks where the ::/0 default router already exists
            $configDataArray[] = 'push "route-ipv6 2000::/3"';
            foreach ($configData['dns'] as $dnsAddress) {
                $configDataArray[] = sprintf('push "dhcp-option DNS %s"', $dnsAddress);
            }
        } else {
            foreach ($configData['dst_net4'] as $dstNet4) {
                $net4 = new IP($dstNet4);
                $configDataArray[] = sprintf('push "route %s %s"', $net4->getNetwork(), $net4->getNetmask());
            }
            foreach ($configData['dst_net6'] as $dstNet6) {
                $configDataArray[] = sprintf('push "route-ipv6 %s"', $dstNet6);
            }
        }

        return $configDataArray;
    }
}
