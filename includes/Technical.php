<?php

class Technical extends Derivative {

  function __destruct() {
    parent::__destruct();
  }

  function techmd($dsid = 'TECHMD', $label = 'Technical metadata') {
    $this->log->lwrite('Starting processing', 'PROCESS_DATASTREAM', $this->pid, $dsid);
    try {
      $output_file = $this->temp_file . '_TECHMD.xml';
      $command = "/opt/fits/fits.sh -i $this->temp_file -o $output_file 2>&1";
      exec($command, $techmd_output = array(), $return);

      if (file_exists($output_file)) {
        $log_message = "$dsid derivative created using FITS with command - $command || SUCCESS";
        $this->add_derivative($dsid, $label, $output_file, 'text/xml', $log_message);
      }
      else {
        $this->log->lwrite("Could not find the file '$output_file' for the Techmd derivative!\nTesseract output: " . implode(', ', $techmd_output) . "\nReturn value: $return", 'FAIL_DATASTREAM', $this->pid, 'techmd', NULL, 'ERROR');
      }
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
