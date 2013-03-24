<?php

class Technical extends Derivative {
  
  function __destruct() {
    parent::__destruct();
  }

  function TECHMD($dsid = 'TECHMD', $label = 'Technical metadata') {
    $this->log->lwrite('Starting processing', 'PROCESS_DATASTREAM', $this->pid, $dsid);
    try {
      $output_file = $this->temp_file . '_TECHMD.xml';
      $command = "/var/www/fits/fits.sh -i $this->temp_file -o $output_file";
$this->log->lwrite("got here 1",'','','','','');	
      exec($command, $techmd_output, $return);
$this->log->lwrite("got here 2",'','','','','');	
      $log_message = "$dsid derivative created using FITS with command - $command || SUCCESS";
      $this->add_derivative($dsid, $label, $output_file, 'text/xml', $log_message);
$this->log->lwrite("got here 3",'','','','','');	
    } catch (Exception $e) {
      $this->log->lwrite("Could not create the $dsid derivative! " . $e->getMessage(), 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
      unlink($output_file);
      return 'ERROR';
    }
    unlink($output_file);
    return $return;
  }

}

?>
