<?php

namespace RESTling\Interfaces;

interface Input {

    public function setPathParameters($paramlist);

    /**
 	 * returns the raw query string
 	 *
 	 * @param type
 	 * @return void
	 */
	public function getQueryString();

    public function getParameter($pname, $source = "");
    public function hasParameter($pname, $source = "");
    public function hasParameterSchema($pname, $source, $schema);

    public function parse();
}

?>
