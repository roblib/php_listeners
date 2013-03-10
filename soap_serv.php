<?php

  	error_reporting(E_ALL ^ E_NOTICE);

	set_include_path(get_include_path() . PATH_SEPARATOR . '/opt/php_listeners');

  	require_once 'SOAP/Server.php';
	require_once 'FedoraConnect.php';
	require_once 'tuque/Object.php';
	require_once 'includes/Image.php';
	require_once 'Logging.php';

  $soap = new SOAP_Server;
  $service = new IslandoraService();
  $soap->addObjectMap($service, 'urn:php-islandora-soapservice');

  if (isset($_SERVER['REQUEST_METHOD']) &&
    $_SERVER['REQUEST_METHOD'] == 'POST') {
    $soap->service($HTTP_RAW_POST_DATA);
  } else {
    require_once 'SOAP/Disco.php';
    $disco = new SOAP_DISCO_Server($soap,
      'DiscoServer');
    if (isset($_SERVER['QUERY_STRING']) &&
      strpos($_SERVER['QUERY_STRING'], 'wsdl') === 0) {
      header('Content-type: text/xml');
      echo $disco->getWSDL();
    }
  }

//$service->JPG('islandora:313', 'JPG', 'thumbnail', 400);
//$service->read('islandora:313', 'JPG');
//$service->write('islandora:313', 'STANLEY_JPG', 'tiny Stanley', base64_encode(file_get_contents("tiny_stanley.jpg")), "image/jpeg");

class IslandoraService {

	var $config;
	var $log;
	var $fedora_connect;
  	var $__dispatch_map = array();

  	function IslandoraService() {

		$config_file = file_get_contents('/opt/php_listeners/config.xml');
		$this->config = new SimpleXMLElement($config_file);

		$this->log = new Logging();
		$this->log->lfile($this->config->log->file);
		
		$this->connect();

    	$this->__dispatch_map['JPG'] = array(
      		'in'  => array(
				'pid' => 'string',
				'dsid' => 'string',
				'label' => 'string',
				'resize' => 'int'
			),
      		'out' => array('exit_status' => 'int')
    	);

		$this->__dispatch_map['TN'] = array(
                        'in'  => array(
                                'pid' => 'string',
                                'dsid' => 'string',
                                'label' => 'string',
                                'height' => 'int',
                                'width' => 'int'
                        ),
                        'out' => array('exit_status' => 'int')
                );

                $this->__dispatch_map['JP2'] = array(
                        'in'  => array(
                                'pid' => 'string',
                                'dsid' => 'string',
                                'label' => 'string'
                        ),
                        'out' => array('exit_status' => 'int')
                );

		$this->__dispatch_map['read'] = array(
			'in' => array(
				'pid' => 'string',
				'dsid' => 'string',
				'extension' => 'string'
			),
			'out' => array('base64_content' => 'string')
		);
		$this->__dispatch_map['write'] = array(
			'in' => array(
				'pid' => 'string',
				'dsid' => 'string',
				'label' => 'string',
				'base64_content' => 'string',
				'mimetype' => 'string'
			),
			'out' => array('message' => 'string')
		);
  	}

	function connect() {
		$fedora_user = new stdClass();
		$fedora_user->name = $this->config->fedora->username;
		$fedora_user->pass = $this->config->fedora->password;
		$this->fedora_connect = new FedoraConnection($fedora_user, 'http://' . $this->config->fedora->host . ':' . $this->config->fedora->port . '/fedora');
	}

  	function __dispatch($method) {
    	if (isset($this->__dispatch_map[$method])) {
      		return $this->__dispatch_map[$method];
    	} else {
      		return null;
    	}
  	}

	function read($pid, $dsid, $extension) {
		
		try {
        	if (fedora_object_exists($this->fedora_url, $this->user, $pid)) {
				$tempfile = $this->fedora_connect->saveDatastream($pid, $dsid, $extension);
				return base64_encode(file_get_contents($tempfile));
         	}
		} catch (Exception $e) {
        	$this->log->lwrite("An error occurred creating the fedora object", 'FAIL_OBJECT', $pid, NULL, $message->author, 'ERROR');
        }
	}

	function write($pid, $dsid, $label, $base64_content, $mimetype) {
		return $this->fedora_connect->addDerivative($pid, $dsid, $label, base64_decode($content), $mimetype, null, true, false);
	}

	function JPG($pid, $dsid = "JPEG", $label = "JPEG image", $resize = "800") {
		$fedora_object = $this->fedora_connect->repository->getObject($pid);
		$image = new Image($fedora_object, $dsid, 'jpg', $this->log, null);
		return $image->JPG($dsid . '_JPG', $label, $resize);
	}

	function JP2($pid, $dsid = "OBJ", $label = "Compressed jp2") {
                $fedora_object = $this->fedora_connect->repository->getObject($pid);
                $image = new Image($fedora_object, $dsid, 'jp2', $this->log, null);
                return $image->JP2($dsid . '_JP2', $label);
        }

        function TN($pid, $dsid = "JPG", $label = "Thumbnail", $height = 200, $width = 200) {
                $fedora_object = $this->fedora_connect->repository->getObject($pid);
                $image = new Image($fedora_object, $dsid, 'jpg', $this->log, null);
                return $image->TN($dsid . '_TN', $label, $height, $width);
        }
}
  
?>
