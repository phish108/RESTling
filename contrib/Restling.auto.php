<?php

    /**
     * Autoloader for the Resting TLA subsystem for the PHP5 autoloader system.
     *
     * In order to use autoloading this file needs to be loaded during the
     * initialization of your root script.
     */
    spl_autoload_register(function ($class) {
        include_once 'RestingTLA/classes/' . $class . '.class.php';
    });
?>