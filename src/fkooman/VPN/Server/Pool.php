<?php
/**
 * Copyright 2016 François Kooman <fkooman@tuxed.net>.
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
use InvalidArgumentException;
use DomainException;

class Pool
{
    /** @var string */
    private $id;

    /** @var string */
    private $poolName;

    /** @var string */
    private $hostName;

    /** @var bool */
    private $defaultGateway;

    /** @var IP */
    private $range;

    /** @var IP */
    private $range6;

    /** @var array */
    private $routes;

    /** @var array */
    private $dns;

    /** @var bool */
    private $useNat;

    /** @var string */
    private $extIf;

    /** @var bool */
    private $twoFactor;

    /** @var bool */
    private $clientToClient;

    /** @var IP */
    private $managementIp;

    /** @var IP */
    private $listen;

    /** @var array */
    private $instances;

    /** @var bool */
    private $enableLog;

    /** @var bool */
    private $enableAcl;

    /** @var bool */
    private $fixMtu;

    public function __construct($poolNumber, array $poolData)
    {
        $this->setId(self::validate($poolData, 'id'));
        $this->setName(self::validate($poolData, 'name', false, $this->getId()));
        $this->setHostName(self::validate($poolData, 'hostName'));
        $this->setDefaultGateway(self::validate($poolData, 'defaultGateway', false, false));
        $this->setRange(new IP(self::validate($poolData, 'range')));
        $this->setRange6(new IP(self::validate($poolData, 'range6')));
        $this->setRoutes(self::validate($poolData, 'routes', false, []));
        $this->setDns(self::validate($poolData, 'dns', false, []));
        $this->setUseNat(self::validate($poolData, 'useNat', false, false));
        $this->setExtIf(self::validate($poolData, 'extIf'));
        $this->setTwoFactor(self::validate($poolData, 'twoFactor', false, false));
        $this->setClientToClient(self::validate($poolData, 'clientToClient', false, false));
        $this->setManagementIp(new IP(sprintf('127.42.%d.1', $poolNumber)));
        $this->setListen(new IP(self::validate($poolData, 'listen', false, '::')));
        $this->setEnableLog(self::validate($poolData, 'enableLog', false, false));
        $this->setEnableAcl(self::validate($poolData, 'enableAcl', false, false));
        $this->setFixMtu(self::validate($poolData, 'fixMtu', false, false));
        $this->populateInstances();
    }

    public function setId($id)
    {
        self::validateSimpleString($id);
        $this->id = $id;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setName($poolName)
    {
        self::validateString($poolName);
        $this->poolName = $poolName;
    }

    public function getName()
    {
        return $this->poolName;
    }

    public function setHostName($hostName)
    {
        self::validateString($hostName);
        $this->hostName = $hostName;
    }

    public function getHostName()
    {
        return $this->hostName;
    }

    public function setDefaultGateway($defaultGateway)
    {
        $this->defaultGateway = (bool) $defaultGateway;
    }

    public function getDefaultGateway()
    {
        return $this->defaultGateway;
    }

    public function setRange(IP $range)
    {
        $this->range = $range;
    }

    public function getRange()
    {
        return $this->range;
    }

    public function setRange6(IP $range6)
    {
        $this->range6 = $range6;
    }

    public function getRange6()
    {
        return $this->range6;
    }

    public function setRoutes(array $routes)
    {
        $this->routes = [];

        foreach ($routes as $route) {
            $this->routes[] = new IP($route);
        }
    }

    public function getRoutes()
    {
        return $this->routes;
    }

    public function setDns(array $dns)
    {
        $this->dns = [];

        foreach ($dns as $server) {
            $this->dns[] = new IP($server);
        }
    }

    public function getDns()
    {
        return $this->dns;
    }

    public function setUseNat($useNat)
    {
        $this->useNat = (bool) $useNat;
    }

    public function getUseNat()
    {
        return $this->useNat;
    }

    public function setExtIf($extIf)
    {
        self::validateString($extIf);
        $this->extIf = $extIf;
    }

    public function getExtIf()
    {
        return $this->extIf;
    }

    public function setTwoFactor($twoFactor)
    {
        $this->twoFactor = (bool) $twoFactor;
    }

    public function getTwoFactor()
    {
        return $this->twoFactor;
    }

    public function setClientToClient($clientToClient)
    {
        $this->clientToClient = (bool) $clientToClient;
    }

    public function getClientToClient()
    {
        return $this->clientToClient;
    }

    public function setManagementIp(IP $managementIp)
    {
        $this->managementIp = $managementIp;
    }

    public function getManagementIp()
    {
        return $this->managementIp;
    }

    public function setListen(IP $listen)
    {
        $this->listen = $listen;
    }

    public function getListen()
    {
        return $this->listen;
    }

    public function getInstances()
    {
        return $this->instances;
    }

    public function setEnableLog($enableLog)
    {
        $this->enableLog = (bool) $enableLog;
    }

    public function getEnableLog()
    {
        return $this->enableLog;
    }

    public function setEnableAcl($enableAcl)
    {
        $this->enableAcl = (bool) $enableAcl;
    }

    public function getEnableAcl()
    {
        return $this->enableAcl;
    }

    public function setFixMtu($fixMtu)
    {
        $this->fixMtu = (bool) $fixMtu;
    }

    public function getFixMtu()
    {
        return $this->fixMtu;
    }

    private function populateInstances()
    {
        $instanceCount = self::getNetCount($this->getRange()->getPrefix());
        $splitRange = $this->getRange()->split($instanceCount);
        $splitRange6 = $this->getRange6()->split($instanceCount);

        for ($i = 0; $i < $instanceCount; ++$i) {
            // protocol is udp unless it is the last instance when there is
            // not just one instance
            if (1 === $instanceCount || $i !== $instanceCount - 1) {
                $proto = 'udp';
                $port = 1194 + $i;
            } else {
                $proto = 'tcp';
                $port = 1194;
            }

            $this->instances[] = new Instance(
                [
                    'range' => $splitRange[$i],
                    'range6' => $splitRange6[$i],
                    'dev' => sprintf('tun-%s-%d', $this->getId(), $i),
                    'proto' => $proto,
                    'port' => $port,
                    'managementPort' => 11940 + $i,
                ]
            );
        }
    }

    /**
     * Depending on the prefix we will divide it in a number of nets to
     * balance the load over the instances, it is recommended to use a least
     * a /24.
     *
     * A /24 or 'bigger' will be split in 4 networks, everything 'smaller'
     * will be either be split in 2 networks or remain 1 network.
     */
    private static function getNetCount($prefix)
    {
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

    public function toArray()
    {
        $routesList = [];
        foreach ($this->getRoutes() as $route) {
            $routesList[] = $route->getAddressPrefix();
        }

        $dnsList = [];
        foreach ($this->getDns() as $dns) {
            $dnsList[] = $dns->getAddress();
        }

        $instancesList = [];
        foreach ($this->getInstances() as $instance) {
            $instancesList[] = $instance->toArray();
        }

        return [
            'clientToClient' => $this->getClientToClient(),
            'defaultGateway' => $this->getDefaultGateway(),
            'dns' => $dnsList,
            'enableAcl' => $this->getEnableAcl(),
            'enableLog' => $this->getEnableLog(),
            'extIf' => $this->getExtIf(),
            'hostName' => $this->getHostName(),
            'id' => $this->getId(),
            'instances' => $instancesList,
            'listen' => $this->getListen()->getAddress(),
            'managementIp' => $this->getManagementIp()->getAddress(),
            'name' => $this->getName(),
            'range' => $this->getRange()->getAddressPrefix(),
            'range6' => $this->getRange6()->getAddressPrefix(),
            'routes' => $routesList,
            'twoFactor' => $this->getTwoFactor(),
            'useNat' => $this->getUseNat(),
        ];
    }

    private static function validate(array $configData, $configName, $requiredField = true, $defaultValue = false)
    {
        if (!array_key_exists($configName, $configData)) {
            if ($requiredField) {
                throw new RuntimeException(sprintf('missing configuration field "%s"', $configName));
            }

            return $defaultValue;
        }

        return $configData[$configName];
    }

    private static function validateString($input)
    {
        if (!is_string($input)) {
            throw new InvalidArgumentException('parameter must be string');
        }
        if (0 >= strlen($input)) {
            throw new DomainException('parameter must be non-empty string');
        }
    }

    private static function validateSimpleString($input)
    {
        self::validateString($input);
        $matchPattern = '/^[a-zA-Z0-9]+$/';
        if (1 !== preg_match($matchPattern, $input)) {
            throw new DomainException(
                sprintf('parameter must match pattern "%s"', $matchPattern)
            );
        }
    }
}
