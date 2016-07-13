<?php
/**
 * Copyright 2016 François Kooman <fkooman@tuxed.net>.
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

namespace fkooman\VPN\Server;

interface AclInterface
{
    /**
     * Get the groups a user is a member of.
     *
     * @param string userId the userID of the user to request the groups of
     *
     * @return array the groups as an array containing the keys "id" and 
     *               "displayName", empty array if no groups are available for this user
     */
    public function getGroups($userId);
}
