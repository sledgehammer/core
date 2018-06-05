<?php

namespace Sledgehammer\Core\Util;

use DirectoryIterator;
use Sledgehammer\Core\Debug\PhpAnalyzer;
use Sledgehammer\Core\Framework;

/**
 * Controleer of alle benodige php extenties geinstalleerd zijn.
 *
 * @todo Convert to an utility
 */
class RequiredPhpExtensionsTest extends TestCase
{
    /**
     * Switch off to scan all files in the \Sledgehammer\PATH.
     *
     * @var bool
     */
    private $onlyClassesFolder = true;

    /**
     * Assoc array waarvan de key de functie/class is en de value de extentie.
     *
     * @var array
     */
    private $definitionToExtension = [];

    /**
     * Assoc array met als key de extentie gevult met alle bestanden die deze extentie gebruiken.
     *
     * @var array
     */
    private $extensionUsedIn = [];

    /**
     * @var array
     */
    private $missingExtensions = [];

    /**
     * tests/data/required_php_extentions.db.php inlezen en omzetten naar de $function_to_extention_map.
     */
    public function __construct()
    {
        $functions_per_extention = include __DIR__.'/data/required_php_extentions_functions.db.php';
        foreach ($functions_per_extention as $extention => $functions_or_classes) {
            foreach ($functions_or_classes as $function_or_class) {
                if (isset($this->definitionToExtension[strtolower($function_or_class)])) {
                    trigger_error('Duplicate entry "'.$function_or_class.'"', E_USER_NOTICE);
                }
                $this->definitionToExtension[strtolower($function_or_class)] = $extention;
            }
        }
    }

    /**
     * Controleer of de php extenties geinstalleerd zijn.
     */
    public function test_missing_extentions()
    {
        if (!function_exists('token_get_all')) {
            $this->fail('PHP extention "tokenizer" is required for this UnitTest');

            return;
        }
        $whitelist = array(
//            'apc' => array(realpath(CORE_DIR.'classes/Cache.php')), // Sledgehammer\Cache doesn't require apc, only uses apc when available
        );

        if ($this->onlyClassesFolder) { // Alleen de classes mappen van de modules inlezen
            $modules = Framework::getModules();
            foreach ($modules as $module) {
                $this->checkFolder($module['path'].'classes/');
            }
        } else { // check all php files within $path
            $this->checkFolder(\Sledgehammer\PATH);
        }
        foreach ($this->missingExtensions as $extension => $definition) {
            $files = $this->extensionUsedIn[$extension];
            foreach ($files as $i => $filename) {
                if (isset($whitelist[$extension]) && in_array($filename, $whitelist[$extension])) {
                    unset($files[$i]);
                }
            }
            if (count($files) > 0) {
                $this->fail('Missing php extension "'.$extension.'". Function or class "'.$definition.'" is used in '.\Sledgehammer\quoted_human_implode(' and', $files));
            }
        }
        $this->assertTrue(true, 'All required extenstion are installed');
    }

    /**
     * Scan a folder and subfolders for *.php files.
     *
     * @param string $folder
     */
    private function checkFolder($folder)
    {
        if (!file_exists($folder)) {
            return;
        }
        $DirectoryIterator = new DirectoryIterator($folder);
        foreach ($DirectoryIterator as $Entry) {
            if ($Entry->isDot() || $Entry->getFilename() == '.svn') {
                continue;
            }
            if ($Entry->isDir()) {
                $this->checkFolder($Entry->getPathname());
            } elseif (substr($Entry->getFilename(), -4) == '.php') {
                $this->checkFile($Entry->getPathname());
            }
        }
    }

    /**
     * Analyze the PHP file and check all used classes and functions again the known extension mapping.
     *
     * @param string $filename
     */
    private function checkFile($filename)
    {
        $analyser = new PhpAnalyzer();
        $analyser->open($filename);
        $definitions = array_merge(array_keys($analyser->usedDefinitions), array_keys($analyser->usedFunctions));
        $extentions = [];
        foreach ($definitions as $definition) {
            $key = strtolower($definition);
            if (isset($this->definitionToExtension[$key])) { // function/class belongs to an extension?
                $extentions[$this->definitionToExtension[$key]] = true;
                if (function_exists($key) == false && class_exists($key) == false) {
                    $this->missingExtensions[$this->definitionToExtension[$key]] = $key;
                }
            }
        }
        foreach (array_keys($extentions) as $extention) {
            $this->extensionUsedIn[$extention][] = $filename;
        }
    }
}
