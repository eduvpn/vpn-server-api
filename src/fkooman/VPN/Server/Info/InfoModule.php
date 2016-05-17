<?php

namespace fkooman\VPN\Server\Info;

use fkooman\Http\Request;
use fkooman\Rest\Service;
use fkooman\Rest\ServiceModuleInterface;
use fkooman\Http\JsonResponse;
use fkooman\Http\Exception\ForbiddenException;
use fkooman\Rest\Plugin\Authentication\Bearer\TokenInfo;
use fkooman\VPN\Server\Pools;

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
                self::requireScope($tokenInfo, ['admin', 'portal']);

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

    private static function requireScope(TokenInfo $tokenInfo, array $requiredScope)
    {
        foreach ($requiredScope as $s) {
            if ($tokenInfo->getScope()->hasScope($s)) {
                return;
            }
        }

        throw new ForbiddenException('insufficient_scope', sprintf('"%s" scope required', implode(',', $requiredScope)));
    }
}
