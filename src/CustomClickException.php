<?php

namespace Clickspace\ClickException;

class CustomClickException extends \Exception
{

    public $category;
    public $code;
    public $message;
    public $httpCode;

    public function __construct($category, $code, $message = "", $httpCode = 500, $previous = null)
    {
        $this->category = $category;
        $this->code = $code;
        $this->message = $message;
        $this->httpCode = $httpCode;
        parent::__construct($message, $httpCode, $previous);
    }

}