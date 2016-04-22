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

namespace fkooman\VPN\Server;

use RuntimeException;

class Utils
{
    public static function exec($cmd, $throwExceptionOnFailure = true)
    {
        exec($cmd, $output, $returnValue);

        if (0 !== $returnValue) {
            if ($throwExceptionOnFailure) {
                throw new RuntimeException(
                    sprintf('command "%s" did not complete successfully (%d)', $cmd, $returnValue)
                );
            }
        }
    }

    /**
     * @param string $configData the current OpenVPN configuration file with
     *                           keys and certificates
     *
     * @return array the extracted keys and certificates
     */
    public static function extractCertificates($configData)
    {
        $serverConfig = [];

        foreach (array('cert', 'ca', 'key', 'tls-auth', 'dh') as $inlineType) {
            $pattern = sprintf('/\<%s\>(.*)\<\/%s\>/msU', $inlineType, $inlineType);
            if (1 !== preg_match($pattern, $configData, $matches)) {
                throw new DomainException('inline type not found');
            }
            $serverConfig[$inlineType] = trim($matches[1]);
        }

        $parsedCert = openssl_x509_parse($serverConfig['cert']);
        $serverConfig['valid_from'] = $parsedCert['validFrom_time_t'];
        $serverConfig['valid_to'] = $parsedCert['validTo_time_t'];
        $serverConfig['cn'] = $parsedCert['subject']['CN'];

        return $serverConfig;
    }
}
