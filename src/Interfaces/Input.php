<?php

namespace RESTling\Interfaces;

interface Input {

    /**
 	 * If any path parameters were identified, then these parameters can be
     * added to the input and included for input validation
 	 *
 	 * @param array $parameterList
 	 * @return void
	 */
	public function setPathParameter($parameterList);

    public function setQueryParameter($queryParameterList);
    public function setHeaderParameter($headerList);

    /**
 	 * returns the raw query string
 	 *
 	 * @param type
 	 * @return void
	 */
	public function getQueryString();

    /**
 	 * Returns a parameter from the curent input.
     *
     * If source is set, then the parameter will be chosesn from the provided
     * source.
     *
     * If a parameter is set in more than one source and source is not set, then
     * the first matching source is used.
     *
     * Valid sources are:
     *
     * - query - for parameters in the GET query string
     * - body - for parameters in the POST data
     * - cookie - for cookies
     * - path - for path parameters
     * - header - for HTTP headers
 	 *
     * If no source is set, then the search order is as provided above.
     *
 	 * @param string $parameterName
     * @param string $parameterSource - default ""
 	 * @return void
	 */
	public function getParameter($pname, $source = "");

    /**
 	 * Checks if a parameter exists in the curent input.
     * If source is set, then the parameter is checked only at the provided
     * source.
     *
     * The same sources apply as for getParameter().
 	 *
 	 * @param mixed $parameterName - string or array of strings
     * @param string $parameterSource - default ""
 	 * @return void
	 */
	public function hasParameter($pname, $source = "");

    /**
 	 * Checks if a parameter at the given source complies with the given schema.
     *
     * The same sources apply as for getParameter().
 	 *
 	 * @param string $parameterName
     * @param string $parameterSource
     * @param mixed $schema
 	 * @return void
	 */
	public function hasParameterSchema($pname, $source, $schema);

    /**
 	 * Parses the incoming content body.
 	 *
 	 * @return void
     * @throws \RESTling\Exception\EmptyInputData - if no input is present
     * @throws \RESTling\Exception\BrokenInput - if input cannot be processed
	 */
	public function parse();

    public function setContentType($contentType);
    public function getContentType();


    /**
 	 * returns the requested content type for the response, if any.
     *
     * This method is used ONLY by the service class.
 	 *
 	 * @param type
 	 * @return void
	 */
	public function getResponseContentType();

    /**
 	 * requests a content type for the response. This will overwrite any
     * client requests.
 	 *
 	 * @param string $contentType - valid Mime Type string.
 	 * @return void
	 */
	public function setResponseContentType($contentType);

    /**
 	 * Alias for setResponseContentType($contentType).
 	 *
 	 * @param string $contentType - valid Mime Type string.
 	 * @return void
	 */
	public function setResponseType($contentType);

    public function addActiveUser($userid);
    public function hasActiveUser($userid);

    public function hasUser();
    public function getUser();

    /**
 	 * returns the full body object of the request.
 	 *
 	 * @return array Body object or null if no body is present.
	 */
	public function getBody();
}

?>
