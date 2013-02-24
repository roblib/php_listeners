<?php

/**
 * Class to create derivatives and ingets them into Fedora
 * 
 * @author Richard Wincewicz
 */
include_once 'PREMIS.php';

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
    if(isset($this->object)){
      unset($this->object);
    }
  }

	protected function create_temp_file()
	{
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
			echo "$mime_type : $this->extension\n";
      		$tempfile = temp_filename($this->extension);
      		$file_handle = fopen($tempfile, 'w');
      		fwrite($file_handle, $datastream->content);
      		fclose($file_handle);
    	} catch (Exception $e) {
      		print "Could not save datastream - $e";
    	}

    	return $tempfile;
	}

  protected function add_derivative($dsid, $label, $content, $mimetype, $log_message = NULL, $delete = TRUE, $from_file = TRUE, $stream_type = "M") {
    $return = FALSE;
    //we are don't seem to be sending custom log message for updates only ingests
    if (isset($this->object[$dsid])) {
      if ($from_file) {
        $this->object[$dsid]->content = file_get_contents($content);
      }
      else {
        $this->object[$dsid]->content = $content;
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
      $return = $this->fedora_object->ingestDatastream($datastream);
    }
    if ($delete && $from_file) {
      unlink($content);
    }
    $this->log->lwrite('Finished processing', 'COMPLETE_DATASTREAM', $this->pid, $dsid);
    return $return;
  }

}

?>
