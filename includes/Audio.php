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
    $return = MS_SUCCESS;
    $mp3_output = array();
    $out_file = $this->temp_file . "-audio.mp3";
    $file = $this->temp_file;
    $command = "lame -V5 --vbr-new \"$file\" \"$out_file\"";
    $ret = FALSE;
    exec($command, $mp3_output, $ret);
    if ($ret === 0) {
      $log_message = "$outputdsid derivative created using lame - $command || SUCCESS";
      try {
        $this->add_derivative($outputdsid, $label, $out_file, 'audio/mp3', $log_message);
        $this->log->lwrite("Updated $outputdsid datastream", 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'SUCCESS');
      }
      catch (Exception $e) {
        $return = MS_FEDORA_EXCEPTION;
        $this->log->lwrite("Failed to add audio derivative" . $e->getMessage(), 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'ERROR');
      }
    } else {
      $return = MS_SYSTEM_EXCEPTION;
      $this->log->lwrite("Failed to add audio derivative" . implode(', ', $mp3_output), 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'ERROR');
    }
    return $return;
  }
}