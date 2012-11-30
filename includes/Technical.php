<?php

class Technical extends Derivative {

  function TECHMD($dsid = 'TECHMD', $label = 'Technical metadata') {
    $this->log->lwrite('Starting processing', 'PROCESS_DATASTREAM', $this->pid, $dsid);
    try {
      $output_file = $this->temp_file . '_TECHMD.xml';
      $command = "/opt/fits/fits.sh -i $this->temp_file -o $output_file";
      exec($command, $techmd_output, $return);
      $log_message = "$dsid derivative created using FITS with command - $command || SUCCESS";
      $this->add_derivative($dsid, $label, $output_file, 'text/xml', $log_message);
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
