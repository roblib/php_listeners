<?php
/**
 * Class to create derivatives and ingets them into Fedora
 *
 * @author Richard Wincewicz
 */
define('MS_FEDORA_EXCEPTION', 1);  // Fedora errors are probably recoverable, ie if we try again it will likely work (ie there may have been more then one thread trying to update the same object causing a conflict)
define('MS_SUCCESS', 0);
define('MS_SYSTEM_EXCEPTION', -1); //errors returned from imagemagick or other system calls fatal errors (retrying will probably not succeed)
define('MS_OBJECT_NOT_FOUND', -2);
define('MS_SERVICE_NOT_FOUND', -3);

class Derivative {

  protected $log;
  protected $fedora_object;
  protected $pid;
  protected $created_datastream;
  protected $incoming_dsid;
  protected $extension;
  protected $temp_file;

  function __construct($fedora_object, $incoming_dsid, $extension = NULL, $log, $created_datastream) {
    include_once 'message.php';
    include_once 'FedoraConnect.php';

    $this->log = $log;
    $this->fedora_object = $fedora_object;
    $this->pid = $fedora_object->id;
    $this->created_datastream = $created_datastream;
    $this->incoming_dsid = $incoming_dsid;
    //$this->incoming_datastream = new FedoraDatastream($this->incoming_dsid, $this->fedora_object, $this->fedora_object->repository);
    //$this->mimetype = $this->incoming_datastream->mimetype;
    //$this->log->lwrite('Mimetype: ' . $this->mimetype, 'SERVER_INFO');
    $this->extension = $extension;
    if ($this->incoming_dsid != NULL) {
      $this->temp_file = $this->create_temp_file();
      $extension_array = explode('.', $this->temp_file);
      $extension = $extension_array[1];
    }
  }

  function __destruct() {
    unlink($this->temp_file);
    if (isset($this->fedora_object)) {
      unset($this->fedora_object);
    }
  }

  protected function create_temp_file() {
    $datastream_array = array();

    foreach ($this->fedora_object as $datastream) {
      $datastream_array[] = $datastream->id;
    }

    if (!in_array($this->incoming_dsid, $datastream_array)) {
      // Some services like writeDatastream may not work with an existing datastream
      $this->log->lwrite('Could not create temp file from datastream, DATASTREAM NOT FOUND', 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid);
      return ;
    }
    try {
      $datastream = $this->fedora_object->getDatastream($this->incoming_dsid);
      $mime_type = $datastream->mimetype;
      if (!$this->extension) {
        $this->extension = system_mime_type_extension($mime_type);
      }
      $tempfile = temp_filename($this->extension);
      $datastream->getContent($tempfile);
    }
    catch (Exception $e) {
      $this->log->lwrite('Could not create temp file from datastream ' . $e->getMessage(), 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid);
    }
    return $tempfile;
  }

  /**
   * add a thumbnail from a jpg image.
   *
   * @param string $outputdsid
   *   The output dsid
   * @param string $label
   *   the datastream label
   * @param array $params
   *   $params should include the type of thumbnail video or audio
   *
   * @return int|string
   *   0 = success
   */
  function addDefaultThumbnail($outputdsid, $label, $params) {
    $type = $params['type'];
    $out_file = realpath(dirname(__FILE__));
    if (empty($type)) {
      $this->log->lwrite("Failed to create thumbnail derivative no type provided", 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'ERROR');
      return MS_FEDORA_EXCEPTION;
    }
    switch ($type) {
      case "video":
        $out_file .= '/images/crystal_clear_app_camera.png';
        break;

      case "audio":
        $out_file .= '/images/audio-TN.jpg';
        break;

      default:
        $out_file .= '/images/folder.png';
    }
    $return = MS_SUCCESS;
    if (file_exists($out_file)) {
      try {
        $log_message = "created $outputdsid using default thumbnail || SUCCESS";
        $this->add_derivative($outputdsid, $label, $out_file, 'image/jpeg', $log_message, FALSE);
        $this->log->lwrite("Updated $outputdsid datastream using default thumbnail", 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'SUCCESS');
      }
      catch (Exception $e) {
        $return = MS_FEDORA_EXCEPTION;
        $this->log->lwrite("Failed to add default thumbnail for $outputdsid derivative" . $e->getMessage(), 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'ERROR');
      }
    }
    else {
      $this->log->lwrite("Could not add default thumbnail - file not found $out_file", 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'ERROR');
      $return = MS_SYSTEM_EXCEPTION;
    }
    return $return;
  }

