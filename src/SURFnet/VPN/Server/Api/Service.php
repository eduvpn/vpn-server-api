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
namespace SURFnet\VPN\Server\Api;

use fkooman\Http\Exception\MethodNotAllowedException;
use fkooman\Http\Exception\NotFoundException;
use RuntimeException;

class Service
{
    private $routes;
    private $hooks;

    public function __construct()
    {
        $this->routes = [];
        $this->hooks = [];
    }

    public function addHook($type, $name, callable $callback)
    {
        if (!array_key_exists($type, $this->hooks)) {
            $this->hooks[$type] = [];
        }
        $this->hooks[$type][] = ['name' => $name, 'cb' => $callback];
    }

    public function addRoute($requestMethod, $pathInfo, callable $callback)
    {
        $this->routes[$requestMethod][$pathInfo] = $callback;
    }

    public function get($pathInfo, callable $callback)
    {
        $this->addRoute('GET', $pathInfo, $callback);
    }

    public function post($pathInfo, callable $callback)
    {
        $this->addRoute('POST', $pathInfo, $callback);
    }

    public function addModule(ServiceModuleInterface $module)
    {
        $module->init($this);
    }

    public function run(array $serverData, array $getData, array $postData)
    {
        if (!array_key_exists('REQUEST_METHOD', $serverData)) {
            throw new RuntimeException('invalid HTTP request, missing REQUEST_METHOD');
        }

        // before hooks
        $hookData = [];
        if (array_key_exists('before', $this->hooks)) {
            foreach ($this->hooks['before'] as $hook) {
                $hookData[$hook['name']] = $hook['cb']($serverData, $postData, $getData);
            }
        }

        $requestMethod = $serverData['REQUEST_METHOD'];
        $pathInfo = '/';
        if (array_key_exists('PATH_INFO', $serverData)) {
            $pathInfo = $serverData['PATH_INFO'];
        }

        if (!array_key_exists($requestMethod, $this->routes)) {
            throw new MethodNotAllowedException(sprintf('method "%s" not allowed', $requestMethod));
        }
        if (!array_key_exists($pathInfo, $this->routes[$requestMethod])) {
            throw new NotFoundException(sprintf('"%s" not found', $pathInfo));
        }

        return $this->routes[$requestMethod][$pathInfo]($serverData, $getData, $postData, $hookData);
    }
}
