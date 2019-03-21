<?php

require_once 'Derivatives.php';

class Pdf extends Derivative {

  function __destruct() {
    parent::__destruct();
  }

  /**
   * TODO refactor this function or get rid of it?
   * @param type $dsid
   * @param type $label
   * @return type
   */
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
      }
      catch (Exception $e) {
        $this->log->lwrite("Could not create the $dsid derivative!", $return . ' ' . $pdfa_output, 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
        unlink($output_file);
      }
      return $return;
    }
  }

  /**
   * Creates a new datastream derivative based on the type element passed in $params.
   *
   * @param string $dsid
   *   The output dsid
   * @param string $label
   *   the datastream label
   * @param array $params
   *   $params should include $params['type'] = 'pdf' or $params['type'] = 'txt'
   *   if the type is pdf this function will attempt to create a pdf datastream
   *   assuming the input dsid is an image type
   *
   *   if the type is txt this function will attempt to create a text datastream
   *   assuming the input dsid is a pdf type
   *
   * @return int|string
   *   0 = success
   */
  function createDerivative($dsid = 'PDF', $label = 'PDF', $params) {
    $this->log->lwrite('Starting processing', 'PROCESS_DATASTREAM', $this->pid, $dsid);
    $type = $params['type'];

    if (empty($type)) {
      $this->log->lwrite("Failed to create derivative for $dsid no type provided", 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'ERROR');
      return MS_FEDORA_EXCEPTION;
    }
    $output_file = $this->temp_file . "_pdf.$type";
    switch ($type) {
      case "pdf":
        //$command = 'convert ' . $this->temp_file . ' ' . $output_file . ' 2>&1';
        $command = 'tesseract ' . $this->temp_file . ' ' . $output_file . ' -l eng -psm 3 pdf 2>&1';
        $mimetype = 'application/pdf';
        //tesseract adds and extentsion
        $output_file = $output_file . '.pdf';
        break;

      case "txt":
        $command = 'pdftotext ' . $this->temp_file . ' ' . $output_file . ' 2>&1';
        $mimetype = 'text/plain';
        break;

      default:
        //$command = 'convert ' . $this->temp_file . ' ' . $output_file . ' 2>&1';
        $command = 'tesseract ' . $this->temp_file . ' ' . $output_file . ' -l eng -psm 3 pdf 2>&1';
        $mimetype = 'application/pdf';
        //tesseract adds and extentsion
        $output_file = $output_file . '.pdf';
    }
    $return = MS_SYSTEM_EXCEPTION;
    if (file_exists($this->temp_file)) {
      $pdf_output = array();
      exec($command, $pdf_output, $return);
      if (file_exists($output_file)) {
        if($type == 'txt'){
          $this->sanitizeText($output_file);
        }
        $log_message = "$dsid derivative created with command - $command || SUCCESS";
        $return = $this->add_derivative($dsid, $label, $output_file, $mimetype, $log_message);
        if ($return == MS_SUCCESS) {
          $this->log->lwrite("Successfully added the $dsid derivative!", $return, 'SUCCESS', $this->pid, $dsid, NULL, 'INFO');
        }
      }
      else {
        $this->log->lwrite("Could not find the file '$output_file' for output: " . implode(',', $pdf_output) . "\nReturn value: $return", 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
        $return = MS_SYSTEM_EXCEPTION;
      }
    }
    else {
      $this->log->lwrite("Could not create the $dsid derivative! could not find file $this->temp_file " . $return . ' ' . implode($pdf_output), 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
      $return = MS_OBJECT_NOT_FOUND;
    }
    return $return;
  }

  function sanitizeText($file){
    $full_text = file_get_contents($file);
    $full_text = preg_replace('/[^\x9\xA\xD\x20-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', ' ', $full_text);
    file_put_contents($file, $full_text);

  }
}

