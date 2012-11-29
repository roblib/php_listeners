<?php

class Technical extends Derivative {

  function TECHMD($dsid = 'TECHMD', $label = 'Technical metadata') {
    $this->log->lwrite('Starting processing', 'PROCESS_DATASTREAM', $this->pid, $dsid);
    try {
      $output_file = $this->temp_file . '_TECHMD.xml';
      //$command = "/opt/fits/fits.sh -i $this->temp_file -o $output_file -xc"; //changed this as it seems as most people don't use switchs and -xc seems to fail on wavs
      $command = "/opt/fits/fits.sh -i $this->temp_file -o $output_file";
      exec($command, $techmd_output, $return);
      $this->update_rels_from_tech($output_file);
      $log_message = "$dsid derivative created using FITS with command - $command || SUCCESS";
      $this->add_derivative($dsid, $label, $output_file, 'text/xml', $log_message);
    } catch (Exception $e) {
      $this->log->lwrite("Could not create the $dsid derivative! " . $e->getMessage(), 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
      unlink($output_file);
    }

    unlink($output_file);
    return $return;
  }

}

?>
