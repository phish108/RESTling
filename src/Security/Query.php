<?php
namespace RESTling\Security;

// the security model needs to implement a validateToken($token) method
class Query extends \RESTling\Security {
    public function validate($model, $input) {
        parent::validate($model, $input);

        $cookiename = "session";
        if (method_exists($model, "getQueryAuthName")) {
            $cookiename = $model->getQueryAuthName();
        }

        // ignore if we have no cookie
        if ($input->hasParameter($cookiename, "query")) {
            $auth = $input->getParameter($cookiename, "query");
            if (empty($auth)) {
                throw new \RESTling\Exception\Forbidden();
            }

            $model->validateQueryAuth($auth);
            $this->success();
        }
    }
}

?>