  /**
   * Calls tuque to add or update the datastream to the object.
   *
   * Also logs success or failure and will update the audit trail if log_message
   * is set.
   *
   * @param string $dsid
   *   The dastream id
   * @param string $label
   *   The datastream label
   * @param string $content
   *   The content for the datastream, or a path to a file, or a url.
   * If the stream_type is E or R then it expects this parameter to be a URL.
   * If $from_file = true then it expects this string to be a path to a file
   * @param string $mimetype
   *   The mimetype for the datastream
   * @param type $log_message
   *   A message that will be added to the audit trail
   * @param boolean $delete
   *   Do we want to delete the temp file
   * @param boolean $from_file
   *   Is content a path to a file or actual data
   * @param type $stream_type
   *   The type of stream M=managed X=inline xml currently does not support
   * E or R
   * @return int
   *   0 = success, + int is a recoverable error, - int is unrecoverable error
   *
   */
  protected function add_derivative($dsid, $label, $content, $mimetype, $log_message = NULL, $delete = TRUE, $from_file = TRUE, $stream_type = "M") {
    $return = MS_SUCCESS;
    if (!isset($this->fedora_object)) {
      $this->log->lwrite("Could not create the $dsid derivative! The object does not exist", 'OBJECT_DELETED', $this->pid, $dsid, NULL, 'INFO');
      //we want to acknowledge that we have received and processed the message
      return $return;
    }
    $datastream = null;
    try {
    //TODO we don't seem to be sending custom log message for updates only ingests
    if (isset($this->fedora_object[$dsid])) {
      $this->log->lwrite("updating the datastream $dsid derivative! ", 'DATASTREAM_EXISTS', $this->pid, $dsid, NULL, 'INFO');
      $datastream = $this->fedora_object[$dsid];
    }
    else {
      $datastream = new NewFedoraDatastream($dsid, $stream_type, $this->fedora_object, $this->fedora_object->repository);

      // considering setting versionable to TRUE for RELS- datastreams
      // but we usually won't be creating rels via workflow
      // we could also ask for this info from the workflow? this would mean
      // workflow designers would have to know what to do
      // since dervitives are just derivatives they probably don't need to
      // be versioned.
      // We are also assumig that if a datastream exists we are just updating
      // the datastream comments.  We may want to revisit this in case we
      // actually want to change the mimetype or label?

      $datastream->state = 'A';
      $datastream->versionable = FALSE;
      if($stream_type != 'R' && $stream_type != 'E') {
        $datastream->checksum = TRUE;
        $datastream->checksumType = 'MD5';
      }
      $datastream->label = $label;
      $datastream->mimetype = $mimetype;

    }

    if($stream_type == 'R' || $stream_type == 'E'){
      $datastream->url = $content;
    }
    elseif ($from_file) {
      $datastream->setContentFromFile($content);
    }
    else {
      $datastream->setContentFromString($content);
    }

    if ($log_message) {
      $datastream->logMessage = $log_message;
    }

      $this->fedora_object->ingestDatastream($datastream);
    }
    catch (Exception $e) {
      $return = MS_FEDORA_EXCEPTION;
      $this->log->lwrite("Could not create the $dsid derivative! " . $e->getMessage() . ' ' . $e->getTraceAsString(), 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
    }

    if ($delete && $from_file) {
      unlink($content);
    }
    if ($return == MS_SUCCESS) {
      $this->log->lwrite('Finished processing', 'COMPLETE_DATASTREAM', $this->pid, $dsid);
    }
    return $return;
  }

}

