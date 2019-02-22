<?php

class Technical extends Derivative {

  function __destruct() {
    parent::__destruct();
  }

  function techmd($dsid = 'TECHMD', $label = 'Technical metadata') {
    $return = MS_SYSTEM_EXCEPTION;
    //check for env variable for location of fits in case it is in a different location
    $fits = getenv('LISTENER_FITS_PATH');
    if (empty($fits)) {
      $fits = '/opt/fits/fits.sh';
    }
    $this->log->lwrite('Starting processing', 'PROCESS_DATASTREAM', $this->pid, $dsid);

    $output_file = $this->temp_file . '_TECHMD.xml';
    $command = "$fits -i $this->temp_file -xc -o $output_file 2>&1";
    $valid_fits = FALSE;
    $output = array();
    exec($command, $output, $return);
    if ($return == MS_SUCCESS) {
      if ($this->verify_fits_xml($output_file)) {
        $valid_fits = TRUE;
      }
    }
    // It failed, lets try a simpler command 
    if (!$valid_fits) {
      $command = "$fits -i $this->temp_file -x -o $output_file 2>&1";
      $output = array();
      exec($command, $output, $return);
      if ($return == MS_SUCCESS) {
        if ($this->verify_fits_xml($output_file)) {
          $valid_fits = TRUE;
        }
      }
    }
    // In case of disaster, fall back to the simplest possibly command line.
    if (!$valid_fits) {
      $command = "$fits -i $this->temp_file -o $output_file 2>&1";
      exec($command, $output, $return);
      if ($return == MS_SUCCESS) {
        if ($this->verify_fits_xml($output_file)) {
          $valid_fits = TRUE;
        }
      }
    }
    if ($valid_fits) {
      $log_message = "$dsid derivative created using FITS with command - $command || SUCCESS";
      $return = $this->add_derivative($dsid, $label, $output_file, 'text/xml', $log_message);
      if ($return == MS_SUCCESS) {
        $this->log->lwrite("Updated $dsid datastream", 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'SUCCESS');
      }
    }
    else {
      $this->log->lwrite("Could not find the file '$output_file' for the Techmd derivative!" . implode(', ', $output) . "\nReturn value: $return", 'FAIL_DATASTREAM', $this->pid, 'techmd', NULL, 'ERROR');
    }
    return $return;
  }

  /**
   * Helper to verify the fits xml file size is greater then 0.
   *
   * borrowed from the islandora_fits module
   *
   * This function does not verify the contents of the provided xml file.
   * WARNING: This function will delete said file if its file size
   *   is equel to 0.
   *
   * @param String $xml_file
   *   The path to the created xml file to validate
   */
  function verify_fits_xml($xml_file) {
    if (filesize($xml_file) > 0) {
      return TRUE;
    }
    else {
      // Fits wont write to a file if it already exists.
      if (file_exists($xml_file)) {
        unlink($xml_file);
      }
      return FALSE;
    }
  }

}

?>
