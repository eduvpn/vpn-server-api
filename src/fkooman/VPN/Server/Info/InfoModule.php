<?php

namespace fkooman\VPN\Server\Info;

use fkooman\Http\Request;
use fkooman\Rest\Service;
use fkooman\Rest\ServiceModuleInterface;
use fkooman\Http\JsonResponse;
use fkooman\Http\Exception\ForbiddenException;
use fkooman\Rest\Plugin\Authentication\Bearer\TokenInfo;
use fkooman\VPN\Server\Utils;

class InfoModule implements ServiceModuleInterface
{
    /** @var array */
    private $dns;

    /** @var array */
    private $net;

    public function __construct(array $dns, array $net)
    {
        $this->dns = $dns;
        $this->net = $net;
    }

    public function init(Service $service)
    {
        $service->get(
            '/info/net',
            function (Request $request, TokenInfo $tokenInfo) {
                self::requireScope($tokenInfo, 'info_get');

                return $this->getInfo();
            }
        );
    }

    private function getInfo()
    {
        $net6 = $this->net['range6'];
        $net4 = $this->net['range'];

        $responseData = [];
        $responseData['range'] = $net4;
        $responseData['range6'] = Utils::convert4to6($net6, $net4); // XXX fix convert4to6
        $responseData['pools'] = [];
        $responseData['dns'] = $this->dns;

        foreach ($this->net['pools'] as $id => $pool) {
            $poolInfo = [
                'name' => $pool['name'],
                'range' => $pool['range'],
                'range6' => Utils::convert4to6($net6, $pool['range']),
            ];
            if (!array_key_exists('firewall', $pool)) {
                $pool['firewall'] = [];
            }
            if (!array_key_exists('dst_net', $pool['firewall'])) {
                $pool['firewall']['dst_net'] = ['0.0.0.0/0', '::/0'];
            }
            if (!array_key_exists('dst_port', $pool['firewall'])) {
                $pool['firewall']['dst_port'] = ['*/*'];
            }
            $poolInfo['firewall'] = $pool['firewall'];

            $responseData['pools'][$id] = $poolInfo;
        }

        $response = new JsonResponse();
        $response->setBody($responseData);

        return $response;
    }

    private static function requireScope(TokenInfo $tokenInfo, $requiredScope)
    {
        if (!$tokenInfo->getScope()->hasScope($requiredScope)) {
            throw new ForbiddenException('insufficient_scope', sprintf('"%s" scope required', $requiredScope));
        }
    }
}
