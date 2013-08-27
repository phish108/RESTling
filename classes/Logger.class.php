<?php

/**
 * CoreService
 *
 * Basic functions for logging
 */
class Logger
{
    protected $debugMode = true;

    /**
     * setDebugMode($mode)
     *
     * Sets the internal debug mode gor logging messages and stuff.
     *
     * @param $mode boolean value to switch logging on or off.
     */
    public function setDebugMode($mode)
    {
        $this->debugMode = $mode;
    }

    /**
     * @method void fatal($message)
     *
     * @param string $message the error message to send
     *
     * This helper function works pretty much like log() but it ignores the debug flag.
     * This means that any fatal message will appear in the server's error log regardless
     * of the debug flag.
     *
     * This is useful to send important error messages if we run into a non-recoverable
     * error.
     */
    public function fatal($message)
    {
        $t = debug_backtrace();
        // need to shift twice
        array_shift($t); // ignore self
        $c = array_shift($t);

        // because mark() uses this function
        if (__CLASS__ === $c['class'])
        {
            $c = array_shift($t);
        }

        if (!empty($c['class']))
        {
            error_log($c['class'] . "::".  $c['function'] . " " . $message);
        }
        else
        {
            error_log($c['function']. " " . $message);
        }
    }

    /**
     * @method void log($message)
     *
     * @param string $message takes the logging message.
     *
     * This helper method eases the debugging of the service. It expands the message with the
     * current class name, so it can be easily identified.
     */
    public function log($message)
    {
        $t = debug_backtrace();
        // need to shift twice
        array_shift($t); // ignore self
        $c = array_shift($t);

        // because mark() uses this function
        if (__CLASS__ === $c['class'])
        {
            $c = array_shift($t);
        }

        if ( $this->debugMode )
        {
            if (!empty($c['class']))
            {
                error_log($c['class'] . "::".  $c['function'] . " " . $message);
            }
            else
            {
                error_log($c['function']. " " . $message);
            }
        }
    }

    /**
     * @method void mark([$extra]);
     *
     * @param $extra (optional) extra label for the marker
     *
     * This method sets a marker to the caller function so one can easily track down important parts in the code.
     * In the error_log file the classname and the function name will appear next with the lable 'MARK'.
     */
    public function mark($extra = "")
    {
        if ($this->debugMode)
        {
            // we want to report on the caller function not on this function
            $t = debug_backtrace();
            // need to shift twice
            array_shift($t);
            $c = array_shift($t);

            if (!(isset($extra) &&
                  strlen($extra)))
            {
                $extra = "";
            }

            $this->log(" MARK " . $extra);
        }
    }


    /**
     * @method void logtest($bTest, $message)
     *
     * @param bool $bTest test this boolean
     * @param string $message the log message
     *
     * This helper function logs only of the $bTest parameter is TRUE.
     */
    public function logtest($bTest, $message)
    {
        if ($bTest)
        {
            $this->log($message);
        }
    }
}

?>