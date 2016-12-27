<?php
namespace RESTling;

interface ServiceInterface extends WorkerInterface {

    /**
     * This function allows to specify, which referring sites can access this
     * service.
     *
     * Both parameters can be either strings or arrays.

     * CORS handling of RESTling services allows configuration of hosts and
     * methods during runtime.
     *
     * This method allows to set CORS hosts and related methods. Both $methods
     * and $hosts can be either strings or arrays. If a parameter is
     * passed as a string, this string may contain a space separated list, that
     * will get automatically expanded.
     *
     * Note that '*' is a valid host name that is used if any other host
     * definition fails to match.
     *
     * By default, CORS is not permitted.
     *
     * It is only possible to add but not to remove methods to a CORS
     * definition.
 	 *
 	 * @param mixed $host
     * @param mixed $methods
 	 * @return void
     *
     * @throws Exception 'Invalid CORS Parameter', if parameters are of invalid type.
	 */
    public function addCORSHost($host, $methods);

    /**
 	 * RESTling service depend at least on a model that defines the operations
     * of a service. The setModel() method is used for connecting a model with
     * the RESTling service controller.
     *
     * All models MUST implement a \RESTling\ModelInterface.
     *
     * If $secure is set to true, then this method will NOT overwrite any
     * existing model.
 	 *
 	 * @param \RESTling\ModelInterface $model
     * @param bool $secure - defaults to false
 	 * @return void
     *
     * @throws Exception "Not a RESTling\ModelInterface"
     * @throws Exception "Model Already Set"
     * If secure validates to true, then this method will throw an Exception if
     * the model is already set.
	 */
	public function setModel($model, $secure = false);

    /**
 	 * RESTling has a plugable security system that needs to interact with
     * the system internals of the application logic.
     *
     *
 	 * @param \RESTling\Security\ModelInterface $securityModel
 	 * @return void
     *
     * @throws Exception "Not a RESTling\Security\ModelInterface"
     *
     * @throws Exception "Model Already Set"
     * If secure validates to true, then this method will throw an Exception if
     * the model is already set.
	 */
	public function setSecurityModel($model, $secure = false);

    /**
     * Adds a security validator.
     *
     * Note that a service may implement different security schemes at the same
     * time. RESTling service process all added security handlers until one
     * rejects or accepts the authorization. If one RESTling security handler
     * rejects access, no access should be granted even if other security
     * handler have accepted an authorization.
     *
     * @param \RESTling\SecurityInterface $securityHandler
     * @throws Exception "Not a RESTling\SecurityInterface"
     */
    public function addSecurityHandler($securityHandler);

    /**
 	 * Allows to connect input processors to incoming Content-Types.
     *
     * This method populates the content map for inputs so the service
     * can automatically select the appropriate input processor for incoming
     * content.
     *
     * All handler types should implement a \RESTling\InputInterface. This
     * is not validated at this point.
     *
     * Default content types are mapped as following:
     *
     * - application/json : \RESTling\Input\JSON
     * - application\/x-www-form-urlencoded : \RESTling\Input
     * - multipart/form-data : \RESTling\Input\MultiPartForm
     * - text/yaml : \RESTling\Input\YAML
     *
     * This method allows applications to overwrite and extend this default
     * behavior.
 	 *
 	 * @param string $contentType
     * @param string $handlerType
 	 * @return void
	 */
	public function addInputContentTypeMap($contentType, $handlerType);

    /**
     * Allows to connect output processors for outgoing Content-Types.
     *
     * This method populates the content map for outputs so the service
     * can automatically select the appropriate output processor for response
     * content.
     *
     * All handler types should implement a \RESTling\OutputInterface. This
     * is not validated at this point.
     *
     * Default content types are mapped as following:
     *
     * - application/json : \RESTling\Output\JSON
     * - text/yaml : \RESTling\Output\YAML
     * - text/plain : \RESTling\Output
     *
     * By default, the \RESTling\Ouput handler is selected.
     *
     * This method allows applications to overwrite and extend this default
     * behavior.
 	 *
 	 * @param string $contentType
     * @param string $handlerType
 	 * @return void
	 */
	public function addOutputContentTypeMap($contentType, $handlerType);

    /**
 	 * Runs the service process.
 	 *
     * This function must be called in order to intiate the service handling.
     *
 	 * @return void
	 */
    public function run();
}
?>
