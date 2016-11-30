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

namespace SURFnet\VPN\Server\Test;

use SURFnet\VPN\Common\Config;
use SURFnet\VPN\Server\CA\CaInterface;

class TestCa implements CaInterface
{
    public function serverCert($commonName)
    {
        return [
            'certificate' => sprintf('ServerCert for %s', $commonName),
            'private_key' => sprintf('ServerCert for %s', $commonName),
            'valid_from' => 1234567890,
            'valid_to' => 2345678901,
        ];
    }

    public function clientCert($commonName)
    {
        return [
            'certificate' => sprintf('ClientCert for %s', $commonName),
            'private_key' => sprintf('ClientKey for %s', $commonName),
            'valid_from' => 1234567890,
            'valid_to' => 2345678901,
        ];
    }

    public function caCert()
    {
        return 'Ca';
    }

    public function init(Config $config)
    {
        // NOP
    }
}
