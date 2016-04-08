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

    public static function writeTempConfig($tmpConfig, array $configFileData)
    {
        if (false === @file_put_contents($tmpConfig, implode(PHP_EOL, $configFileData))) {
            throw new RuntimeException('unable to write temporary config file');
        }
    }
}
