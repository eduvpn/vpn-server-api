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

    /** @var IP */
    private $range;

    /** @var IP */
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
        $this->setRange(new IP(Utils::validate($poolData, 'range')));
        $this->setRange6(new IP(Utils::validate($poolData, 'range6')));
        $this->setRoutes(Utils::validate($poolData, 'routes', false, []));
        $this->setDns(Utils::validate($poolData, 'dns', false, []));
        $this->setTwoFactor(Utils::validate($poolData, 'twoFactor', false, false));
        $this->setClientToClient(Utils::validate($poolData, 'clientToClient', false, false));
        $this->setManagementIp(new IP(sprintf('127.42.%d.1', $this->getId())));
        $this->setListen(new IP(Utils::validate($poolData, 'listen')));

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

    private function populateInstances()
    {
        $instanceCount = self::getNetCount($this->getRange()->getPrefix());
        $splitRange = $this->getRange()->split($instanceCount);
        $splitRange6 = $this->getRange6()->split($instanceCount);

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
                    'range' => $splitRange[$i],
                    'range6' => $splitRange6[$i],
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

    private static function validateIpAddress($ipAddress)
    {
        if (false === filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            throw new BadRequestException('invalid IP address');
        }
    }
}
