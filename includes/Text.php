<?php
/* TODO ocr function is outdated and needs work currently only using allOcr
*/
class Text extends Derivative {

  /**
   * this function is to process all type of ocr files using tesseract
   *
   * calls a couple of functions internally.  We use this when we are
   * creating multiple OCR datastreams.  Since it can 2 minutes to ocr a document
   * we only call tesseract once then use an strip the tags to create the ocr
   * from the hocr.
   *
   * @param string $dsid
   * The dsID that will be added to the fedora object
   *
   * @param string $label
   * The label of data stream
   *
   * @param string $language
   * the processing language
   *
   * @return int
   */
  function hOcr($dsid = 'HOCR', $label = 'HOCR', $params = array('language' => 'eng')) {
    $language = $params['language'];
    $this->log->lwrite('Starting processing', 'PROCESS_DATASTREAM', $this->pid, 'HOCR');
    $return = MS_SYSTEM_EXCEPTION;
    $hocr_output = array();
    if (file_exists($this->temp_file)) {
      $output_file = $this->temp_file . '_HOCR';
      $command = "tesseract $this->temp_file $output_file -l $language hocr 2>&1";
      exec($command, $hocr_output, $return);
      $this->log->lwrite(implode(', ', $hocr_output) . "\nReturn value: $return", 'PROCESS_DATASTREAM', $this->pid, 'HOCR', NULL, 'INFO');
      if (file_exists($output_file . '.hocr')) {
        //TODO correct hardcoded version text
        $log_message = "HOCR derivative created by tesseract v3.03 using command - $command || SUCCESS";
        $return = $this->add_derivative($dsid, $label, $output_file . '.hocr', 'text/html', $log_message);
      }
      else {
        $this->log->lwrite("Initial call to create ocr failed, converting to png for another attempt", 'FAIL_DATASTREAM', $this->pid, 'HOCR', NULL, 'ERROR');
        $convert_command = "convert -monochrome " . $this->temp_file . " " . $this->temp_file . "_JPG2.png 2>&1";
        exec($convert_command, $hocr_output = array(), $return);
        $this->log->lwrite("attempted conversion to png: " . implode(', ', $hocr_output) . "\nReturn value: $return", 'PROCESS_DATASTREAM', $this->pid, 'HOCR', NULL, 'INFO');
        $command = "tesseract " . $this->temp_file . "_JPG2.png " . $output_file . " -l $language hocr 2>&1";
        exec($command, $hocr_output = array(), $return);
        $this->log->lwrite("attempting OCR on png derivative: " . implode(', ', $hocr_output) . "\nReturn value: $return", 'PROCESS_DATASTREAM', $this->pid, 'HOCR', NULL, 'INFO');
        if (file_exists($output_file . '.hocr')) {
          $log_message = "HOCR derivative created by using ImageMagick to convert to jpg using command - $convert_command - and tesseract v3.03 using command - $command || SUCCESS ~~ OCR of original TIFF failed so the image was converted to a PNG and reprocessed.";
          $return = $this->add_derivative($dsid, $label, $output_file . '.hocr', 'text/html', $log_message);
        }
        else {
          $this->log->lwrite("Could not find the file '$output_file.hocr' for the HOCR derivative!\nTesseract output: " . implode(', ', $hocr_output) . "\nReturn value: $return", 'FAIL_DATASTREAM', $this->pid, 'HOCR', NULL, 'ERROR');
          $return = MS_SYSTEM_EXCEPTION;
        }
      }
      unlink($output_file . '.hocr');
    }
    else {
      $this->log->lwrite("Could not find the input file '$this->temp_file' for the HOCR derivative!", 'FAIL_DATASTREAM', $this->pid, 'allOcr', NULL, 'ERROR');
    }
    return $return;
  }

  /**
   * creates the raw OCR stream by stripping tags from hocr
   * faster then calling tesseract again
   *
   * @param string $file
   * the temp files path
   */
  function ocrFromHocr($dsid = 'FULL_TEXT', $label = 'FULL_TEXT', $params = array('language' => 'eng')) {
    $this->log->lwrite('Starting processing', 'PROCESS_DATASTREAM', $this->pid, $dsid);
    $return = MS_SUCCESS;
    if (file_exists($this->temp_file)) {
      $hocr_xml = new DOMDocument();
      $hocr_xml->load($this->temp_file);
      $raw_ocr = strip_tags($hocr_xml->saveHTML());
      $log_message = "OCR derivative created from hocr by stripping tags || SUCCESS";
      $return = $this->add_derivative($dsid, $label, $raw_ocr, 'text/plain', $log_message, TRUE, FALSE);
    } else {
      $this->log->lwrite('ERROR PROCESSING', 'PROCESS_DATASTREAM', $this->pid, $dsid);
      $return = MS_SYSTEM_EXCEPTION;
    }
    return $return;
  }

  /**
   * this function is to process ocr files using tesseract
   *
   * if params array contains an element with key = hocr the command will
   * include the value of that element at the end of the command.
   *
   * the same array element will also determine what mimetype to use either text/plain or
   * text/html
   *
   * @param string $dsid
   * The dsID that will be added to the fedora object
   *
   * @param string $label
   * The label of data stream
   *
   * @param string $language
   * the processing language
   *
   * @return int
   */
  function ocr($dsid = 'OCR', $label = 'Scanned text', $params = array('language' => 'eng')) {
    $this->log->lwrite('Starting processing', 'PROCESS_DATASTREAM', $this->pid, $dsid);
    $language = $params['language'];
    $hocr = isset($params['hocr']) ? '' : $params['hocr'];
    $ext = ($hocr == 'hocr') ? 'hocr' : '.txt';
    $mime_type = ($ext == 'hocr') ? 'text/html' : 'text/plain';
    $ingest = NULL;
    $return = MS_SYSTEM_EXCEPTION;
    if (file_exists($this->temp_file)) {
      $output_file = $this->temp_file . '_OCR';
      $command = "tesseract $this->temp_file $output_file -l $language -psm 1 $hocr 2>&1";
      exec($command, $ocr_output = array(), $return);
      if (file_exists($output_file . '.txt')) {
        $log_message = "$dsid derivative created by tesseract v3.0.1 using command - $command || SUCCESS";
        $return = $this->add_derivative($dsid, $label, $output_file . $ext, $mime_type, $log_message);
      }
      else {
        $convert_command = "/usr/bin/convert -monochrome " . $this->temp_file . " " . $this->temp_file . "_JPG2.jpg 2>&1";
        exec($convert_command, $convert_output, $return);
        $command = "tesseract " . $this->temp_file . "_JPG2.jpg " . $output_file . " -l $language -psm 1 $hocr 2>&1";
        exec($command, $ocr2_output = array(), $return);
        if (file_exists($output_file . '.txt')) {
          $log_message = "$dsid derivative created by using ImageMagick to convert to jpg using command - $convert_command - and tesseract v3.0.1 using command - $command || SUCCESS ~~ OCR of original TIFF failed and so the image was converted to a JPG and reprocessed.";
          $return = $this->add_derivative($dsid, $label, $output_file . $ext, $mime_type, $log_message);
        }
        else {
          $this->log->lwrite("Could not find the file '$output_file.txt' for the $dsid derivative!\nTesseract output: " . implode(', ', $ocr_output) . " - Return value: $return", 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
          $return = MS_SYSTEM_EXCEPTION; //tesseract may return 0 even on an error so we set the error here          
        }
      }
    }
    else {
      $this->log->lwrite("Could not find the input file '$this->temp_file' for the $dsid derivative!", 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
    }
    return $return;
  }
}

?>