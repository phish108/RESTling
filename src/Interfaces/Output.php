<?php

namespace RESTling\Interfaces;

interface Output {

    /**
 	 * Sets the status code for the response.
 	 *
 	 * @param integer $code
 	 * @return void
	 */
	public function setStatus($code);

    /**
 	 * Sets the content type for the response. 
 	 *
 	 * @param string $contentType
 	 * @return void
	 */
	public function setContentType($contentType);

    /**
 	 * Adds the message to the end of the error traceback.
     *
     * The traceback is only formatted if an error code is set an not
     * data has been previously sent to the client via the data() or bypass()
     * methods.
 	 *
 	 * @param string $message
 	 * @return void
	 */
	public function addTraceback($message);

    /**
 	 * Used by a RESTling\Service instance to set the CORS context for the
     * current request. This method must be called before the process()
     * method.
     *
     * If invalid information is passed, then the call will be silently
     * ignored.
     *
     * The $methods parameter can contain a string or an array.
     * Arrays will be stringified with space-separators.
 	 *
 	 * @param string $origin
     * @param mixed $methods
 	 * @return void
	 */
	public function setCORSContext($origin, $methods);

    /**
 	 * Will interact with the model to generate the output.
     *
     * This method will generate all relevant headers, including any CORS
     * related information.
 	 *
 	 * @param \RESTling\Interfaces\Model $model
 	 * @return void
	 */
	public function process($model);

    /**
     * the data() method sends a data chunk to the client.
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
