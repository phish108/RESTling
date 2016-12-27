<?php
namespace RESTling\Interfaces;

interface Security extends Validator {
    /**
 	 * Verifies the functional authororization within a scope.
     *
     * A verification must raise an exception if the scope is violated.
     *
     * A verification may ignore the verification request in a given
     * $model/$input setting.
 	 *
 	 * @param mixed $model
     * @param \RESTling\Interfaces\Input
 	 * @return void
	 */
	public function verify($model, $input);

    /**
 	 * Sets the authorization scope for the current request.
 	 *
 	 * @param array $scopeSet
 	 * @return void
	 */
	public function setScopes($scopeSet);
}
?>
