<?php
/**
 * Created by IntelliJ IDEA.
 * User: ppound
 * Date: 2014-10-07
 * Time: 11:03 AM
 */

require_once 'Derivatives.php';

class Audio extends Derivative {

  function __destruct() {
    parent::__destruct();
  }

  /**
   * Create a audio derivative.
   *
   * @param string $outputdsid
   *   The output dsid
   * @param string $label
   *   the datastream label
   * @param array $params
   *
   * @return int|string
   */
  function createMP3Derivative($outputdsid, $label, $params) {
    $return = MS_SYSTEM_EXCEPTION;
    $mp3_output = array();
    $out_file = $this->temp_file . "-audio.mp3";
    $file = $this->temp_file;
    $command = "lame -V5 --vbr-new $file $out_file 2>&1";
    $ret = FALSE;
    exec($command, $mp3_output, $ret);
    if ($ret === 0) {
      $log_message = "$outputdsid derivative created using lame - $command || SUCCESS";
      $return = $this->add_derivative($outputdsid, $label, $out_file, 'audio/mpeg', $log_message);
    }
    if ($return == MS_SUCCESS) {
      $this->log->lwrite("Updated $outputdsid datastream", 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'SUCCESS');
    }
    else {
      $this->log->lwrite("Failed to create audio derivative using file $file " . implode(', ', $mp3_output), 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'ERROR');
    }
    return $return;
  }
}