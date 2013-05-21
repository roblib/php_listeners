<?php

class Text extends Derivative {

  function __destruct() {
    parent::__destruct();
  }

  /**
   * Creates all ocr datastreams in one call
   */
  function allOcr($dsid = 'HOCR', $label = 'HOCR', $language = 'eng') {
    $this->log->lwrite('Starting processing', 'PROCESS_DATASTREAM', $this->pid, 'HOCR');

    if (isset($this->object['ENCODED_OCR'])) {
      $this->log->lwrite('ENCODED_OCR exists skipping all OCR tasks', 'PROCESS_DATASTREAM', $this->pid, 'HOCR');
      return 0;
    }

    try {
      if (file_exists($this->temp_file)) {
        $output_file = $this->temp_file . '_HOCR';
        $command = "/usr/local/bin/tesseract $this->temp_file $output_file -l $language -psm 1 hocr &> /var/log/phpfunctions/cmd1.log";
        exec($command, $hocr_output, $return);

        if (file_exists($output_file . '.html')) {
          $log_message = "HOCR derivative created by tesseract v3.02.02 using command - $command || SUCCESS";
          $ingest = $this->add_derivative('HOCR', 'HOCR', $output_file . '.html', 'text/html', $log_message, FALSE);
        }
        else {
          $this->log->lwrite("Initial call to create ocr failed, converting to png for another attempt", 'FAIL_DATASTREAM', $this->pid, 'HOCR', NULL, 'ERROR');
          $convert_command = "/usr/bin/convert -monochrome " . $this->temp_file . " " . $this->temp_file . "_JPG2.png &> /var/log/phpfunctions/cmd2.log";
          exec($convert_command);
          $command = "/usr/local/bin/tesseract " . $this->temp_file . "_JPG2.png " . $output_file . " -l $language -psm 1 hocr &> /var/log/phpfunctions/cmd3.log";
          exec($command, $hocr_output, $return);

          if (file_exists($output_file . '.html')) {
            $log_message = "HOCR derivative created by using ImageMagick to convert to jpg using command - $convert_command - and tesseract v3.02.02 using command - $command || SUCCESS ~~ OCR of original TIFF failed and so the image was converted to a PNG and reprocessed.";
            $ingest = $this->add_derivative('HOCR', 'HOCR', $output_file . '.html', 'text/html', $log_message, FALSE);
          }
          else {
            $this->log->lwrite("Could not find the file '$output_file.html' for the HOCR derivative!\nTesseract output: " . implode(', ', $hocr_output) . "\nReturn value: $return", 'FAIL_DATASTREAM', $this->pid, 'HOCR', NULL, 'ERROR');
          }
        }

        $this->createEncodedOcrStream($output_file . '.html');
      }
      else {
        $this->log->lwrite("Could not find the input file '$this->temp_file' for the HOCR derivative!", 'FAIL_DATASTREAM', $this->pid, 'HOCR', NULL, 'ERROR');
      }

    } catch (Exception $e) {
      $this->log->lwrite("Could not create the HOCR or one of its derivatives", 'FAIL_DATASTREAM', $this->pid, 'HOCR', NULL, 'ERROR');

      if ($ingest) {
        unlink($output_file . '.html');
      }

    }

    unlink($output_file . '.html');
    return $return;
  }

  /**
   * creates the ENCODED_OCR stream and the OCR stream
   * @param string $file 
   */
  function createEncodedOcrStream($file) {
    $this->log->lwrite('Starting processing ' . $file, 'PROCESS_DATASTREAM', $this->pid, 'ENCODED_OCR');
    $hocr_xml = new DOMDocument();
    $hocr_xml->load($file);
    $raw_ocr = strip_tags($hocr_xml->saveHTML());
    $log_message = "OCR derivative created from ENCODED_OCR and transformed using hocr_to_lower.xslt || SUCCESS";
    $this->add_derivative('OCR', 'Scanned text', $raw_ocr, 'text/plain', $log_message, TRUE, FALSE);
    $xsl = new DOMDocument();
    $xsl->load('/var/www/html/hocr_to_lower.xslt');
    $proc = new XSLTProcessor();
    $proc->importStylesheet($xsl);
    $encoded_xml = $proc->transformToXml($hocr_xml);
    $encoded_xml = str_replace('<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">', '<?xml version="1.0" encoding="UTF-8"?>', $encoded_xml);
    /* $encoded_datastream = new NewFedoraDatastream('ENCODED_OCR', 'M', $this->object, $this->fedora_object->repository);
      $encoded_datastream->setContentFromString($encoded_xml);
      $encoded_datastream->label = $label;
      $encoded_datastream->mimetype = 'text/xml';
      $encoded_datastream->state = 'A';
      $encoded_datastream->checksum = TRUE;
      $encoded_datastream->checksumType = 'MD5';
      $encoded_datastream->logMessage = "ENCODED_OCR derivative created by tesseract v3.02.02 and transformed using hocr_to_lower.xslt || SUCCESS";
      $this->object->ingestDatastream($encoded_datastream); */
    $log_message = "ENCODED_OCR derivative created by tesseract v3.02.02 and transformed using hocr_to_lower.xslt || SUCCESS";
    $this->add_derivative('ENCODED_OCR', 'ENCODED_OCR', $encoded_xml, 'text/xml', $log_message, FALSE, FALSE);
  }

