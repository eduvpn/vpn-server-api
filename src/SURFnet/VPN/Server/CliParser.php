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

use SURFnet\VPN\Common\Config;
use SURFnet\VPN\Server\Exception\CliException;

class CliParser
{
    /** @var string */
    private $programDescription;

    /** @var array */
    private $optionList;

    public function __construct($programDescription, array $optionList)
    {
        $this->programDescription = $programDescription;
        $this->optionList = $optionList;
    }

    public function help()
    {
        $helpText = $this->programDescription.PHP_EOL;
        $helpText .= 'Options:'.PHP_EOL;
        foreach ($this->optionList as $k => $v) {
            if ($v[1]) {
                $helpText .= sprintf("  --%s %s\t\t%s", $k, $k, $v[0]);
            } else {
                $helpText .= sprintf("  --%s\t\t%s", $k, $v[0]);
            }
            if ($v[2]) {
                $helpText .= ' (REQUIRED)';
            }
            $helpText .= PHP_EOL;
        }

        return $helpText;
    }

    public function parse(array $argv)
    {
        $argc = count($argv);
        $optionValues = [];

        for ($i = 1; $i < $argc; ++$i) {
            if (0 === strpos($argv[$i], '--')) {
                // it is an option selector
                $p = substr($argv[$i], 2);  // strip the dashes
                $pO = [];
                while ($i + 1 < $argc && false === strpos($argv[$i + 1], '--')) {
                    $pO[] = $argv[++$i];
                }
                if (1 === count($pO)) {
                    $optionValues[$p] = $pO[0];
                } else {
                    $optionValues[$p] = $pO;
                }
            }
        }

        // --help is special
        if (array_key_exists('help', $optionValues)) {
            return new Config(['help' => true]);
        }

        // check if any of the required keys is missing
        foreach (array_keys($this->optionList) as $opt) {
            if ($this->optionList[$opt][2]) {
                // required
                if (!array_key_exists($opt, $optionValues)) {
                    throw new CliException(sprintf('missing required parameter "--%s"', $opt));
                }
            }
        }

        // check if any of the options that require a value has no value
        foreach (array_keys($this->optionList) as $opt) {
            if ($this->optionList[$opt][1]) {
                // check if it is actually there
                if (array_key_exists($opt, $optionValues)) {
                    // must have value
                    if (0 === count($optionValues[$opt])) {
                        throw new CliException(sprintf('missing required parameter value for option "--%s"', $opt));
                    }
                }
            }
        }

        return new Config($optionValues);
    }
}
