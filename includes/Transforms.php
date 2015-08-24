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
    }
    catch (Exception $e) {
      $return = MS_FEDORA_EXCEPTION;
      $this->log->lwrite("Failed to update EML taxonomy" . $e->getMessage(), 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'ERROR');
    }
    return $return;
  }
}




