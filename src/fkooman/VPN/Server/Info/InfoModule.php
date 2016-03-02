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
            '/info',
            function (Request $request, TokenInfo $tokenInfo) {
                self::requireScope($tokenInfo, 'info_get');

                $response = new JsonResponse();
                $response->setBody(
                    [
                        'ip' => [
                            'v4' => $this->v4,
                            'v6' => $this->v6,
                        ],
                    ]
                );

                return $response;
            }
        );
    }

    private static function requireScope(TokenInfo $tokenInfo, $requiredScope)
    {
        if (!$tokenInfo->getScope()->hasScope($requiredScope)) {
            throw new ForbiddenException('insufficient_scope', sprintf('"%s" scope required', $requiredScope));
        }
    }
}
