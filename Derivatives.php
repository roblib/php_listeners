<?php

/**
 * Class to create derivatives and ingets them into Fedora
 * 
 * @author Richard Wincewicz
 */
include_once 'PREMIS.php';

class Derivative {

  function __construct($fedora_object, $incoming_dsid, $extension = NULL, $log, $created_datastream) {
    include_once 'message.php';
    include_once 'fedoraConnection.php';

    $this->log = $log;
    $this->fedora_object = $fedora_object;
    $this->object = $fedora_object->object;
    $this->pid = $fedora_object->object->id;
    $this->created_datastream = $created_datastream;
    $this->incoming_dsid = $incoming_dsid;
    $this->incoming_datastream = new FedoraDatastream($this->incoming_dsid, $this->fedora_object->object, $this->fedora_object->repository);
    //$this->mimetype = $this->incoming_datastream->mimetype;
    //$this->log->lwrite('Mimetype: ' . $this->mimetype, 'SERVER_INFO');
    $this->extension = $extension;
    if ($this->incoming_dsid != NULL) {
      $this->temp_file = $fedora_object->saveDatastream($incoming_dsid, $extension);
    }
    $extension_array = explode('.', $this->temp_file);
    $extension = $extension_array[1];
  }

  function __destruct() {
    unlink($this->temp_file);
  }

  protected function add_derivative($dsid, $label, $content, $mimetype, $log_message = NULL, $delete = TRUE, $from_file = TRUE) {
    $return = FALSE;
    if (isset($this->object[$dsid])) {
      if ($from_file) {
        $this->object[$dsid]->content = file_get_contents($content);
      }
      else {
        $this->object[$dsid]->content = content;
      }
    }
    else {
      $datastream = new NewFedoraDatastream($dsid, 'M', $this->object, $this->fedora_object->repository);
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
      $return = $this->object->ingestDatastream($datastream);
    }
    if ($delete && $from_file) {
      unlink($content);
    }
    $this->log->lwrite('Finished processing', 'COMPLETE_DATASTREAM', $this->pid, $dsid);
    return $return;
  }

}

?>