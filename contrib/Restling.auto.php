<?php

    /**
     * Autoloader for the RESTling subsystem for the PHP5 autoloader system.
     *
     * In order to use autoloading this file needs to be loaded during the
     * initialization of your root script.
     */
    spl_autoload_register(function ($class) {
        $parts = explode('\\', $class);
        $NSRoot = array_shift($parts);

        if (isset($NSRoot) &&
            !empty($NSRoot) &&
            $NSRoot == "RESTling") {

            $path = 'classes/' . end($parts) . '.class.php';

            $prefixes = explode(PATH_SEPARATOR, get_include_path());

            array_push($prefixes, "..");
            foreach ( $prefixes as $p ) {
                if (file_exists($p . "/RESTling/" . $path)) {
                    include_once $p . "/RESTling/" . $path;
                    break;
                }
                else if (file_exists($p . "/include/RESTling/" . $path)) {
                   include_once $p . "/include/RESTling/" . $path;
                   break;
                }
                else if (file_exists($p . "/" . $path)) {
                   include_once $p . "/" . $path;
                   break;
                }
            }
        }
    });
?>
