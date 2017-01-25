<?php

namespace RESTling;

/**
 * OutputHelper provides an interface for models, so they can hand down data to the
 * output model, without having to implement any output logic themselves.
 *
 * Models should use 
 *
 * This class is called BEFORE RESTling determines, which output processor is used.
 * An instance of this class is passed to the models operation handler.
 */
class OutputHelper {
    public $data = "";
    public $content_type = "text/plain";

    private $outputCallback = null;

    private $errors   = [];
    private $headers  = [];
    private $worker   = [];
    private $location = null;

    public function addHeader($header, $value="") {
        if (is_string($header) && strlen($header)) {
            if (!isset($value)) {
                $value = "";
            }
            $this->headers[$header] = $value;
        }
    }

    public function getHeaders() {
        return $this->headers;
    }

    public function addWorker($worker) {
        if (!($worker && $worker instanceof \RESTling\Interfaces\Worker)) {
            throw new Exception\WorkerInterfaceMismatch();
        }
        $this->worker[] = $worker;
    }

    public function runWorkers() {
        foreach ($this->worker as $worker) {
            $worker->run($this);
        }
    }

    public function addError($message) {
        $this->errors[] = $message;
    }

    public function getErrors() {
        return $this->errors;
    }

    public function getLocation() {
        return $this->location;
    }

    protected function redirect($location, $immediate= true) {
        if (!empty($location)) {
            $this->location = $location;
            if ($immediate) {
                throw new Exception\Redirect();
            }
        }
    }

    /**
 	 * sets an output data handler.
     * The data handler MUST accept 2 parameters
     * - the output processor (instanceof \RESTling\Output)
     * - the output manager (the instance of this class)
     *
     * an output data handler may completely ignore the output manager.
 	 *
 	 * @param callback $callback
 	 * @return void
	 */
	public function dataHandler($callback) {
        $this->outputCallback = $callback;
    }

    public function hasData() {
        return (!empty($this->data) || isset($this->outputHandler));
    }

    public function handleData($output) {
        if ($this->outputCallback) {
            call_user_func($this->outputCallback, $output, $this);
        }
        elseif (!empty($this->data)) {
            $output->data($this->data);
        }
    }
}
?>
