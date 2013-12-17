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
}

