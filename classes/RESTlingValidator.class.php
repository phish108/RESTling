<?php
class RESTlingValidator extends Logger
{
    protected $service;

    public function setService($service)
    {
        $this->service = $service;
    }

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