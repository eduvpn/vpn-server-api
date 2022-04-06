<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Server\OpenVpnMgmt;

use LC\Common\Log\LoggerInterface;
use LC\Server\OpenVpnMgmt\Exception\ManagementSocketException;

class ConnectionManager
{
    /** @var array<int,string> */
    private $socketAddressList;

    /** @var \LC\Common\Log\LoggerInterface */
    private $logger;

    /** @var ManagementSocketInterface */
    private $managementSocket;

    /**
     * @param array<int,string> $socketAddressList
     */
    public function __construct(array $socketAddressList, LoggerInterface $logger, ManagementSocketInterface $managementSocket = null)
    {
        $this->socketAddressList = $socketAddressList;
        $this->logger = $logger;
        if (null === $managementSocket) {
            $managementSocket = new ManagementSocket();
        }
        $this->managementSocket = $managementSocket;
    }

    /**
     * @return array<int, array>
     */
    public function connections()
    {
        $connectionList = [];
        foreach ($this->socketAddressList as $socketAddress) {
            try {
                $this->managementSocket->open($socketAddress);
                $connectionList = array_merge($connectionList, StatusParser::parse($this->managementSocket->command('status 2')));
                $this->managementSocket->close();
            } catch (ManagementSocketException $e) {
                $this->logger->error(
                    sprintf(
                        'error with socket "%s": "%s"',
                        $socketAddress,
                        $e->getMessage()
                    )
                );
            }
        }

        return $connectionList;
    }

    /**
     * @param array<string> $commonNameList
     *
     * @return int
     */
    public function disconnect(array $commonNameList)
    {
        $disconnectCount = 0;
        foreach ($this->socketAddressList as $socketAddress) {
            try {
                $this->managementSocket->open($socketAddress);
                foreach ($commonNameList as $commonName) {
                    $result = $this->managementSocket->command(sprintf('kill %s', $commonName));
                    if (0 === strpos($result[0], 'SUCCESS: ')) {
                        ++$disconnectCount;
                    }
                }
                $this->managementSocket->close();
            } catch (ManagementSocketException $e) {
                $this->logger->error(
                    sprintf(
                        'error with socket "%s", message: "%s"',
                        $socketAddress,
                        $e->getMessage()
                    )
                );
            }
        }

        return $disconnectCount;
    }
}
