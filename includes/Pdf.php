<?php
require_once 'Derivatives.php';
class Pdf extends Derivative {

  function __destruct() {
    parent::__destruct();
  }

  function scholarPdfa($dsid = 'PDF', $label = 'PDF') {
    if ($this->created_datastream == 'OBJ') {
      $this->log->lwrite('Starting processing because the ' . $this->created_datastream . ' datastream was added', 'PROCESS_DATASTREAM', $this->pid, $dsid);
      try {
        $output_file = $this->temp_file . '_Scholar_PDFA.pdf';
        $pdfa_output = array();
        if ($this->mimetype == 'application/pdf') {
          $command = "gs -dPDFA -dBATCH -dNOPAUSE -dUseCIEColor -sProcessColorModel=DeviceCMYK -sDEVICE=pdfwrite -sPDFACompatibilityPolicy=1 -sOutputFile=$output_file $this->temp_file 2>&1";
          exec($command, $pdfa_output, $return);
          $log_message = "$dsid derivative created using GhostScript with command - $command || SUCCESS";
        }
        else {
          $command = "java -jar /opt/jodconverter-core-3.0-beta-4/lib/jodconverter-core-3.0-beta-4.jar $this->temp_file $output_file 2>&1";
          exec($command, $pdfa_output, $return);
          $log_message = "$dsid derivative created using JODConverted and OpenOffice with command - $command || SUCCESS";
        }

        if (file_exists($output_file)) {
          $this->add_derivative($dsid, $label, $output_file, 'application/pdf', $log_message);
        }
        else {
          $this->log->lwrite("Could not find the file '$output_file.html' for the HOCR derivative!\nTesseract output: " . implode(', ', $pdfa_output));
          return $return;
        }
      } catch (Exception $e) {
        $this->log->lwrite("Could not create the $dsid derivative!", $return . ' ' . $pdfa_output, 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
        unlink($output_file);
      }
      return $return;
    }
  }

  function toPdf($dsid = 'PDF', $label = 'PDF') {
    $this->log->lwrite('Starting processing', 'PROCESS_DATASTREAM', $this->pid, $dsid);
    if (file_exists($this->temp_file)) {
      try {
        $output_file = $this->temp_file . '_pdf.pdf';
        $command = 'convert ' . $this->temp_file . ' ' .$output_file . ' 2>&1';
        $pdf_output = array();
        exec($command, $pdf_output, $return);
        if (file_exists($output_file)) {
          $log_message = "$dsid derivative created using convert with command - $command || SUCCESS";
          $this->add_derivative($dsid, $label, $output_file, 'application/pdf', $log_message);
        }
        else {
          $this->log->lwrite("Could not find the file '$output_file' for output: " . implode(', ', $pdf_output) . "\nReturn value: $return", 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
          return $return;
        }
      } catch (Exception $e) {
        $this->log->lwrite("Could not create the $dsid derivative! " . $return . ' ' . implode(',', $pdf_output), 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
        unlink($output_file);
      }
    }
    else {
      $this->log->lwrite("Could not create the $dsid derivative! could not find file $this->temp_file " . $return . ' ' . implode($pdf_output), 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
    }
    return $return;
  }

}
?>