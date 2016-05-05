<?php

namespace fkooman\VPN\Server\Info;

use fkooman\Config\Reader;
use fkooman\Http\Request;
use fkooman\Rest\Service;
use fkooman\Rest\ServiceModuleInterface;
use fkooman\Http\JsonResponse;
use fkooman\Http\Exception\ForbiddenException;
use fkooman\Rest\Plugin\Authentication\Bearer\TokenInfo;

class InfoModule implements ServiceModuleInterface
{
    /** @var \fkooman\Config\Reader */
    private $configData;

    public function __construct(Reader $config)
    {
        $this->config = $config;
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
        $responseData = [];
        $responseData['range'] = $this->config->v('range');
        $responseData['range6'] = $this->config->v('range6');
        $responseData['dns'] = $this->config->v('dns');
        $responseData['tfa'] = $this->config->v('twoFactor', false, false);

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
