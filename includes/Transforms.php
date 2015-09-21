<?php

require_once 'Derivatives.php';

class Transforms extends Derivative {

  function __destruct() {
    parent::__destruct();
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
    } catch (Exception $e) {
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
    $this->log->lwrite('Starting processing', 'PROCESS_DATASTREAM', $this->pid, $dsid);
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
    $log_message = "$dsid derivative created using xml transform from $this->incoming_dsid || SUCCESS";
    $return = $this->add_derivative($outputdsid, $label, $output_xml, 'application/xml', $log_message, FALSE, FALSE);
    if ($return == MS_SUCCESS) {
      $this->log->lwrite("Successfully added the $dsid derivative!", $return, 'SUCCESS', $this->pid, $dsid, NULL, 'INFO');
    }
    else {
      $this->log->lwrite("Successfully added the $dsid derivative!", $return, 'SUCCESS', $this->pid, $dsid, NULL, 'INFO');
    }
    return $return;
  }

}
