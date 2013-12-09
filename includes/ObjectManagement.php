<?php
require_once 'Derivatives.php';

class ObjectManagement extends Derivative {

  function __destruct() {
    parent::__destruct();
  }

  /**
   * Deletes the given datastream.
   * @param string $pid
   *   Fedora object PID
   * @param string $dsid
   *   DSID of the datastream to be deleted
   * @param array $params
   * @return int|string
   */
  function deleteDatastream($pid, $dsid, $params) {
    $object = $this->fedora_object;
    $datastream = $object[$dsid];

    try {
      $deleted = $object->purgeDatastream($datastream->id);
      $return = MS_SUCCESS;
      $this->log->lwrite("Successfully deleted datastream.", 'DELETE_DATASTREAM', $pid, $dsid, 'SUCCESS');
      $log_message = "$dsid datastream successfully deleted using ObjectManagement->deleteDatastream() || SUCCESS";
      $this->fedora_object->repository->api->m->modifyDatastream($this->fedora_object->id, $dsid, $log_message);
    } catch (Exception $e) {
      $return = MS_FEDORA_EXCEPTION;
      $this->log->lwrite("Error deleting datastream.", 'DELETE_DATASTREAM', $pid, $dsid, 'ERROR');
      $log_message = "$dsid datastream failed to delete using ObjectManagement->deleteDatastream() || ERROR";
      $this->fedora_object->repository->api->m->modifyDatastream($this->fedora_object->id, $dsid, $log_message);
    }

    return $return;
  }
}

