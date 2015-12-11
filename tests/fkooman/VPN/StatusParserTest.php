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

use PHPUnit_Framework_TestCase;
use fkooman\Json\Json;
use fkooman\IO\IO;

class StatusParserTest extends PHPUnit_Framework_TestCase
{
    public function testList()
    {
        $io = new IO();

        $fileList = array(
            __DIR__.'/data/no_clients.txt',
            __DIR__.'/data/one_client.txt',
#            __DIR__.'/data/three_clients.txt',
        );

        foreach ($fileList as $fileName) {
            $outputFile = $fileName.'.output';

            $statusParser = new StatusParser('tcp://localhost:7505', $io->readFile($fileName));

#            var_dump(Json::encode($statusParser->getClientInfo()));

            $this->assertSame(
                Json::decodeFile($outputFile),
                $statusParser->getClientInfo()
            );
        }
    }
}
