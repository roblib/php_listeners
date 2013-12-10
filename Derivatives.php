<?php
/**
 * Class to create derivatives and ingets them into Fedora
 * 
 * @author Richard Wincewicz
 */
define('MS_FEDORA_EXCEPTION', -100);
define('MS_SYSTEM_EXCEPTION', -1); //errors returned from imagemagick or other system calls
define('MS_SUCCESS', 0);

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
    }
    $extension_array = explode('.', $this->temp_file);
    $extension = $extension_array[1];
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
      print "Could not find the $this->incoming_dsid datastream!";
    }
    try {
      $datastream = $this->fedora_object->getDatastream($this->incoming_dsid);
      $mime_type = $datastream->mimetype;
      if (!$this->extension) {
        $this->extension = system_mime_type_extension($mime_type);
      }
      $tempfile = temp_filename($this->extension);
      $file_handle = fopen($tempfile, 'w');
      fwrite($file_handle, $datastream->content);
      fclose($file_handle);
      } catch (Exception $e) {
      $this->log->lwrite('Could not create temp file from datastream ' . $e->getMessage(), 'PROCESS_DATASTREAM', $this->pid, $dsid);
      }
    return $tempfile;
  }

  /**
   * calls tuque to add the datastream to the object.  Also logs success or failure
   * @param type $dsid
   * @param type $label
   * @param type $content
   * @param type $mimetype
   * @param type $log_message
   * @param type $delete
   * @param type $from_file
   * @param type $stream_type
   * @return string
   *   
   */
  protected function add_derivative($dsid, $label, $content, $mimetype, $log_message = NULL, $delete = TRUE, $from_file = TRUE, $stream_type = "M") {
    $return = MS_SUCCESS;
    if(!isset($this->fedora_object)){
      $this->log->lwrite("Could not create the $dsid derivative! The object does not exist", 'OBJECT_DELETED', $this->pid, $dsid, NULL, 'INFO');
      //we want to acknowledge that we have received and processed the message
      return $return;
    }
    //TODO we are don't seem to be sending custom log message for updates only ingests
    if (isset($this->fedora_object[$dsid])) {
      $this->log->lwrite("updating the datastream $dsid derivative! ", 'DATASTREAM_EXISTS', $this->pid, $dsid, NULL, 'INFO');
      if ($from_file) {
        $content = file_get_contents($content);
      }
      $arr = array('dsString' => $content);
      if ($log_message) {
        $arr['logMessage'] = $log_message;
      }
      try{
        $this->fedora_object->repository->api->m->modifyDatastream($this->fedora_object->id, $dsid, $arr);
      }catch(Exception $e){
        $this->log->lwrite("Could not update the $dsid derivative! " . $e->getMessage().' '. $e->getTraceAsString(), 'DATASTREAM_EXISTS', $this->pid, $dsid, NULL, 'ERROR');
        $return = MS_FEDORA_EXCEPTION;
      }
    }
    else {
      $datastream = new NewFedoraDatastream($dsid, $stream_type, $this->fedora_object, $this->fedora_object->repository);
      if ($from_file) {
        $datastream->setContentFromFile($content);
      }
      else {
        $datastream->setContentFromString($content);
      }
      $datastream->label = $label;
      $datastream->mimetype = $mimetype;
      $datastream->state = 'A';
      $datastream->checksum = TRUE;
      $datastream->checksumType = 'MD5';
      if ($log_message) {
        $datastream->logMessage = $log_message;
      }
      try{
        $return = $this->fedora_object->ingestDatastream($datastream);
        if ($return == FALSE) {
          $this->log->lwrite("Could not create the $dsid derivative! it may have already existed in this object", 'DATASTREAM_EXISTS', $this->pid, $dsid, NULL, 'INFO');
          //we have to return success here or we will get in an endless loop if the workflows are configured to loop until success.  this scenario should not happen very often
        }
        $return = MS_SUCCESS; //in microservices 0 = success
      } catch (Exception $e){
        $return = MS_FEDORA_EXCEPTION;
        $this->log->lwrite("Could not create the $dsid derivative! " . $e->getMessage() . ' ' . $e->getTraceAsString(), 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
      }
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
?>