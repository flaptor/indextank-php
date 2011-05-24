<?php

class Indextank_Response
{
    public $status = NULL;
    public $response = NULL;

    function __construct($status, $response)
    {
        $this->status = $status;
        $this->response = $response;
    }
}
