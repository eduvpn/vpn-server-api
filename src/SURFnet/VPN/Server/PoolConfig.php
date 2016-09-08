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

class PoolConfig extends Config
{
    private static function defaultValues()
    {
        return [
            'defaultGateway' => false,
            'routes' => [],
            'dns' => [],
            'useNat' => false,
            'twoFactor' => false,
            'clientToClient' => false,
            'listen' => '0.0.0.0',
            'enableLog' => false,
            'enableAcl' => false,
            'aclGroupList' => [],
            'blockSmb' => false,
            'forward6' => true,
        ];
    }

    public function __construct(array $configData)
    {
        parent::__construct(
            array_merge(self::defaultValues(), $configData)
        );
    }

    /**
     * Depending on the size of the prefix assigned to this pool, this method
     * will return the number of OpenVPN processes backing that range, each
     * pool range is split in #processCount number of OpenVPN processes.
     */
    public function getProcessCount()
    {
        $range = new IP($this->v('range'));
        $prefix = $range->getPrefix();

        switch ($prefix) {
            case 32:    // 1 IP
            case 31:    // 2 IPs
                throw new RuntimeException('not enough available IPs in range');
            case 30:    // 4 IPs (1 usable for client, no splitting)
            case 29:    // 8 IPs (5 usable for clients, no splitting)
                return 1;
            case 28:    // 16 IPs (12 usable for clients)
            case 27:    // 32 IPs
            case 26:    // 64 IPs
            case 25:    // 128 IPs
                return 2;
            case 24:
                return 4;
        }

        return 8;
    }
}
