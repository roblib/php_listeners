<?php

class Image extends Derivative {
  
  function __destruct() {
    parent::__destruct();
  }

  function Scholar_Policy($dsid = 'POLICY', $label = "Embargo policy - Both") {
    $this->log->lwrite('Starting processing', 'PROCESS_DATASTREAM', $this->pid, $dsid);
    try {
      $output_file = '/opt/php_listeners/document-embargo.xml';
      $log_message = "$dsid derivative uploaded from the file system || SUCCESS";
      $this->add_derivative($dsid, $label, $output_file, 'text/xml', $log_message, FALSE);
    } catch (Exception $e) {
      $this->log->lwrite("Could not create the $dsid derivative!", 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
    }
    return TRUE;
  }

}

?>
