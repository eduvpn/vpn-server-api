<?php

namespace fkooman\VPN;

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
