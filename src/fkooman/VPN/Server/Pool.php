<?php

namespace fkooman\VPN\Server;

use RuntimeException;

class Pool
{
    /** @var int */
    private $id;

    /** @var string */
    private $poolName;

    /** @var string */
    private $hostName;

    /** @var bool */
    private $defaultGateway;

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
        $this->setName(Utils::validate($poolData, 'name'));
        $this->setHostName(Utils::validate($poolData, 'hostName'));
        $this->setDefaultGateway(Utils::validate($poolData, 'defaultGateway'));
        $this->setRange(new IPv4(Utils::validate($poolData, 'range')));
        $this->setRange6(new IPv6(Utils::validate($poolData, 'range6')));
        $this->setRoutes(Utils::validate($poolData, 'routes', false, []));
        $this->setDns(Utils::validate($poolData, 'dns', false, []));
        $this->setTwoFactor(Utils::validate($poolData, 'twoFactor', false, false));
        $this->setClientToClient(Utils::validate($poolData, 'clientToClient', false, false));
        $this->setManagementIp(sprintf('127.42.%d.1', $this->getId()));
        $this->setListen(Utils::validate($poolData, 'listen'));

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

    public function setHostName($hostName)
    {
        // XXX validate
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

        $is6 = false !== strpos($this->getListen(), ':');

        for ($i = 0; $i < $instanceCount; ++$i) {
            // protocol is udp6 unless it is the last instance when there is
            // not just one instance
            if (1 === $instanceCount) {
                $proto = $is6 ? 'udp6' : 'udp';
            } elseif ($i === $instanceCount - 1) {
                // the TCP instance always listens on IPv4 to work around iOS
                // issue together with sniproxy
                $proto = 'tcp-server';
            } else {
                $proto = $is6 ? 'udp6' : 'udp';
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
            $serverConfig[sprintf('%s-%d', $this->getName(), $k)] = ServerConfig::get($this, $instance);
        }

        return $serverConfig;
    }

    public function getConnectInfo()
    {
        $protoPort = [];
        foreach ($this->getInstances() as $k => $instance) {
            // for TCP connections we only want the client to connect to tcp/443
            if ('tcp-server' === $instance->getProto()) {
                $proto = 'tcp';
                $port = 443;
            } else {
                $proto = 'udp';
                $port = $instance->getPort();
            }

            $protoPort[] = ['host' => $this->getHostName(), 'proto' => $proto, 'port' => $port];
        }

        return $protoPort;
    }
}
