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

use SURFnet\VPN\Server\Api\Exception\HttpException;

class Service
{
    /** @var array */
    private $routes;

    /** @var array */
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

    public function run(Request $request)
    {
        try {
            // before hooks
            $hookData = [];
            if (array_key_exists('before', $this->hooks)) {
                foreach ($this->hooks['before'] as $hook) {
                    $hookData[$hook['name']] = $hook['cb']($request);
                }
            }

            $requestMethod = $request->getRequestMethod();
            $pathInfo = $request->getPathInfo();

            if (!array_key_exists($requestMethod, $this->routes)) {
                throw new HttpException(
                    sprintf('method "%s" not allowed', $requestMethod),
                    405
                );
            }
            if (!array_key_exists($pathInfo, $this->routes[$requestMethod])) {
                throw new HttpException(
                    sprintf('"%s" not found', $pathInfo),
                    404
                );
            }

            return $this->routes[$requestMethod][$pathInfo]($request, $hookData);
        } catch (HttpException $e) {
            $response = new Response($e->getCode());
            $response->setBody($e->getMessage());

            return $response;
        }
    }
}
