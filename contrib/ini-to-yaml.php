#!/usr/bin/php
<?php

require_once '/usr/share/php/fkooman/Config/autoload.php';

use fkooman\Config\YamlFile;
use fkooman\Config\IniFile;

try {
    $iniFile = new IniFile('/etc/vpn-server-api/config.ini');
    $yamlFile = new YamlFile('/etc/vpn-server-api/config.yaml');

    $iniConfig = $iniFile->readConfig();
    $newConfig = array();

    // OpenVpnManagement --> OpenVpn
    $newConfig['OpenVpn'] = array();
    $sockets = $iniConfig['OpenVpnManagement'];

    for ($i = 0; $i < sizeof($sockets['socket']); ++$i) {
        $newConfig['OpenVpn'][] = array(
            'id' => sprintf('server_%s', $i),
            'name' => sprintf('Server %s', $i),
            'socket' => $sockets['socket'][$i],
        );
    }

    // BasicAuthentication --> Users
    $newConfig['Users'] = array();
    $basicAuth = $iniConfig['BasicAuthentication'];
    foreach ($basicAuth as $user => $hash) {
        $newConfig['Users'][$user] = $hash;
    }

    // Crl --> Crl
    $newConfig['Crl']['url'] = $iniConfig['Crl']['crlUrl'];
    $newConfig['Crl']['path'] = $iniConfig['Crl']['crlPath'];
    $yamlFile->writeConfig($newConfig);
} catch (Exception $e) {
    die(sprintf('ERROR: %s', $e->getMessage()).PHP_EOL);
}
