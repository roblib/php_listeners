<?php

$reader = new Reader();
//$reader->read("islandora:283", "OBJ", "mp3", "/opt/php_listeners/reader-test");
$reader->write("writer-test.ogg", "islandora:1337", "OBJ", "audio/ogg", "Song", TRUE, FALSE);

unset($reader);

class Reader {

	function __construct() {

		include_once 'message.php';
		include_once 'fedoraConnection.php';
		include_once 'connect.php';
		include_once 'Logging.php';
	
		// Load config file
		$config_file = file_get_contents('config.xml');
		$this->config_xml = new SimpleXMLElement($config_file);
	
    	// Logging settings
    	$log_file = $this->config_xml->log->file;
    	$this->log = new Logging();
    	$this->log->lfile($log_file);

		// Connect to Fedora
		$this->fedora_url = 'http://' . $this->config_xml->fedora->host . ':' . $this->config_xml->fedora->port . '/fedora';
		$this->user = new stdClass();
		$this->user->name = $this->config_xml->fedora->username;
		$this->user->pass = $this->config_xml->fedora->password;
	
		// Set up stomp settings
		$stomp_url = 'tcp://' . $this->config_xml->stomp->host . ':' . $this->config_xml->stomp->port;
		$channel = $this->config_xml->stomp->channel;

		// Make a connection
		try {
	  		$this->con = new Stomp($stomp_url);
		} catch (StompException $e) {
	  		file_put_contents('php://stderr', "Could not open a connection to $stomp_url - $e");
		}
		$this->con->sync = TRUE;
		$this->con->setReadTimeout(1);
	}

    function read($pid, $dsid, $extension, $store_filename) {
		
		try {
        	if (fedora_object_exists($this->fedora_url, $this->user, $pid)) {
       			$fedora_object = new ListenerObject($this->user, $this->fedora_url, $pid);
				$tempfile = $fedora_object->saveDatastream($dsid, $extension);
				copy($tempfile, $store_filename);
         	}
		} catch (Exception $e) {
        	$this->log->lwrite("An error occurred creating the fedora object", 'FAIL_OBJECT', $pid, NULL, $message->author, 'ERROR');
        }
	}

	function write($content, $dest_pid, $dest_dsid, $mimetype, $dest_label, $from_file = TRUE, $delete = TRUE, $stream_type = "M") {

		echo "Writing $content . $mimetype to $dest_pid - $dest_dsid\n";

		$return = FALSE;

		$fedora_object = NULL;

		try {
       		$fedora_object = new ListenerObject($this->user, $this->fedora_url, $dest_pid);
			echo "fedora_object assigned, object: $fedora_object->object\n";
		} catch (Exception $e) {
        	$this->log->lwrite("An error occurred creating the fedora object", 'FAIL_OBJECT', $dest_pid, NULL, $message->author, 'ERROR');
        }

		$datastream = new NewFedoraDatastream($dest_dsid, $stream_type, $fedora_object->object, $fedora_object->repository);

		if ($from_file) {
			$datastream->setContentFromFile($content);
		}
		else {
			$datastream->setContentFromString($content);
		}

		$datastream->label = $dest_label;
		$datastream->mimetype = $mimetype;
		$datastream->state = 'A';
		$datastream->checksum = TRUE;
		$datastream->checksumType = 'MD5';

		if ($log_message) {
			$datastream->logMessage = $log_message;
		}

		$return = $fedora_object->object->ingestDatastream($datastream);

    	if ($delete && $from_file) {
      		unlink($content);
    	}

    	$this->log->lwrite('Finished processing', 'COMPLETE_DATASTREAM', $this->pid, $dsid);

    	return $return;
	}
}

?>
