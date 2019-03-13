<?php
require_once 'Derivatives.php';

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
    $this->log->lwrite('Starting processing', 'PROCESS_DATASTREAM', $this->pid, 'HOCR');
    //if the workflow defines a language always use that
    if(isset($params['language']) && $params['language'] != 'none') {
      $language = $params['language'];
    } else {
      $language = $this->getOcrLanguage();
      $this->log->lwrite("Using MODS Language of $language", 'PROCESS_DATASTREAM', $this->pid, $this->dsid);
      $language = $this->convertLanguageToTesseractParam($language);
    }
    $this->log->lwrite("Using Tesseract Language of $language", 'PROCESS_DATASTREAM', $this->pid, $this->dsid);

    $return = MS_SYSTEM_EXCEPTION;
    $hocr_output = array();
    if (file_exists($this->temp_file)) {
      $output_file = $this->temp_file . '_HOCR';
      $command = "tesseract $this->temp_file $output_file --oem 1 -l $language hocr 2>&1";
      $this->log->lwrite("command = $command", 'PROCESS_DATASTREAM', $this->pid, 'HOCR');
      exec($command, $hocr_output, $return);
      $this->log->lwrite(implode(', ', $hocr_output) . "\nReturn value: $return", 'PROCESS_DATASTREAM', $this->pid, 'HOCR', NULL, 'INFO');
      if (file_exists($output_file . '.hocr')) {
        // TODO correct hardcoded version text.
        $log_message = "HOCR derivative created by tesseract using command - $command || SUCCESS";
        $return = $this->add_derivative($dsid, $label, $output_file . '.hocr', 'text/html', $log_message);
      }
      else {
        $this->log->lwrite("Initial call to create ocr failed, converting to png for another attempt", 'FAIL_DATASTREAM', $this->pid, 'HOCR', NULL, 'ERROR');
        $convert_command = "convert -monochrome " . $this->temp_file . " " . $this->temp_file . "_JPG2.png 2>&1";
        $hocr_output = array();
        exec($convert_command, $hocr_output, $return);
        $this->log->lwrite("attempted conversion to png: " . implode(', ', $hocr_output) . "\nReturn value: $return", 'PROCESS_DATASTREAM', $this->pid, 'HOCR', NULL, 'INFO');
        $command = "tesseract " . $this->temp_file . "_JPG2.png " . $output_file . " --oem 1 -l $language hocr 2>&1";
        exec($command, $hocr_output, $return);
        $this->log->lwrite("attempting HOCR on png derivative: " . implode(', ', $hocr_output) . "\nReturn value: $return", 'PROCESS_DATASTREAM', $this->pid, 'HOCR', NULL, 'INFO');
        if (file_exists($output_file . '.hocr')) {
          $log_message = "HOCR derivative created by using ImageMagick to convert to jpg using command - $convert_command - and tesseract using command - $command || SUCCESS ~~ OCR of original TIFF failed so the image was converted to a PNG and reprocessed.";
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
   * Convert the language value to something Tesseract can understand.
   *
   * @param $language
   *
   * current installed languages in Tesseract
   * enm,fra, ara, nld, frm, eng, ita_old, osd, jpn, grc, equ, hin, spa, spa_old, ita
   *
   *  @return string
   * A normalized string that tessearact can use
   */
  public function convertLanguageToTesseractParam($language) {
    if(empty($language)) {
      return 'eng';
    }
    $lang = strtolower($language);
    $languageKey = NULL;
    $langArr = array(
      'eng' => array('english', 'en', 'eng')
    , 'fra' => array('french', 'fr', 'fre', 'fra'),
      'jpn' => array('japanese', 'jp', 'jpn'),
      'ita' => array('italian', 'it', 'ite', 'ita'),
      'spa' => array('spanish', 'sp', 'spa'),
      );

    foreach($langArr as $key => $arr) {
      if(in_array($lang, array_values($arr))) {
        $languageKey = $key;
        break;
      }
    }
    return isset($languageKey) ? $languageKey : 'eng';
  }


  /**
   * Get the language to use for OCR.
   *
   * Internal helper function not exposed to SOAP WSDL
   *
   * If language exists in the MODS of this Object or it's parent Object
   * return the language string.
   *
   * @return null|string
   *   null if no language exists otherwise the language string
   */
  function getOcrLanguage() {
    $defaultLang = 'eng';
    $item = $this->fedora_object;
    if(empty($item)) {
      $this->log->lwrite('Could not load the Fedora object to get the language from MODS', 'PROCESS_DATASTREAM', $this->pid, $this->dsid);
      return $defaultLang;
    }
    $lang = $this->parseModsForLanguage($item);

    if(!empty($lang)) {
      return $lang;
    }
    //check the parent object MODS for language
    if (empty($item['RELS-EXT'])) {
      //no datastream to reference in RELS
      $this->log->lwrite('Could not read the RELS-EXT datastream to get parent using ' . $defaultLang .' for language', 'PROCESS_DATASTREAM', $this->pid, $this->dsid);
      return $defaultLang;
    }

    $rels_ds = trim($item['RELS-EXT']->content);
    $doc = new DOMDocument();
    $doc->loadXML($rels_ds);

    if(empty($doc)) {
      $this->log->lwrite('Could not load the RELS-EXT datastream content to get parent using language of ' . $defaultLang, 'PROCESS_DATASTREAM', $this->pid, $this->dsid);
      return $defaultLang;
    }
    $memberOf = $doc->getElementsByTagName('isMemberOf');
    if(empty($memberOf)) {
      $this->log->lwrite('Could not load the isMemberOf nodeList from RELS-EXT to get parent. Using language of ' . $defaultLang, 'PROCESS_DATASTREAM', $this->pid, $this->dsid);
      return $defaultLang;
    }
    // we will get the parent mods of the first memberOf, usually only one anyway.
    $memberOf = $memberOf->item(0);
    if(empty($memberOf)){
      $this->log->lwrite('Could not load the isMemberOf item from RELS-EXT to get parent. Using language of ' . $defaultLang, 'PROCESS_DATASTREAM', $this->pid, $this->dsid);
      return $defaultLang;
    }
    $about = $memberOf->getAttribute('rdf:resource');
    $parentPid = substr($about, mb_strlen('info:fedora/'));
    $this->log->lwrite("value of iparentPID = $parentPid", 'PROCESS_DATASTREAM', $this->pid, $this->dsid);
    $parentObject = new FedoraObject($parentPid, $item->repository);
    if(empty($parentObject)) {
      $this->log->lwrite("Could not load Parent Object  $parentPid. Usign language of $defaultLang", 'PROCESS_DATASTREAM', $this->pid, $this->dsid);
      return $defaultLang;
    }
    $lang = $this->parseModsForLanguage($parentObject);
    if (!isset($lang)) {
      //either the MODS or the object do not exist
      $this->log->lwrite('Could not read the parent MODs datastream for object ' . $parentPid . ' Using language of ' . $defaultLang, 'PROCESS_DATASTREAM', $this->pid, $this->dsid);
      return $defaultLang;
    }
    return $lang;
  }

  /**
   * Get the language we want to use for OCR from MODS datastream.
   *
   * Internal helper function not exposed to SOAP WSDL
   *
   * @param $object
   *
   * @return mixed|null
   *   returns the language defined in the objects MODS datastream or NULL.
   */
  function parseModsForLanguage($object) {
    if (empty($object) || empty($object['MODS'])) {
      return NULL;
    }
    $mods = $object['MODS']->content;
    $modsDoc = new DOMDocument();
    $modsDoc->loadXML($mods);
    if(empty($modsDoc)) {
      $this->log->lwrite('Could not load the MODS datastream content to get HOCR Language ' , 'PROCESS_DATASTREAM', $this->pid, $this->dsid);
      return NULL;
    }
    $language = $modsDoc->getElementsByTagName('languageTerm');
    if(isset($language)) {
      $language = $language->item(0);
      $language = $language->nodeValue;
      return $language;
    }
    return NULL;
  }

  /**
   * creates the raw OCR stream by stripping tags from hocr
   * faster then calling tesseract again
   *
   * @param string $file
   * the temp files path
   */
  function ocrFromHocr($dsid = 'FULL_TEXT', $label = 'FULL_TEXT', $params = array('language' => 'eng')) {
    $this->log->lwrite('Starting processing ocrFromHocr', 'PROCESS_DATASTREAM', $this->pid, $dsid);
    if (file_exists($this->temp_file)) {
      $hocr_xml = new DOMDocument();
      $isLoaded = $hocr_xml->load($this->temp_file);
      if($isLoaded) {
        $raw_ocr = strip_tags($hocr_xml->saveHTML());
        $log_message = "OCR derivative created from hocr by stripping tags || SUCCESS";
        $return = $this->add_derivative($dsid, $label, $raw_ocr, 'text/plain', $log_message, TRUE, FALSE);
      } else {
        $this->log->lwrite("Could not parse the hocr xml file for the $dsid",
          'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
        $return = MS_FEDORA_EXCEPTION;   // Try again the datastream maybe available
      }
    } else {
      $this->log->lwrite("Could not find the hocr xml file for the $dsid",
        'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
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
      $command = "tesseract $this->temp_file $output_file --oem 1 -l $language -psm 1 $hocr 2>&1";
      $ocr_output = array();
      exec($command, $ocr_output, $return);
      if (file_exists($output_file . '.txt')) {
        $log_message = "$dsid derivative created by tesseract using command - $command || SUCCESS";
        $return = $this->add_derivative($dsid, $label, $output_file . $ext, $mime_type, $log_message);
      }
      else {
        $convert_command = "/usr/bin/convert -monochrome " . $this->temp_file . " " . $this->temp_file . "_JPG2.jpg 2>&1";
        exec($convert_command, $convert_output, $return);
        $command = "tesseract " . $this->temp_file . "_JPG2.jpg " . $output_file . " --oem 1 -l $language -psm 1 $hocr 2>&1";
        $ocr2_output = array();
        exec($command, $ocr2_output, $return);
        if (file_exists($output_file . '.txt')) {
          $log_message = "$dsid derivative created by using ImageMagick to convert to jpg using command - $convert_command - and tesseract using command - $command || SUCCESS ~~ OCR of original TIFF failed and so the image was converted to a JPG and reprocessed.";
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
