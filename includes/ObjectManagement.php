<?php
require_once 'Derivatives.php';

class ObjectManagement extends Derivative {

  function __destruct() {
    parent::__destruct();
  }

  /**
   * Deletes the given datastream.
   *
   * @param string $pid
   *   Fedora object PID
   * @param string $dsid
   *   DSID of the datastream to be deleted
   * @param array $params
   *   array with logMessage element for audit trail   *
   *
   * @return int
   *   returns an int which will get passed back to Taverna 0 = success
   */
  function deleteDatastream($pid, $dsid, $params) {
    $return = MS_SUCCESS;
    try {
      $this->fedora_object->repository->api->m->purgeDatastream($this->fedora_object->id, $dsid, $params);
      $this->log->lwrite("Successfully deleted datastream.", 'DELETE_DATASTREAM', $pid, $dsid, 'SUCCESS');
    }
    catch (Exception $e) {
      $return = MS_FEDORA_EXCEPTION;
      $this->log->lwrite("Error deleting datastream. " . $e->getMessage(), 'DELETE_DATASTREAM', $pid, $dsid, 'ERROR');
    }
    return $return;
  }

  /**
   * TODO document and finish implementationj
   * @param $dsid
   * @param $label
   * @param $params
   * @return int
   */
  function writeDatastream($dsid, $label, $params) {
    $return = MS_SUCCESS;
    $this->log->lwrite('Starting writeDatastream function', 'PROCESS_DATASTREAM', $this->pid, $dsid);
    extract($params, EXTR_SKIP);
    $log_message = "writing datastream using write service || SUCCESS";
    $return = $this->add_derivative($dsid, $label, base64_decode($content), $mimetype, $log_message, TRUE, FALSE);
    if ($return == MS_SUCCESS) {
      $this->log->lwrite("Successfully added the $dsid derivative!", $return, 'SUCCESS', $this->pid, $dsid, NULL, 'INFO');
    }
    else {
      $this->log->lwrite("Could not find the file write the datastrea $dsid", 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
      $return = MS_SYSTEM_EXCEPTION;
    }
    return $return;
  }

  /**
   * TODO document and finish implementation
   * @param $dsid
   * @param $label
   * @param $params
   * @return int|string
   */
  function readDatastream($dsid, $label, $params){

    extract($params, EXTR_SKIP);
    $this->log->lwrite('Starting readDatastream function', 'PROCESS_DATASTREAM', $this->pid, $dsid);
    $object = $this->fedora_object;
    try {
      $datastream = $object->getDatastream($dsid);
      $return = base64_encode($datastream->content);
    } catch (Exception $e){
      $this->log->lwrite("Could not find read the $dsid datastream ", 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
      $return = MS_SYSTEM_EXCEPTION;
    }
    return $return;
  }
}

