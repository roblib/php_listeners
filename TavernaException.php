<?php
/**
 * @file this file is to process exceptions on taverna.
 */
class TavernaException extends Exception
{
    private $execption_type;
    
    public function __construct($message, $code = 0,$execption_type) {
        parent::__construct($message, $code);
        $this->execption_type=$execption_type;
    }

    // custom string representation of object
    public function __toString() {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
    
    public function getExceptionType() {
      return $this->execption_type;
    }
}
?>
