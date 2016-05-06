<?php

require_once 'Derivatives.php';

class Transforms extends Derivative {

  function __destruct() {
    parent::__destruct();
  }

  /**
   * Update the objects TECHMD based on values in the MORPHO_TECHMD datastream.
   *
   * @param string $outputdsid
   *   The output dsid
   * @param string $label
   *   the datastream label
   * @param array $params
   *
   *
   * @return int|string
   */
  function updateTechmdMorphospace($outputdsid, $label, $params) {
    //require_once 'includes/transform_utilities.php';
    $morpho_content = empty($this->fedora_object['MORPHO_TECHMD']->content) ? NULL : $this->fedora_object['MORPHO_TECHMD']->content;
    $techmd_content = empty($this->fedora_object[$outputdsid]->content) ? NULL : $this->fedora_object[$outputdsid]->content;

    if (empty($morpho_content)) {
      $this->log->lwrite("DID NOT update $outputdsid no MORPHO_TECHMD found", 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'INFO');
      return MS_OBJECT_NOT_FOUND;
    }
    if (empty($techmd_content)) {
      $this->log->lwrite("DID NOT update $outputdsid no TECHMD found", 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'INFO');
      return MS_OBJECT_NOT_FOUND;
    }

    $morpho_key_entries = $this->transform_service_get_morpho_data($morpho_content);
    if (empty($morpho_key_entries)) {
      $this->log->lwrite("DID NOT update TECHMD no fields found in MORPHO_TECHMD found", 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'INFO');
      return MS_OBJECT_NOT_FOUND;
    }

    $techmd_doc = @DOMDocument::loadXML($techmd_content);
    if (empty($techmd_doc)) {
      $this->log->lwrite("DID NOT update TECHMD Could not parse TECHMD as XML", 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'ERROR');
      return MS_SYSTEM_EXCEPTION;
    }
    $wd_results = $techmd_doc->getElementsByTagName('WorkingDistance');
    $working_distance = '';
    $magnification = '';
    if ($wd_results->length > 0) {
      $working_distance = $wd_results->item(0)->textContent;
    }
    $mag_results = $techmd_doc->getElementsByTagName('Magnification');
    if ($mag_results->length > 0) {
      $magnification = $mag_results->item(0)->textContent;
    }
    if ($morpho_key_entries['Magnification'] === $magnification && $morpho_key_entries['WorkingDistance'] === $working_distance) {
      $this->log->lwrite("DID NOT update TECHMD, it is already up to date", 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'SUCCESS');
      return MS_SUCCESS;
    }
    if (!empty($working_distance)) {
      $wd_results->item(0)->nodeValue = $morpho_key_entries['WorkingDistance'];
    }
    else {
      $image = $techmd_doc->getElementsByTagName('image');
      $wd = $techmd_doc->createElement("WorkingDistance", $morpho_key_entries['WorkingDistance']);
      $wd->setAttribute('toolname', 'IslandoraMicroservices');
      $image->item(0)->appendChild($wd);
    }
    if (!empty($magnification)) {
      $mag_results->item(0)->nodeValue = $morpho_key_entries['Magnification'];
    }
    else {
      // create new element
      $image = $techmd_doc->getElementsByTagName('image');
      $mag = $techmd_doc->createElement("Magnification", $morpho_key_entries['Magnification']);
      $mag->setAttribute('toolname', 'IslandoraMicroservices');
      $image->item(0)->appendChild($mag);
    }
    $log_message = 'Updated TECHMD with values from MORPHO_TECHMD';
    $return = $this->add_derivative($outputdsid, $label, $techmd_doc->saveXML(), 'text/xml', $log_message, FALSE, FALSE);
    $this->log->lwrite("Updated $outputdsid datastream", 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'SUCCESS');

    return $return;
  }

  /**
   * Get key content from the MORPHO_TECHMD datastream.
   *
   * @param $content
   * @return array
   */
  function transform_service_get_morpho_data($content) {
    $return_arr = array();
    $wanted_values = array('WorkingDistance', 'Magnification');
    $lines = explode("\n", $content);
    foreach ($lines as $line) {
      $arr = explode("=", $line);
      if (count(array_intersect($wanted_values, $arr)) > 0) {
        $return_arr[$arr[0]] = trim($arr[1]);
      }
    }
    return $return_arr;
  }

  /**
   * Update the objects EML datastream with classifiction info from itis.
   *
   * @param string $outputdsid
   *   The output dsid
   * @param string $label
   *   the datastream label
   * @param array $params
   *   an array containing the itisString
   *
   * @return int|string
   */
  function updateEMLTaxonomy($outputdsid, $label, $params) {
    require_once 'includes/transform_utilities.php';
    if (empty($params['itisString'])) {
      $this->log->lwrite("Failed to update EML taxonomy no itis string found", 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'ERROR');
      return MS_OBJECT_NOT_FOUND;
    }
    $return = MS_SUCCESS;
    //todo get the return value from the add_derivative function and remove the try.
    try {
      transform_service_eml_with_itis($this->temp_file, $params['itisString']);
      $log_message = "EML updated using ITIS getFullHieracrchyFromTSN web service";
      $this->add_derivative($outputdsid, $label, $this->temp_file, 'text/xml', $log_message);
      $this->log->lwrite("Updated EML datastream", 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'SUCCESS');
    }
    catch (Exception $e) {
      $return = MS_FEDORA_EXCEPTION;
      $this->log->lwrite("Failed to update EML taxonomy" . $e->getMessage(), 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'ERROR');
    }
    return $return;
  }

  /**
   * Crosswalk xml from one schema to another.
   *
   * @param string $outputdsid
   *   The datastream to write to
   * @param string $label
   *   The updated datastreams label
   * @param array $params
   *   Array with a key of xslt_string whose value is an xsl to use in the transform.
   *
   * @return int
   *   0 on success, + int for recoverable error, - int for non recoverable
   */
  function transformXmlToXml($outputdsid, $label, $params) {
    $this->log->lwrite('Starting processing', 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid);
    $return = MS_SYSTEM_EXCEPTION;
    $xsl = new DOMDocument;
    extract($params, EXTR_SKIP);
    if (!isset($xslt_string)) {
      $this->log->lwrite("Failed to transform XML, missing xslt_string variable.", 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'ERROR');
      return $return;
    }
    $xsl->loadXML($xslt_string);

    $xml = new DOMDocument;
    $xml->load($this->temp_file);

    $proc = new XSLTProcessor;
    $proc->importStyleSheet($xsl);
    $output_xml = $proc->transformToXML($xml);
    if (!isset($output_xml)) {
      $this->log->lwrite("Failed to transform XML, Transform failed.", 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'ERROR');
      return $return;
    }
    $log_message = "$this->incoming_dsid derivative created using xml transform from $this->incoming_dsid || SUCCESS";
    $return = $this->add_derivative($outputdsid, $label, $output_xml, 'application/xml', $log_message, FALSE, FALSE);
    if ($return == MS_SUCCESS) {
      $this->log->lwrite("Successfully added the $this->incoming_dsid derivative!", $return, 'SUCCESS', $this->pid, $this->incoming_dsid, NULL, 'INFO');
    }
    else {
      $this->log->lwrite("Successfully added the $this->incoming_dsid derivative!", $return, 'SUCCESS', $this->pid, $this->incoming_dsid, NULL, 'INFO');
    }
    return $return;
  }

}
