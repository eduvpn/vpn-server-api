<?php

namespace fkooman\VPN\Server\Info;

use fkooman\Http\Request;
use fkooman\Rest\Service;
use fkooman\Rest\ServiceModuleInterface;
use fkooman\Http\JsonResponse;
use fkooman\Http\Exception\ForbiddenException;
use fkooman\Rest\Plugin\Authentication\Bearer\TokenInfo;

class InfoModule implements ServiceModuleInterface
{
    /** @var array */
    private $v4;

    /** @var array */
    private $v6;

    public function __construct(array $v4, array $v6)
    {
        $this->v4 = $v4;
        $this->v6 = $v6;
    }

    public function init(Service $service)
    {
        $service->get(
            '/info/net',
            function (Request $request, TokenInfo $tokenInfo) {
                self::requireScope($tokenInfo, ['admin', 'portal']);

                return $this->getInfo();
            }
        );
    }

    private function getInfo()
    {
        $prefix6 = $this->v6['prefix'];
        $net4 = $this->v4['range'];

        $responseData = [];
        $responseData['range'] = $net4;
        $responseData['range6'] = $prefix6;
        $responseData['dns'] = array_merge($this->v4['dns'], $this->v6['dns']);

        $response = new JsonResponse();
        $response->setBody($responseData);

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
