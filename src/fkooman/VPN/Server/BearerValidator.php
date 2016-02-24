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
namespace fkooman\VPN\Server;

use fkooman\Rest\Plugin\Authentication\Bearer\ValidatorInterface;
use fkooman\Rest\Plugin\Authentication\Bearer\TokenInfo;

class BearerValidator implements ValidatorInterface
{
    /** @var array */
    private $bearerTokens;

    public function __construct(array $bearerTokens)
    {
        $this->bearerTokens = $bearerTokens;
    }

    /**
     * @return TokenInfo
     */
    public function validate($bearerToken)
    {
        foreach ($this->bearerTokens as $t) {
            if (self::hashEquals($t, $bearerToken)) {
                return new TokenInfo(
                    ['active' => true]
                );
            }
        }

        return new TokenInfo(
            ['active' => false]
        );
    }

    /**
     * Wrapper to compare two hashes in a timing safe way.
     *
     * @param string $safe the string we control
     * @param string $user the string the user controls
     *
     * @return bool whether or not the two strings are identical
     */
    public static function hashEquals($safe, $user)
    {
        // PHP >= 5.6.0 has "hash_equals"
        if (function_exists('hash_equals')) {
            return hash_equals($safe, $user);
        }

        return self::timingSafeEquals($safe, $user);
    }
    /**
     * A timing safe equals comparison.
     *
     * @param string $safe The internal (safe) value to be checked
     * @param string $user The user submitted (unsafe) value
     *
     * @return bool True if the two strings are identical.
     *
     * @see http://blog.ircmaxell.com/2014/11/its-all-about-time.html
     */
    public static function timingSafeEquals($safe, $user)
    {
        $safeLen = strlen($safe);
        $userLen = strlen($user);
        if ($userLen != $safeLen) {
            return false;
        }
        $result = 0;
        for ($i = 0; $i < $userLen; ++$i) {
            $result |= (ord($safe[$i]) ^ ord($user[$i]));
        }
        // They are only identical strings if $result is exactly 0...
        return $result === 0;
    }
}
