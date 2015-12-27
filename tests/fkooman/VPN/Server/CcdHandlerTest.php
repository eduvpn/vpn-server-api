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

class CcdHandlerTest extends PHPUnit_Framework_TestCase
{
    private $ccdPath;

    public function setUp()
    {
        // get a directory to play with
        $tempDirName = tempnam(sys_get_temp_dir(), 'ccd');
        if (file_exists($tempDirName)) {
            @unlink($tempDirName);
        }
        @mkdir($tempDirName);

        // a not disabled commonName
        @file_put_contents($tempDirName.'/bar', "foo\nbar\n# disable\n# i am not disabled\ndisabled\n");

        // a disabled commonName
        @file_put_contents($tempDirName.'/foobar', "foo\nbar\n# disable\n# i am not disabled\ndisabled\ndisable\n");

        $this->ccdPath = $tempDirName;
    }

    public function testDisableNonExistingFile()
    {
        $ccd = new CcdHandler($this->ccdPath);
        $this->assertTrue($ccd->disableCommonName('foo'));
        $this->assertFileEquals(__DIR__.'/data/disabled', $this->ccdPath.'/foo');
    }

    public function testDisableExistingFile()
    {
        $ccd = new CcdHandler($this->ccdPath);
        $this->assertTrue($ccd->disableCommonName('bar'));
        $this->assertFileEquals(__DIR__.'/data/existing_disabled', $this->ccdPath.'/bar');
    }

    public function testDisableAlreadyDisabled()
    {
        $ccd = new CcdHandler($this->ccdPath);
        $this->assertFalse($ccd->disableCommonName('foobar'));
        $this->assertFileEquals(__DIR__.'/data/existing_disabled', $this->ccdPath.'/foobar');
    }

    public function testEnableExistingFile()
    {
        $ccd = new CcdHandler($this->ccdPath);
        $this->assertFalse($ccd->enableCommonName('bar'));
        $this->assertFileEquals(__DIR__.'/data/existing_enabled', $this->ccdPath.'/bar');
    }

    public function testEnableDisabledFile()
    {
        $ccd = new CcdHandler($this->ccdPath);
        $this->assertTrue($ccd->enableCommonName('foobar'));
        $this->assertFileEquals(__DIR__.'/data/existing_enabled', $this->ccdPath.'/foobar');
    }

    public function testEnableNonExistingFile()
    {
        $ccd = new CcdHandler($this->ccdPath);
        $this->assertFalse($ccd->enableCommonName('foo'));
        $this->assertFileNotExists($this->ccdPath.'/foo');
    }

    public function testGetDisabledCommonNames()
    {
        $ccd = new CcdHandler($this->ccdPath);
        $ccd->disableCommonName('a');
        $ccd->disableCommonName('b');
        $ccd->enableCommonName('b');
        $ccd->disableCommonName('c');

        $this->assertSame(
            array(
                'a',
                'c',
                'foobar',
            ),
            $ccd->getDisabledCommonNames()
        );
    }

    public function testGetDisabledCommonNamesByUser()
    {
        $ccd = new CcdHandler($this->ccdPath);
        $ccd->disableCommonName('userA_foo');
        $ccd->disableCommonName('userA_bar');
        $ccd->disableCommonName('userB_xyz');

        $this->assertSame(
            array(
                'userA_bar',
                'userA_foo',
            ),
            $ccd->getDisabledCommonNames('userA')
        );
    }
}