  function ocr($dsid = 'OCR', $label = 'Scanned text', $language = 'eng') {
    $this->log->lwrite('Starting processing', 'PROCESS_DATASTREAM', $this->pid, $dsid);
    try {
      if (file_exists($this->temp_file)) {
        $output_file = $this->temp_file . '_OCR';
        $command = "/usr/local/bin/tesseract $this->temp_file $output_file -l $language -psm 1 &> /var/log/phpfunctions/cmd1.log";
        exec($command, $ocr_output, $return);
        if (file_exists($output_file . '.txt')) {
          $log_message = "$dsid derivative created by tesseract v3.0.1 using command - $command || SUCCESS";
          $ingest = $this->add_derivative($dsid, $label, $output_file . '.txt', 'text/plain', $log_message);
        }
        else {
          $convert_command = "/usr/bin/convert -monochrome " . $this->temp_file . " " . $this->temp_file . "_JPG2.jpg &> /var/log/phpfunctions/cmd2.log";
          exec($convert_command, $convert_output, $return);
          $command = "/usr/local/bin/tesseract " . $this->temp_file . "_JPG2.jpg " . $output_file . " -l $language -psm 1 &> /var/log/phpfunctions/cmd3.log";
          exec($command, $ocr2_output, $return);
          if (file_exists($output_file . '.txt')) {
            $log_message = "$dsid derivative created by using ImageMagick to convert to jpg using command - $convert_command - and tesseract v3.0.1 using command - $command || SUCCESS ~~ OCR of original TIFF failed and so the image was converted to a JPG and reprocessed.";
            $ingest = $this->add_derivative($dsid, $label, $output_file . '.txt', 'text/plain', $log_message);
          }
          else {
            $this->log->lwrite("Could not find the file '$output_file.txt' for the $dsid derivative!\nTesseract output: " . implode(', ', $ocr_output) . " - Return value: $return", 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
          }
        }
      }
      else {
        $this->log->lwrite("Could not find the input file '$this->temp_file' for the $dsid derivative!", 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
      }
    } catch (Exception $e) {
      $this->log->lwrite("Could not create the $dsid derivative!", 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
      if ($ingest) {
        unlink($output_file . '.txt');
      }
    }
    return $return;
  }

  function hOcr($dsid = 'HOCR', $label = 'HOCR', $language = 'eng') {
    $this->log->lwrite('Starting processing', 'PROCESS_DATASTREAM', $this->pid, $dsid);
    try {
      if (file_exists($this->temp_file)) {
        $output_file = $this->temp_file . '_HOCR';
        $command = "/usr/local/bin/tesseract $this->temp_file $output_file -l $language -psm 1 hocr &> /var/log/phpfunctions/cmd1.log";
        exec($command, $hocr_output, $return);
        if (file_exists($output_file . '.html')) {
          $log_message = "$dsid derivative created by tesseract v3.0.1 using command - $command || SUCCESS";
          $ingest = $this->add_derivative($dsid, $label, $output_file . '.html', 'text/html', $log_message);
        }
        else {
          $this->log->lwrite("Initial call to create ocr failed, converting to png for another attempt", 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
          $convert_command = "/usr/bin/convert -monochrome " . $this->temp_file . " " . $this->temp_file . "_JPG2.png &> /var/log/phpfunctions/cmd2.log";
          exec($convert_command);
          $command = "/usr/local/bin/tesseract " . $this->temp_file . "_JPG2.png " . $output_file . " -l $language -psm 1 hocr &> /var/log/phpfunctions/cmd3.log";
          exec($command, $hocr2_output, $return);
          if (file_exists($output_file . '.html')) {
            $log_message = "$dsid derivative created by using ImageMagick to convert to jpg using command - $convert_command - and tesseract v3.0.1 using command - $command || SUCCESS ~~ OCR of original TIFF failed and so the image was converted to a PNG and reprocessed.";
            $ingest = $this->add_derivative($dsid, $label, $output_file . '.html', 'text/plain', $log_message);
          }
          else {
            $this->log->lwrite("Could not find the file '$output_file.html' for the $dsid derivative!\nTesseract output: " . implode(', ', $ocr_output) . "\nReturn value: $return", 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
          }
        }
      }
      else {
        $this->log->lwrite("Could not find the input file '$this->temp_file' for the $dsid derivative!", 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
      }
    } catch (Exception $e) {
      $this->log->lwrite("Could not create the $dsid derivative!", 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
      if ($ingest) {
        unlink($output_file . '.html');
      }
    }
    return $return;
  }

  function encodedOcr($dsid = 'ENCODED_OCR', $label = 'Encoded OCR', $language = 'eng') {
    $this->log->lwrite('Starting processing', 'PROCESS_DATASTREAM', $this->pid, $dsid);
    try {
      $output_file = $this->temp_file . '_HOCR';
      $command = "/usr/local/bin/tesseract $this->temp_file $output_file -l $language -psm 1 hocr &> /var/log/phpfunctions/cmd1.log";
      exec($command, $hocr_output, $return);
      if (!file_exists($output_file . '.html')) {
        exec("/usr/bin/convert -quality 99 " . $this->temp_file . " " . $this->temp_file . "_JPG2.jpg &> /var/log/phpfunctions/cmd2.log");
        $command = "/usr/local/bin/tesseract " . $this->temp_file . "_JPG2.jpg " . $output_file . " -l $language -psm 1 hocr &> /var/log/phpfunctions/cmd3.log";
        exec($command, $hocr2_output, $return);
        if (!file_exists($output_file . '.html')) {
          $this->log->lwrite("Could not create the $dsid derivative!", 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
          return $return;
        }
        else {
          $this->log->lwrite("Created the $dsid derivative!", 'PASS_DATASTREAM', $this->pid, $dsid, NULL, 'INFO');
        }
      }
      else {
        $this->log->lwrite("Created the $dsid derivative!", 'PASS_DATASTREAM', $this->pid, $dsid, NULL, 'INFO');
      }
      $hocr_datastream = new NewFedoraDatastream("HOCR", 'M', $this->fedora_object, $this->fedora_object->repository);
      $hocr_datastream->setContentFromFile($output_file . '.html');
      $hocr_datastream->label = 'HOCR';
      $hocr_datastream->mimetype = 'text/html';
      $hocr_datastream->state = 'A';
      $hocr_datastream->checksum = TRUE;
      $hocr_datastream->checksumType = 'MD5';
      $hocr_datastream->logMessage = "HOCR derivative created by tesseract v3.0.1 using command - $command || SUCCESS";
      $this->object->ingestDatastream($hocr_datastream);
      $hocr_xml = new DOMDocument();
      $hocr_xml->load($output_file . '.html');
      $xsl = new DOMDocument();
      $xsl->load('/var/www/html/hocr_to_lower.xslt');
      $proc = new XSLTProcessor();
      $proc->importStylesheet($xsl);
      $encoded_xml = $proc->transformToXml($hocr_xml);
      $encoded_xml = str_replace('<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">', '<?xml version="1.0" encoding="UTF-8"?>', $encoded_xml);
      $encoded_datastream = new NewFedoraDatastream($dsid, 'M', $this->object, $this->fedora_object->repository);
      $encoded_datastream->setContentFromString($encoded_xml);
      $encoded_datastream->label = $label;
      $encoded_datastream->mimetype = 'text/xml';
      $encoded_datastream->state = 'A';
      $encoded_datastream->checksum = TRUE;
      $encoded_datastream->checksumType = 'MD5';
      $encoded_datastream->logMessage = "$dsid derivative created by tesseract v3.0.1 and transformed using hocr_to_lower.xslt || SUCCESS";
      $this->fedora_object->ingestDatastream($encoded_datastream);
      unlink($output_file . '.html');
      $this->log->lwrite('Finished processing', 'COMPLETE_DATASTREAM', $this->pid, $dsid);
    } catch (Exception $e) {
      $this->log->lwrite("Could not create the $dsid derivative!", 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
      unlink($output_file . '.html');
    }
    return $return;
  }

}

?>
