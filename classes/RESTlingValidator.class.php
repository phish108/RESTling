<?php
class RESTlingValidator extends Logger
{
    public function validate()
    {
        return true;
    }

    public function error()
    {
        // return authentication required by default
        return 401;
    }
}
?>