<?php
namespace RESTling\Interfaces;

interface Validator {
    /**
 	 * Executes the validation routine.
     *
     * If the validation failes, an implementation is expected to throw an
     * Exception.
     *
     * A validator may decide not to validate a request.
 	 *
 	 * @param mixed $model
     * @param \RESTling\Interface\Input
 	 * @return void
	 */
	public function validate($model, $input);

    /**
 	 * Returns true if the validation has succeeded.
     *
     * If no exception was raised and this function returns false, then
     * no validation took place. This allows to have multiple cascading
     * validators. 
 	 *
 	 * @return bool
	 */
	public function passes();
}

?>
