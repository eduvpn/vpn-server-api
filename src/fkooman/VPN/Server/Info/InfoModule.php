<?php

namespace fkooman\VPN\Server\Info;

use fkooman\Http\Request;
use fkooman\Rest\Service;
use fkooman\Rest\ServiceModuleInterface;
use fkooman\Http\JsonResponse;
use fkooman\Rest\Plugin\Authentication\Bearer\TokenInfo;
use fkooman\VPN\Server\Pools;
use fkooman\VPN\Server\Utils;

class InfoModule implements ServiceModuleInterface
{
    /** @var \fkooman\VPN\Server\Pools */
    private $pools;

    public function __construct(Pools $pools)
    {
        $this->pools = $pools;
    }

    public function init(Service $service)
    {
        $service->get(
            '/info/server',
            function (Request $request, TokenInfo $tokenInfo) {
                Utils::requireScope($tokenInfo, ['admin', 'portal']);

                return $this->getInfo();
            }
        );
    }

    private function getInfo()
    {
        $response = new JsonResponse();
        $response->setBody(['data' => $this->pools->getInfo()]);

        return $response;
    }
}
