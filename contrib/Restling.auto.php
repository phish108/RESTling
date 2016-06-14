<?php

    /**
     * Autoloader for the RESTling subsystem for the PHP5 autoloader system.
     *
     * In order to use autoloading this file needs to be loaded during the
     * initialization of your root script.
     *
     * This autoloader uses namespaces and will find also classes within other
     * namespace schemes.
     */
    spl_autoload_register(function ($class) {
        $class = ltrim($class, '\\');
        $parts = explode('\\', $class);

        $root = array_shift($parts);

        if (!empty($root)) {
            $cpath = array();
            // direct namespace
            $cpath[] = $root . "/" . implode("/", $parts) . ".class.php";

            // sub-directory namespaces
            $cpath[] = $root . "/classes/" . implode("/", $parts) . ".class.php";
            $cpath[] = $root . "/src/" . implode("/", $parts) . ".class.php";
            $cpath[] = $root . "/lib/" . implode("/", $parts) . ".class.php";

            // for developer prefixed namespaces
            $root = array_shift($parts);
            $cpath[] = strtolower($root) . "/src/" . implode("/", $parts) . ".class.php";
            $cpath[] = strtolower($root) . "/lib/" . implode("/", $parts) . ".class.php";

            $prefixes = explode(PATH_SEPARATOR, get_include_path());

            foreach ( $prefixes as $p ) {
                foreach ($cpath as $path) {
                    if (file_exists($p . "/" . $path)) {
                        include_once $p . "/" . $path;
                        break 2;
                    }
                }
            }
        }
    });
?>
