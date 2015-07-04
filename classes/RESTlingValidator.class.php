<?php
class RESTlingValidator extends Logger
{
    protected $service; ///< internal handler for the service class.

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
        $this->service->authentication_required();
        return "";
    }
}
?>
