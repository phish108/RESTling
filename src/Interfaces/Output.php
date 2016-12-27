<?php

namespace RESTling\Interfaces;

interface Output {

    public function setStatus($code);
    public function setContentType($ct);
    public function addTraceback($message);
    public function setCORSContext($origin, $methods);
    public function process($model);

    /**
     * the data() method sends a data chunk to the client
     */
    public function data($data);

    /**
 	 * The bypass() method allows models to handle data delivery independently.
     * Without involving service level output handling.
     *
     * This does not affect general handling.
	 */
	public function bypass();
}

?>
