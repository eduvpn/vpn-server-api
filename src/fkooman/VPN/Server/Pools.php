<?php

namespace fkooman\VPN\Server;

class Pools
{
    /** @var array */
    private $pools;

    public function __construct(array $poolsData)
    {
        // XXX listen cannot be the same for different Pool
        // XXX also, if there is more than one pool, listen cannot be '::' or 0.0.0.0

        $this->pools = [];

        $i = 0;
        foreach ($poolsData as $poolId => $poolData) {
            $poolData['id'] = $poolId;
            $this->pools[] = new Pool($i, $poolData);
            ++$i;
        }
    }

    public function getPools()
    {
        return $this->pools;
    }

    public function getInfo()
    {
        $poolInfo = [];
        foreach ($this->pools as $pool) {
            $poolInfo[] = $pool->toArray();
        }

        return $poolInfo;
    }
}
