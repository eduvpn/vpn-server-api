<?php
/**
 *  Copyright (C) 2016 SURFnet.
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace SURFnet\VPN\Server;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use RuntimeException;

class Logger extends AbstractLogger
{
    public function __construct($ident)
    {
        if (false === openlog($ident, LOG_PERROR | LOG_ODELAY, LOG_USER)) {
            throw new RuntimeException('unable to open syslog');
        }
    }

    public function log($level, $message, array $context = array())
    {
        // convert level to syslog level
        $syslogPriority = self::levelToPriority($level);

        // we ignore the context for now
        syslog($syslogPriority, $message);
    }

    private static function levelToPriority($level)
    {
        switch ($level) {
            case LogLevel::EMERGENCY:
                return LOG_EMERG;
            case LogLevel::ALERT:
                return LOG_ALERT;
            case LogLevel::CRITICAL:
                return LOG_CRIT;
            case LogLevel::ERROR:
                return LOG_ERR;
            case LogLevel::WARNING:
                return LOG_WARNING;
            case LogLevel::NOTICE:
                return LOG_NOTICE;
            case LogLevel::INFO:
                return LOG_INFO;
            case LogLevel::DEBUG:
                return LOG_DEBUG;
            default:
                throw new RuntimeException('unknown log level');
        }
    }

    public function __destruct()
    {
        if (false === closelog()) {
            throw new RuntimeException('unable to close syslog');
        }
    }
}
