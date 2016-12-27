<?php
namespace RESTling\Interfaces;

interface Worker {
    /**
 	 * Runs the service process.
 	 *
     * This function must be called in order to intiate the service handling.
     *
     * @param \RESTling\ModelInterface $model - default null
 	 * @return void
	 */
    public function run($model=null);
}
?>