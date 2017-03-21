<?php
namespace RESTling;

/**
 * Internal exception handler.
 *
 * Generates the exception message from the exception class name.
 *
 * @return \RESTling\Exception
 */
class Exception extends \Exception {
    const responseCode = -1;
    public function __construct($aMessage) {
        $cls = explode('\\', get_class($this)); // actual classname
        $mName = array_pop($cls);
        $aMsg = preg_split('/(?=[A-Z])/',$mName);
        if (empty($aMessage)) {
            if (empty($aMsg[0])) {
                array_shift($aMsg);
            }
        }
        else {
            $aMsg = [$aMessage];
        }
        parent::__construct(join(" ", $aMsg), $this::responseCode);
    }
}

?>
