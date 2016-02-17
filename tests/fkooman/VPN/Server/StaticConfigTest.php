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

class StaticConfigTest extends PHPUnit_Framework_TestCase
{
    private $staticConfigDir;

    public function setUp()
    {
        // get a directory to play with
        $tempDirName = tempnam(sys_get_temp_dir(), 'static');
        if (file_exists($tempDirName)) {
            @unlink($tempDirName);
        }
        @mkdir($tempDirName);

        // a not disabled commonName
        @file_put_contents($tempDirName.'/bar', '{}');

        // a disabled commonName
        @file_put_contents($tempDirName.'/foobar', '{"disable": true}');

        $this->staticConfigDir = $tempDirName;
    }

    public function testDisableNonExistingFile()
    {
        $staticConfig = new StaticConfig($this->staticConfigDir);
        $this->assertTrue($staticConfig->disableCommonName('foo'));
        $this->assertTrue($staticConfig->isDisabled('foo'));
    }

    public function testDisableExistingFile()
    {
        $staticConfig = new StaticConfig($this->staticConfigDir);
        $this->assertTrue($staticConfig->disableCommonName('bar'));
        $this->assertTrue($staticConfig->isDisabled('bar'));
    }

    public function testDisableAlreadyDisabled()
    {
        $staticConfig = new StaticConfig($this->staticConfigDir);
        $this->assertFalse($staticConfig->disableCommonName('foobar'));
        $this->assertTrue($staticConfig->isDisabled('foobar'));
    }

    public function testEnableExistingFile()
    {
        $staticConfig = new StaticConfig($this->staticConfigDir);
        $this->assertFalse($staticConfig->enableCommonName('bar'));
        $this->assertFalse($staticConfig->isDisabled('bar'));
    }

    public function testEnableDisabledFile()
    {
        $staticConfig = new StaticConfig($this->staticConfigDir);
        $this->assertTrue($staticConfig->enableCommonName('foobar'));
        $this->assertFalse($staticConfig->isDisabled('foobar'));
    }

    public function testEnableNonExistingFile()
    {
        $staticConfig = new StaticConfig($this->staticConfigDir);
        $this->assertFalse($staticConfig->enableCommonName('foo'));
        $this->assertFileNotExists($this->staticConfigDir.'/foo');
    }

    public function testGetDisabledCommonNames()
    {
        $staticConfig = new StaticConfig($this->staticConfigDir);
        $staticConfig->disableCommonName('a');
        $staticConfig->disableCommonName('b');
        $staticConfig->enableCommonName('b');
        $staticConfig->disableCommonName('c');

        $this->assertSame(
            array(
                'a',
                'c',
                'foobar',
            ),
            $staticConfig->getDisabledCommonNames()
        );
    }

    public function testGetDisabledCommonNamesByUser()
    {
        $staticConfig = new StaticConfig($this->staticConfigDir);
        $staticConfig->disableCommonName('userA_foo');
        $staticConfig->disableCommonName('userA_bar');
        $staticConfig->disableCommonName('userB_xyz');

        $this->assertSame(
            array(
                'userA_bar',
                'userA_foo',
            ),
            $staticConfig->getDisabledCommonNames('userA')
        );
    }

    public function testGetStaticAddresses()
    {
        $staticConfig = new StaticConfig($this->staticConfigDir);
        $this->assertSame(
            array(
                'v4' => null,
            ),
            $staticConfig->getStaticAddress('foo')
        );
        $staticConfig->setStaticAddresses(
            'foo',
            '10.0.0.5'
        );
        $this->assertSame(
            array(
                'v4' => '10.0.0.5'
            ),
            $staticConfig->getStaticAddress('foo')
        );
    }

    public function testSetNewStaticAddresses()
    {
        $staticConfig = new StaticConfig($this->staticConfigDir);
        $staticConfig->setStaticAddresses(
            'foo',
            '10.0.0.5'
        );
        $staticConfig->setStaticAddresses(
            'foo',
            '10.10.0.5'
        );
        $this->assertSame(
            array(
                'v4' => '10.10.0.5',
            ),
            $staticConfig->getStaticAddress('foo')
        );
    }

    public function testSetOnlyV4()
    {
        $staticConfig = new StaticConfig($this->staticConfigDir);
        $staticConfig->setStaticAddresses(
            'foo',
            '10.0.0.5'
        );
        $this->assertSame(
            array(
                'v4' => '10.0.0.5',
            ),
            $staticConfig->getStaticAddress('foo')
        );
    }

    public function testUnset()
    {
        $staticConfig = new StaticConfig($this->staticConfigDir);
        $staticConfig->setStaticAddresses(
            'foo',
            '10.0.0.5'
        );
        $staticConfig->setStaticAddresses(
            'foo',
            null
        );
        $this->assertSame(
            array(
                'v4' => null,
            ),
            $staticConfig->getStaticAddress('foo')
        );
    }
}
