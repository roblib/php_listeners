<?php
/**
 * Created by IntelliJ IDEA.
 * User: ppound
 * Date: 2014-10-06
 * Time: 2:48 PM
 */

require_once 'Derivatives.php';

class Video extends Derivative {

  function __destruct() {
    parent::__destruct();
  }

  /**
   * Create a video derivative.
   *
   * @param string $outputdsid
   *   The output dsid
   * @param string $label
   *   the datastream label
   * @param array $params
   *   an array containing parameters currently type is required an must = mp4
   *   or mkv.  ie $params['type'] = 'mp4' but defined in the workflow
   *
   * @return int|string
   */
  function createVideoDerivative($outputdsid, $label, $params) {
    $return = MS_SUCCESS;
    $mp4_output = array();
    $type = $params['type'];
    if (empty($type)) {
      $this->log->lwrite("Failed to create video derivative no type provided", 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'ERROR');
      return MS_FEDORA_EXCEPTION;
    }
    $out_file = $this->temp_file . "-video.$type";
    $command = "ffmpeg -i $this->temp_file $out_file 2>&1";
    if ($type = 'mp4') {
      $command = "ffmpeg -i $this->temp_file -f mp4 -vcodec libx264 -preset medium -acodec libfaac -ab 128k -ac 2 -async 1 -movflags faststart $out_file 2>&1";
    }
    exec($command, $mp4_output, $return);
    if (file_exists($out_file)) {
      $log_message = "$outputdsid derivative created using ffmpg - $command || SUCCESS";
      $return = $this->add_derivative($outputdsid, $label, $out_file, 'video/mp4', $log_message);
    }
    if ($return == MS_SUCCESS) {
      $this->log->lwrite("Updated $outputdsid datastream", 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'SUCCESS');
    }
    else {
      $this->log->lwrite("Failed to create video derivative" . implode(',', $mp4_output) . 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'ERROR');
    }
    return $return;
  }

  /**
   * Create a thumbnail from a video.
   *
   * @param string $outputdsid
   *   The output dsid
   * @param string $label
   *   the datastream label
   * @param array $params
   *   an array containing optional parameters
   *
   * @return int|string
   *   0 = success
   */
  function createThumbnailFromVideo($outputdsid, $label, $params) {
    include_once('includes/Image.php');
    $return = MS_SUCCESS;
    $out_file = $this->temp_file . '-TN.jpg';
    $vid_length_command = "ffmpeg -i $this->temp_file 2>&1";
    exec($vid_length_command, $time_output, $ret_value);
    $dur_match = FALSE;
    $duration = '';
    foreach ($time_output as $key => $value) {
      preg_match('/Duration: (.*), start/', $value, $time_match);
      if (count($time_match)) {
        $dur_match = TRUE;
        $duration = $time_match[1];
        break;
      }
    }
    if ($dur_match) {
      // Snip off the ms because we don't care about them.
      $time_val = preg_replace('/\.(.*)/', '', $duration);
      $time_array = explode(':', $time_val);
      $output_time = floor((($time_array[0] * 360) + ($time_array[1] * 60) + $time_array[2]) / 2);

      $tn_creation_command = "ffmpeg -itsoffset -2 -ss $output_time -i $this->temp_file -vcodec mjpeg -vframes 1 -an -f rawvideo $out_file";

      $return_value = FALSE;
      exec($tn_creation_command, $output, $return_value);
      if ($return_value === 0) {
        $log_message = "$outputdsid derivative created using ffmpg - $tn_creation_command || SUCCESS";
        $return = $this->add_derivative($outputdsid, $label, $out_file, 'image/jpeg', $log_message);
      }
      if ($return == MS_SUCCESS) {
        $this->log->lwrite("Updated $outputdsid datastream", 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'SUCCESS');
      }
      // Unable to generate with ffmpeg, add default TN.
      else {
        $return = $this->addDefaultThumbnail($outputdsid, $label, array('type' => 'video'));
      }
    }
    // Unable to grab duration at the default thunbnail.
    else {
      $return = $this->addDefaultThumbnail($outputdsid, $label, array('type' => 'video'));
    }
    return $return;
  }
}




