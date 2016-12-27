<?php
namespace RESTling\Security;

class Bearer extends \RESTling\Security {
    public function validate($model, $input) {
        parent::validate($model, $input);

        if ($input->hasParameter("Authorization", "header")) {
            $auth = $input->getParameter("Authorization", "header");
            $aAuth = explode(" ", $auth, 2);
            if (count($aAuth) == 2 && $aAuth[0] === "Bearer") {
                if (!$model || !method_exists($model, "validateToken")) {
                    throw new \RESTling\Exception\Security\TokenValidationUnsupported();
                }

                $model->validateToken($aAuth[1]);
                $this->success();
            }
        }
    }
}
?>
