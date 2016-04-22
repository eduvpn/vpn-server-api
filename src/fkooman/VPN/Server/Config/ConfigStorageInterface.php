<?php
/**
 * Copyright 2015 François Kooman <fkooman@tuxed.net>.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace fkooman\VPN\Server\Config;

interface ConfigStorageInterface
{
    /**
     * Get configuration specific to a particular user.
     *
     * @return UserConfig
     */
    public function getUserConfig($userId);

    /**
     * Set the configuration for a particular user.
     */
    public function setUserConfig($userId, UserConfig $userConfig);

    /**
     * Get the configuration for a particular common name.
     *
     * @return CommonNameConfig
     */
    public function getCommonNameConfig($commonName);

    /**
     * Get all common name configurations, optionally limited to a particular
     * user.
     *
     * @param string|null $userId the userId to retrieve the common names for,
     *                            if the parameter is null all common name configurations for all users 
     *                            are retrieved
     *
     * @return CommonNameConfig[]
     */
    public function getAllCommonNameConfig($userId);

    /** 
     * Set the configuration for a particular common name.
     */
    public function setCommonNameConfig($commonName, CommonNameConfig $commonNameConfig);
}
