<?php
namespace RESTling;

/**
 * Internal exception handler.
 *
 * Generates the exception message from the exception class name.
 *
 * @return \RESTling\Exception
 */
class Exception extends \Exception{
    public function __construct() {
        $cls = explode('\\', get_class($this)); // actual classname
        $mName = array_pop($cls);
        $aMsg = preg_split('/(?=[A-Z])/',$mName);
        if (empty($aMsg[0])) {
            array_shift($aMsg);
        }
        parent::__construct(join(" ", $aMsg), 1);
    }
}

?>
