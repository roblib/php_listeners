<?php

class Pdf extends Derivative {
  
  function __destruct() {
    parent::__destruct();
  }

  function Scholar_PDFA($dsid = 'PDF', $label = 'PDF') {
    if ($this->created_datastream == 'OBJ') {
      $this->log->lwrite('Starting processing because the ' . $this->created_datastream . ' datastream was added', 'PROCESS_DATASTREAM', $this->pid, $dsid);
      try {
        $output_file = $this->temp_file . '_Scholar_PDFA.pdf';
        if ($this->mimetype == 'application/pdf') {
          $command = "gs -dPDFA -dBATCH -dNOPAUSE -dUseCIEColor -sProcessColorModel=DeviceCMYK -sDEVICE=pdfwrite -sPDFACompatibilityPolicy=1 -sOutputFile=$output_file $this->temp_file &> /var/log/phpfunctions/cmd1.log";
          exec($command, $pdfa_output, $return);
          $log_message = "$dsid derivative created using GhostScript with command - $command || SUCCESS";
        }
        else {
          $command = "java -jar /opt/jodconverter-core-3.0-beta-4/lib/jodconverter-core-3.0-beta-4.jar $this->temp_file $output_file &> /var/log/phpfunctions/cmd1.log";
          exec($command, $pdfa_output, $return);
          $log_message = "$dsid derivative created using JODConverted and OpenOffice with command - $command || SUCCESS";
        }
        $this->add_derivative($dsid, $label, $output_file, 'application/pdf', $log_message);
      } catch (Exception $e) {
        $this->log->lwrite("Could not create the $dsid derivative!", 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
        unlink($output_file);
      }
      return $return;
    }
  }

}

?>
