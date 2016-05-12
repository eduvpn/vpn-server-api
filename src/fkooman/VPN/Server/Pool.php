<?php

namespace fkooman\VPN\Server;

use RuntimeException;

class Pool
{
    /** @var int */
    private $id;

    /** @var string */
    private $poolName;

    /** @var IPv4 */
    private $range;

    /** @var IPv6 */
    private $range6;

    /** @var array */
    private $routes;

    /** @var array */
    private $dns;

    /** @var bool */
    private $twoFactor;

    /** @var bool */
    private $clientToClient;

    /** @var string */
    private $managementIp;

    /** @var string */
    private $listen;

    /** @var array */
    private $instances;

    public function __construct($id, array $poolData)
    {
        $this->setId($id);
        $this->setName($poolData['name']);
        $this->setRange(new IPv4($poolData['range']));
        $this->setRange6(new IPv6($poolData['range6']));
        $this->setRoutes($poolData['routes']);
        $this->setDns($poolData['dns']);
        $this->setTwoFactor($poolData['twoFactor']);
        $this->setClientToClient($poolData['clientToClient']);
        $this->setManagementIp(sprintf('127.42.%d.1', $this->getId()));
        $this->setListen($poolData['listen']);

        $this->populateInstances();
    }

    public function setId($id)
    {
        // XXX validate
        $this->id = $id;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setName($poolName)
    {
        // XXX validate
        $this->poolName = $poolName;
    }

    public function getName()
    {
        return $this->poolName;
    }

    public function setRange(IPv4 $range)
    {
        $this->range = $range;
    }

    public function getRange()
    {
        return $this->range;
    }

    public function setRange6(IPv6 $range6)
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
            if (false !== strpos($route, ':')) {
                // IPv6
                $this->routes[] = new IPv6($route);
            } else {
                // IPv4
                $this->routes[] = new IPv4($route);
            }
        }
    }

    public function getRoutes()
    {
        return $this->routes;
    }

    public function setDns(array $dns)
    {
        // XXX validate
        $this->dns = $dns;
    }

    public function getDns()
    {
        return $this->dns;
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

    public function setManagementIp($managementIp)
    {
        // XXX validate, must be valid IPv4 or IPv6 address
        $this->managementIp = $managementIp;
    }

    public function getManagementIp()
    {
        return $this->managementIp;
    }

    public function setListen($listen)
    {
        // XXX validate, must be valid IPv4 or IPv6 address
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

    private function populateInstances()
    {
        $instanceCount = self::getNetCount($this->getRange()->getPrefix());
        $splitRange = $this->getRange()->splitRange($instanceCount);
        $splitRange6 = $this->getRange6()->splitRange($instanceCount);

        for ($i = 0; $i < $instanceCount; ++$i) {
            // protocol is udp6 unless it is the last instance when there is
            // not just one instance
            if (1 === $instanceCount) {
                $proto = 'udp6';
            } elseif ($i === $instanceCount - 1) {
                // the TCP instance always listens on IPv4 to work around iOS
                // issue together with sniproxy
                $proto = 'tcp-server';
            } else {
                $proto = 'udp6';
            }

            if ($proto === 'tcp-server') {
                // there is only one TCP instance and it always listens on tcp/1194
                $port = 1194;
            } else {
                // the UDP instances can cover a range of ports
                $port = 1194 + $i;
            }

            $this->instances[] = new Instance(
                [
                    'range' => new IPv4($splitRange[$i]),
                    'range6' => new IPv6($splitRange6[$i]),
                    'dev' => sprintf('tun-%s-%d', $this->getName(), $i),
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
            case 30:    // 4 IPs
                return 1;
            case 29:    // 8 IPs
            case 28:    // 16 IPs
            case 27:    // 32 IPs
            case 26:    // 64 IPs
            case 25:    // 128 IPs
                return 2;
        }

        return 4;
    }

    public function getServerConfig()
    {
        $serverConfig = [];

        foreach ($this->getInstances() as $k => $instance) {
            $s = new ServerConfig();
            $serverConfig[sprintf('%s-%d', $this->getName(), $k)] = $s->get(
                [
                    'dev' => $instance->getDev(),
                    'proto' => $instance->getProto(),
                    'port' => $instance->getPort(),
                    'v4_prefix' => $instance->getRange(),
                    'v6_prefix' => $instance->getRange6(),
                    'dns' => $this->getDns(),
                    'management_ip' => $this->getManagementIp(),
                    'management_port' => $instance->getManagementPort(),
                    'listen' => $this->getListen(),
                    '2fa' => $this->getTwoFactor(),
                    'routes' => $this->getRoutes(),
                    'c2c' => $this->getClientToClient(),
                ]
            );
        }

        return $serverConfig;
    }
}
