<?php

require_once 'Derivatives.php';

class Scholar extends Derivative {
  
  function __destruct() {
    parent::__destruct();
  }

  /**
   * TODO: update this to work with location env variable and add 
   * and correct path to document embargo.xml
   * @param string $dsid
   * @param string $label
   * @return int
   */
  function scholarPolicy($dsid = 'POLICY', $label = "Embargo policy - Both") {
    $this->log->lwrite('Starting processing', 'PROCESS_DATASTREAM', $this->pid, $dsid);
    try {
      $output_file = '/opt/php_listeners/document-embargo.xml';
      $log_message = "$dsid derivative uploaded from the file system || SUCCESS";
      $this->add_derivative($dsid, $label, $output_file, 'text/xml', $log_message, FALSE);
    } catch (Exception $e) {
      $this->log->lwrite("Could not create the $dsid derivative!", 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
    }
    return 0;
  }

}

?>
