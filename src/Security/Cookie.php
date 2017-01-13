<?php
namespace RESTling\Security;

// the security model needs to implement a validateToken($token) method
class Cookie extends \RESTling\Security {
    public function validate($model, $input) {
        parent::validate($model, $input);

        $cookiename = "session";
        if (method_exists($model, "getCookieName")) {
            $cookiename = $model->getCookieName();
        }

        // ignore if we have no cookie
        if ($input->hasParameter($cookiename, "cookie")) {
            $auth = $input->getParameter($cookiename, "cookie");
            if (empty($auth)) {
                throw new \RESTling\Exception\Forbidden();
            }
            $model->validateCookie($auth);
            $this->success();
        }
    }
}

?>
