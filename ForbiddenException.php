<?php
class ForbiddenException extends Exception
{
    public function __construct($message = 'HTTP 403 - Forbidden: can not run operation.', $code = 0) {
        parent::__construct($message, $code);
    }

    // custom string representation of object
    public function __toString() {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
}
?>
