<?php
namespace RESTling\Interfaces;

interface Model {
    public function getHeaders();
    public function addError($message);

    public function getErrors();

    public function hasData();
    public function getLocation();

    /**
 	 * Adds a new Worker object for post processing.
 	 *
 	 * @param \RESTling\WorkerInterface $worker
 	 * @return void
     * @throws WorkerInterfaceError
	 */
	public function addWorker($worker);

    /**
 	 * Runs all Workers that have been previously added through addWorker().
 	 *
 	 * @return void
	 */
	public function runWorkers();

    /**
 	 * passes any data that should be sent to the client to the output model.
     *
     * A model needs to implement a streaming API if this function runs until
     * all content has been delivered.
     *
     * This function runs a non-caching environment, so every data passed to
     * the ```$outputModel``` will be immediately sent to the client. This will
     * not work around any HTTP server level caching.
     *
     * if a model handles its own data streaming, then it should use the
     * $outputModel's ```bypass()``` method.
 	 *
 	 * @param \RESTling\OutputInterface $outputModel
	 */
    public function handleData($output);
}

?>
