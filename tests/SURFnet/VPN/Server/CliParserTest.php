<?php
/**
 *  Copyright (C) 2016 SURFnet.
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace SURFnet\VPN\Server;

use PHPUnit_Framework_TestCase;

class CliParserTest extends PHPUnit_Framework_TestCase
{
    public function testOne()
    {
        $p = new CliParser(
            'Test',
            [
                'instance' => ['instance identifier', true, true],
            ]
        );

        $config = $p->parse(['name_of_program', '--instance', 'vpn.example']);
        $this->assertSame(file_get_contents(sprintf('%s/data/help.txt', __DIR__)), $p->help());
        $this->assertSame('vpn.example',  $config->v('instance'));
    }

    public function testTwo()
    {
        $p = new CliParser(
            'Test',
            [
                'instance' => ['instance identifier', true, true],
                'generate' => ['generate a new certificate', true, true],
            ]
        );
        $config = $p->parse(['name_of_program', '--instance', 'vpn.example', '--generate', 'vpn00.example']);
        $this->assertSame('vpn.example', $config->v('instance'));
        $this->assertSame('vpn00.example', $config->v('generate'));
    }

    public function testThree()
    {
        $p = new CliParser(
            'Test',
            [
                'install' => ['install the firewall', false, false],
            ]
        );
        $config = $p->parse(['name_of_program', '--install']);
        $this->assertSame([], $config->v('install'));
    }
}
