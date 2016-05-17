<?php

namespace fkooman\VPN\Server;

use ArrayObject;

class Pools extends ArrayObject
{
    public function __construct(array $poolsData)
    {
        $poolList = [];
        $i = 0;
        foreach ($poolsData as $poolId => $poolData) {
            $poolData['id'] = $poolId;
            $poolList[] = new Pool($i, $poolData);
            ++$i;
        }
        parent::__construct($poolList, ArrayObject::STD_PROP_LIST);
    }
}
