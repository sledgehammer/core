<?php

namespace SledgehammerTests\Core;

/**
 * Controleer diverse Sledgehammer vereisten.
 */
class ServerEnvironmentTest extends TestCase
{
    /**
     * Controleer of de php.ini instellingen goed staan.
     */
    public function test_php_ini()
    {
        $this->assertTrue(ini_get('session.auto_start') == false, 'php.ini[session.auto_start] moet uit staan');
        $this->assertTrue(ini_get('register_globals') == false, 'php.ini[register_globals] moet uit staan');
        $this->assertTrue(ini_get('magic_quotes_gpc') == false, 'php.ini[magic_quotes_gpc] moet uit staan');
    }

    /**
     * Controleer de PHP versie.
     */
    public function test_php_version()
    {
        $this->assertTrue(version_compare(PHP_VERSION, '5.3.3', '>='), 'PHP should be version 5.3.3 or higher');
    }

    /**
     * Controleer de tmp map.
     */
    public function test_tmp_folder()
    {
        $this->assertTrue(is_writable(\Sledgehammer\TMP_DIR), 'De tmp map zou beschrijfbaar moeten zijn');
    }

    public function test_environment()
    {
        $allowedEnvironments = ['development', 'testing', 'acceptation', 'production', 'phpunit'];
        $this->assertTrue(in_array(\Sledgehammer\ENVIRONMENT, $allowedEnvironments), '\Sledgehammer\ENVIRONMENT moet een van de volgende waarden zijn: "'.\Sledgehammer\human_implode('" of "', $allowedEnvironments, '", "').'"');
    }
}
