<?php

	error_reporting(E_ALL ^ (E_DEPRECATED | E_NOTICE));

	set_include_path(get_include_path() . PATH_SEPARATOR . '/opt/php_listeners');

  	require_once 'SOAP/Server.php';
	require_once 'FedoraConnect.php';
	require_once 'tuque/Object.php';
	require_once 'includes/sites/scholar/Scholar.php';
	require_once 'includes/Image.php';
	require_once 'includes/Text.php';
	require_once 'includes/Technical.php';
	require_once 'includes/Pdf.php';
	require_once 'includes/Relationships.php';
	require_once 'Logging.php';

  $soap = new SOAP_Server;
  $service = new IslandoraService();
  $soap->addObjectMap($service, 'urn:php-islandora-soapservice');

  if (isset($_SERVER['REQUEST_METHOD']) &&
    $_SERVER['REQUEST_METHOD'] == 'POST') {
	if (!isset($_SERVER['PHP_AUTH_USER']) ||
		!isset($_SERVER['PHP_AUTH_PW']) ||
		strcmp($_SERVER['PHP_AUTH_USER'], $service->config->httpAuth->username) !== 0 ||
		strcmp($_SERVER['PHP_AUTH_PW'], $service->config->httpAuth->username) != 0) {
		$service->log->lwrite("User " . $_SERVER['PHP_AUTH_USER'] . " unauthorized with password " . $_SERVER['PHP_AUTH_PW'], NULL, NULL, NULL, 'ERROR');
		header('HTTP/1.0 401 Unauthorized');
		exit;
	} else {
    	$soap->service($HTTP_RAW_POST_DATA);
	}
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

// Command line tests:
//$service->TECHMD('islandora:313', 'EXIF', 'Technical metadata');
//$service->Scholar_Policy('islandora:313', 'OBJ', 'PDF');
//$service->AddImageDimensionsToRels('islandora:313', 'OBJ', 'RELS-INT');
//$service->AllOCR('islandora:377', 'JPEG', 'HOCR', 'eng');
//$service->HOCR('islandora:377', 'JPEG', 'HOCR', 'eng');
//$service->OCR('islandora:377', 'JPEG', 'Scanned text', 'eng');
//$service->ENCODED_OCR('islandora:377', 'JPEG', 'Encoded OCR', 'eng');
//$service->Scholar_PDFA('islandora:313', 'JPG', 'PDF');
//$service->TN('islandora:313', 'JPG', 'thumbnail', 400);
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

		// Defining, _L are called from listener with pid only, 
		// others can be called from workflow

		// Image.php calls
    		$this->__dispatch_map['JPG'] = array(
      			'in'  => array(
				'pid' => 'string',
				'dsid' => 'string',
				'label' => 'string',
				'resize' => 'int'
			),
	      		'out' => array('exit_status' => 'int')
	    	);

		$this->__dispatch_map['JPG_L'] = array(
                        'in'  => array(
                                'pid' => 'string'
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

                $this->__dispatch_map['TN_L'] = array(
                        'in'  => array(
                                'pid' => 'string'
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

                $this->__dispatch_map['JP2_L'] = array(
                        'in'  => array(
                                'pid' => 'string',
                                'dsid' => 'string',
                                'label' => 'string'
                        ),
                        'out' => array('exit_status' => 'int')
                );

                $this->__dispatch_map['AllOCR'] = array(
                       'in'  => array(
                               'pid' => 'string',
                               'dsid' => 'string',
                               'label' => 'string',
                               'language' => 'string'
                       ),
                'out' => array('exit_status' => 'int')
                );

		$this->__dispatch_map['AllOCR_L'] = array(
                       'in'  => array(
                               'pid' => 'string'
                       ),
                'out' => array('exit_status' => 'int')
                );

                $this->__dispatch_map['OCR'] = array(
                       'in'  => array(
                               'pid' => 'string',
                               'dsid' => 'string',
                               'label' => 'string',
                               'language' => 'string'
                       ),
                'out' => array('exit_status' => 'int')
                );

		$this->__dispatch_map['OCR_L'] = array(
                       'in'  => array(
                               'pid' => 'string'
                       ),
                'out' => array('exit_status' => 'int')
                );

		$this->__dispatch_map['HOCR'] = array(
                       'in'  => array(
                               'pid' => 'string',
                               'dsid' => 'string',
                               'label' => 'string',
                               'language' => 'string'
                       ),
                'out' => array('exit_status' => 'int')
                );

		$this->__dispatch_map['HOCR_L'] = array(
                       'in'  => array(
                               'pid' => 'string'
                       ),
                'out' => array('exit_status' => 'int')
                );

                $this->__dispatch_map['ENCODED_OCR'] = array(
                       'in'  => array(
                               'pid' => 'string',
                               'dsid' => 'string',
                               'label' => 'string',
                               'language' => 'string'
                       ),
                'out' => array('exit_status' => 'int')
                );

		$this->__dispatch_map['ENCODED_OCR_L'] = array(
                       'in'  => array(
                               'pid' => 'string'
                       ),
                'out' => array('exit_status' => 'int')
                );
	
		// Start TECHMD
                $this->__dispatch_map['TECHMD'] = array(
                        'in' => array(
                                'pid' => 'string',
                                'dsid' => 'string',
                                'label' => 'string'
                        ),
                        'out' => array('exit_status' => 'int')
                );

                // Start Scholar_PDFA
                $this->__dispatch_map['Scholar_PDFA'] = array(
                        'in' => array(
                                'pid' => 'string',
                                'dsid' => 'string',
                                'label' => 'string'
                        ),
                        'out' => array('exit_status' => 'int')
                );

		$this->__dispatch_map['AddImageDimensionsToRels'] = array(
                       'in'  => array(
                               'pid' => 'string',
                               'dsid' => 'string',
                               'label' => 'string'
                       ),
                'out' => array('exit_status' => 'int')
                );

                $this->__dispatch_map['Scholar_policy'] = array(
                       'in'  => array(
                               'pid' => 'string',
                               'dsid' => 'string',
                               'label' => 'string'
                       ),
                'out' => array('exit_status' => 'int')
                );

		// Start: Read & Write
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

  	function __dispatch($method) 
	{
    		if (isset($this->__dispatch_map[$method])) 
		{
      			return $this->__dispatch_map[$method];
    		} 
		else 
		{
      			return null;
    		}
  	}

	function read($pid, $dsid, $extension) {
		
		try {
        	if (fedora_object_exists($this->fedora_url, $this->user, $pid)) {
				$content = $this->fedora_connect->getDatastream($pid, $dsid)->content;
				return base64_encode($content);
         	}
		} catch (Exception $e) {
        	$this->log->lwrite("An error occurred creating the fedora object", 'FAIL_OBJECT', $pid, NULL, $message->author, 'ERROR');
        }
	}

	function write($pid, $dsid, $label, $base64_content, $mimetype) {
		return $this->fedora_connect->addDerivative($pid, $dsid, $label, base64_decode($content), $mimetype, null, true, false);
	}

	function AllOCR($pid, $dsid = 'JPEG', $label = 'HOCR', $language = 'eng')
        {
		$result = -1;
                $this->log->lwrite("Function AllOCR starting...", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                try {
                  $fedora_object = $this->fedora_connect->repository->getObject($pid);
                  $this->log->lwrite("Fedora object successfully fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                } catch (Exception $ex) {
                  $this->log->lwrite("Fedora object not fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
                  return -2;
                }
                if ($text = new Text($fedora_object, $dsid, 'jpeg', $this->log, null)) {
                  $this->log->lwrite("Text derivative created", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                } else {
                  $this->log->lwrite("Derivative not created", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
                  $result = -3;
                }
                $funcresult = $text->AllOCR($dsid . '_HOCR', $label, $language);
                if ($funcresult == 0) {
                  $this->log->lwrite("AllOCR function successful", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                  $result = 0;
                } else {
                  $this->log->lwrite("AllOCR function failed", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
                  $result = $funcresult;
                }
                return $result;
        }

	function AllOCR_L($pid, $dsid = 'JPEG', $label = 'HOCR', $language = 'eng')
        {
                $result = -1;
                $this->log->lwrite("Function AllOCR starting...", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                try {
                  $fedora_object = $this->fedora_connect->repository->getObject($pid);
                  $this->log->lwrite("Fedora object successfully fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                } catch (Exception $ex) {
                  $this->log->lwrite("Fedora object not fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
                  return -2;
                }
                if ($text = new Text($fedora_object, $dsid, 'jpeg', $this->log, null)) {
                  $this->log->lwrite("Text derivative created", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                } else {
                  $this->log->lwrite("Derivative not created", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
                  $result = -3;
                }
                $funcresult = $text->AllOCR($dsid . '_HOCR', $label, $language);
                if ($funcresult == 0) {
                  $this->log->lwrite("AllOCR function successful", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                  $result = 0;
                } else {
                  $this->log->lwrite("AllOCR function failed", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
                  $result = $funcresult;
                }
                return $result;
        }

        function OCR($pid, $dsid = 'OCR', $label = 'Scanned text', $language = 'eng')
        {
		$result = -1;
                $this->log->lwrite("Function OCR starting...", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                try {
                  $fedora_object = $this->fedora_connect->repository->getObject($pid);
                  $this->log->lwrite("Fedora object successfully fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                } catch (Exception $ex) {
                  $this->log->lwrite("Fedora object not fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
                  return -2;
                }
                if ($text = new Text($fedora_object, $dsid, 'txt', $this->log, null)) {
                  $this->log->lwrite("Text derivative created", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                } else {
                  $this->log->lwrite("Derivative not created", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
                  $result = -3;
                }
                $funcresult = $text->OCR($dsid . '_OCR', $label, $language);
                if ($funcresult == 0) {
                  $this->log->lwrite("OCR function successful", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                  $result = 0;
                } else {
                  $this->log->lwrite("OCR function failed", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
                  $result = $funcresult;
                }
                return $result;
        }

	function OCR_L($pid, $dsid = 'OCR', $label = 'Scanned text', $language = 'eng')
        {
                $result = -1;
                $this->log->lwrite("Function OCR starting...", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                try {
                  $fedora_object = $this->fedora_connect->repository->getObject($pid);
                  $this->log->lwrite("Fedora object successfully fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                } catch (Exception $ex) {
                  $this->log->lwrite("Fedora object not fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
                  return -2;
                }
                if ($text = new Text($fedora_object, $dsid, 'txt', $this->log, null)) {
                  $this->log->lwrite("Text derivative created", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                } else {
                  $this->log->lwrite("Derivative not created", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
                  $result = -3;
                }
                $funcresult = $text->OCR($dsid . '_OCR', $label, $language);
                if ($funcresult == 0) {
                  $this->log->lwrite("OCR function successful", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                  $result = 0;
                } else {
                  $this->log->lwrite("OCR function failed", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
                  $result = $funcresult;
                }
                return $result;
        }

        function HOCR($pid, $dsid = 'HOCR', $label = 'HOCR', $language = 'eng')
        {
		$result = -1;
                $this->log->lwrite("Function HOCR starting...", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                try {
                  $fedora_object = $this->fedora_connect->repository->getObject($pid);
                  $this->log->lwrite("Fedora object successfully fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                } catch (Exception $ex) {
                  $this->log->lwrite("Fedora object not fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
                  return -2;
                }
                if ($text = new Text($fedora_object, $dsid, 'jpg', $this->log, null)) {
                  $this->log->lwrite("Text derivative created", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                } else {
                  $this->log->lwrite("Derivative not created", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
                  $result = -3;
                }
                $funcresult = $text->HOCR($dsid . '_HOCR', $label, $language);
                if ($funcresult == 0) {
                  $this->log->lwrite("HOCR function successful", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                  $result = 0;
                } else {
                  $this->log->lwrite("HOCR function failed", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
                  $result = $funcresult;
                }
                return $result;
        }

	function HOCR_L($pid, $dsid = 'HOCR', $label = 'HOCR', $language = 'eng')
        {
                $result = -1;
                $this->log->lwrite("Function HOCR starting...", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                try {
                  $fedora_object = $this->fedora_connect->repository->getObject($pid);
                  $this->log->lwrite("Fedora object successfully fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                } catch (Exception $ex) {
                  $this->log->lwrite("Fedora object not fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
                  return -2;
                }
                if ($text = new Text($fedora_object, $dsid, 'jpg', $this->log, null)) {
                  $this->log->lwrite("Text derivative created", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                } else {
                  $this->log->lwrite("Derivative not created", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
                  $result = -3;
                }
                $funcresult = $text->HOCR($dsid . '_HOCR', $label, $language);
                if ($funcresult == 0) {
                  $this->log->lwrite("HOCR function successful", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                  $result = 0;
                } else {
                  $this->log->lwrite("HOCR function failed", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
                  $result = $funcresult;
                }
                return $result;
        }

        function ENCODED_OCR($pid, $dsid = 'ENCODED_OCR', $label = 'Encoded OCR', $language = 'eng')
        {
		$result = -1;
                $this->log->lwrite("Function ENCODED_OCR starting...", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                try {
                  $fedora_object = $this->fedora_connect->repository->getObject($pid);
                  $this->log->lwrite("Fedora object successfully fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                } catch (Exception $ex) {
                  $this->log->lwrite("Fedora object not fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
                  return -2;
                }
                if ($text = new Text($fedora_object, $dsid, 'txt', $this->log, null)) {
                  $this->log->lwrite("Text derivative created", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                } else {
                  $this->log->lwrite("Derivative not created", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
                  $result = -3;
                }
                $funcresult = $text->ENCODED_OCR($dsid . '_EncodedOCR', $label, $language);
                if ($funcresult == 0) {
                  $this->log->lwrite("ENCODED_OCR function successful", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                  $result = 0;
                } else {
                  $this->log->lwrite("ENCODED_OCR function failed", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
                  $result = $funcresult;
                }
                return $result;
        }

	function ENCODED_OCR_L($pid, $dsid = 'ENCODED_OCR', $label = 'Encoded OCR', $language = 'eng')
        {
                $result = -1;
                $this->log->lwrite("Function ENCODED_OCR starting...", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                try {
                  $fedora_object = $this->fedora_connect->repository->getObject($pid);
                  $this->log->lwrite("Fedora object successfully fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                } catch (Exception $ex) {
                  $this->log->lwrite("Fedora object not fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
                  return -2;
                }
                if ($text = new Text($fedora_object, $dsid, 'txt', $this->log, null)) {
                  $this->log->lwrite("Text derivative created", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                } else {
                  $this->log->lwrite("Derivative not created", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
                  $result = -3;
                }
                $funcresult = $text->ENCODED_OCR($dsid . '_EncodedOCR', $label, $language);
                if ($funcresult == 0) {
                  $this->log->lwrite("ENCODED_OCR function successful", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                  $result = 0;
                } else {
                  $this->log->lwrite("ENCODED_OCR function failed", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
                  $result = $funcresult;
                }
                return $result;
        }

	function JPG($pid, $dsid = "JPEG", $label = "JPEG image", $resize = "800") {
		$fedora_object = $this->fedora_connect->repository->getObject($pid);
		$image = new Image($fedora_object, $dsid, 'jpg', $this->log, null);
		return $image->JPG($dsid . '_JPG', $label, $resize);
	}

	function JPG_L($pid, $dsid = "JPEG", $label = "JPEG image", $resize = "800") {
                $fedora_object = $this->fedora_connect->repository->getObject($pid);
                $image = new Image($fedora_object, $dsid, 'jpg', $this->log, null);
                return $image->JPG($dsid . '_JPG', $label, $resize);
        }

	function JP2($pid, $dsid = "OBJ", $label = "Compressed jp2") {
                $result = -1;
                $this->log->lwrite("Function JP2 starting...", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
		try {
                  $fedora_object = $this->fedora_connect->repository->getObject($pid);
                  $this->log->lwrite("Fedora object successfully fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                } catch (Exception $ex) {
                  $this->log->lwrite("Fedora object not fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
                  return -2;
                }
                if ($image = new Image($fedora_object, $dsid, 'jp2', $this->log, null)) {
		  $this->log->lwrite("Image derivative created", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                } else {
                  $this->log->lwrite("Derivative not created", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
                  $result = -3;
                }
		$funcresult = $image->JP2($dsid . '_JP2', $label);
		if ($funcresult == 0) {
                  $this->log->lwrite("JP2 function successful", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
		  $result = 0;
                } else {
                  $this->log->lwrite("JP2 function failed", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
		  $result = $funcresult;
                }
                return $result;
        }

	function JP2_L($pid, $dsid = "OBJ", $label = "Compressed jp2") {
                $result = -1;
                $this->log->lwrite("Function JP2 starting...", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                try {
                  $fedora_object = $this->fedora_connect->repository->getObject($pid);
                  $this->log->lwrite("Fedora object successfully fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                } catch (Exception $ex) {
                  $this->log->lwrite("Fedora object not fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
                  return -2;
                }
                if ($image = new Image($fedora_object, $dsid, 'jp2', $this->log, null)) {
                  $this->log->lwrite("Image derivative created", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                } else {
                  $this->log->lwrite("Derivative not created", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
                  $result = -3;
                }
                $funcresult = $image->JP2($dsid . '_JP2', $label);
                if ($funcresult == 0) {
                  $this->log->lwrite("JP2 function successful", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                  $result = 0;
                } else {
                  $this->log->lwrite("JP2 function failed", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
                  $result = $funcresult;
                }
                return $result;
        }

        function TN($pid, $dsid = "JPG", $label = "Thumbnail", $height = 200, $width = 200) {
		$result = -1;
		$this->log->lwrite("Function TN starting...", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                try {
      		  $fedora_object = $this->fedora_connect->repository->getObject($pid);
		  $this->log->lwrite("Fedora object successfully fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
  		} catch (Exception $ex) {
                  $this->log->lwrite("Fedora object not fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
                  return -2;
                }
                if ($image = new Image($fedora_object, $dsid, 'jpg', $this->log, null)) {
		  $this->log->lwrite("Image derivative created", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                } else {
                  $this->log->lwrite("Derivative not created", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
                  $result = -3;
                }
		$funcresult = $image->TN($dsid . '_TN', $label, $height, $width);
		if ($funcresult == 0) {
		  $this->log->lwrite("TN function successful", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
		  $result = 0;
		} else {
		  $this->log->lwrite("TN function failed", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
		  $result = $funcresult;
		}
                return $result;
        }

	function TN_L($pid, $dsid = "JPG", $label = "Thumbnail", $height = 200, $width = 200) {
                $result = -1;
                $this->log->lwrite("Function TN starting...", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                try {
                  $fedora_object = $this->fedora_connect->repository->getObject($pid);
                  $this->log->lwrite("Fedora object successfully fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                } catch (Exception $ex) {
                  $this->log->lwrite("Fedora object not fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
                  return -2;
                }
                if ($image = new Image($fedora_object, $dsid, 'jpg', $this->log, null)) {
                  $this->log->lwrite("Image derivative created", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                } else {
                  $this->log->lwrite("Derivative not created", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
                  $result = -3;
                }
                $funcresult = $image->TN($dsid . '_TN', $label, $height, $width);
                if ($funcresult == 0) {
                  $this->log->lwrite("TN function successful", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                  $result = 0;
                } else {
                  $this->log->lwrite("TN function failed", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
                  $result = $funcresult;
                }
                return $result;
        }

	function TECHMD($pid, $dsid = 'TECHMD', $label = 'Technical metadata')
        {
		$result = -1;
                $this->log->lwrite("Function TECHMD starting...", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                try {
                  $fedora_object = $this->fedora_connect->repository->getObject($pid);
                  $this->log->lwrite("Fedora object successfully fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                } catch (Exception $ex) {
                  $this->log->lwrite("Fedora object not fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
                  return -2;
                }
                if ($tech = new Technical($fedora_object, $dsid, 'xml', $this->log, null)) {
                  $this->log->lwrite("Technical metadata derivative created", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                } else {
                  $this->log->lwrite("Derivative not created", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
                  $result = -3;
                }
                $funcresult = $tech->TECHMD($dsid . '_TECHMD', $label, $height, $width);
                if ($funcresult == 0) {
                  $this->log->lwrite("TECHMD function successful", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                  $result = 0;
                } else {
                  $this->log->lwrite("TECHMD function failed", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
                  $result = $funcresult;
                }
                return $result;
        }

        function Scholar_PDFA($pid, $dsid = 'PDF', $label = 'PDF')
        {
		$result = -1;
                $this->log->lwrite("Function Scholar_PDFA starting...", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                try {
                  $fedora_object = $this->fedora_connect->repository->getObject($pid);
                  $this->log->lwrite("Fedora object successfully fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                } catch (Exception $ex) {
                  $this->log->lwrite("Fedora object not fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
                  return -2;
                }
                if ($pdf = new Pdf($fedora_object, $dsid, 'jpg', $this->log, null)) {
                  $this->log->lwrite("Pdf derivative created", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                } else {
                  $this->log->lwrite("Derivative not created", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
                  $result = -3;
                }
                $funcresult = $pdf->Scholar_PDFA($dsid . '_TN', $label);
                if ($funcresult == 0) {
                  $this->log->lwrite("Scholar_PDFA function successful", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                  $result = 0;
                } else {
                  $this->log->lwrite("Scholar_PDFA function failed", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
                  $result = $funcresult;
                }
                return $result;
        }

	function AddImageDimensionsToRels($pid, $dsid = 'OBJ', $label = 'RELS-INT')
	{
		$result = -1;
                $this->log->lwrite("Function Scholar_Policy starting...", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                try {
                  $fedora_object = $this->fedora_connect->repository->getObject($pid);
                  $this->log->lwrite("Fedora object successfully fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                } catch (Exception $ex) {
                  $this->log->lwrite("Fedora object not fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
                  return -2;
                }
                if ($rels = new Relationship($fedora_object, $dsid, 'xml', $this->log, null)) {
                  $this->log->lwrite("Relationship derivative created", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                } else {
                  $this->log->lwrite("Derivative not created", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
                  $result = -3;
                }
                $funcresult = $rels->AddImageDimensionsToRels($dsid . '_POLICY', $label, $height, $width);
                if ($funcresult == 0) {
                  $this->log->lwrite("AddImageDimensionsToRels function successful", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                  $result = 0;
                } else {
                  $this->log->lwrite("AddImageDimensionsToRels function failed", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
                  $result = $funcresult;
                }
                return $result;
	}

	function Scholar_Policy($pid, $dsid = 'POLICY', $label = "Embargo policy - Both")
	{
		$result = -1;
                $this->log->lwrite("Function Scholar_Policy starting...", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                try {
                  $fedora_object = $this->fedora_connect->repository->getObject($pid);
                  $this->log->lwrite("Fedora object successfully fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                } catch (Exception $ex) {
                  $this->log->lwrite("Fedora object not fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
                  return -2;
                }
                if ($policy = new Scholar($fedora_object, $dsid, 'xml', $this->log, null)) {
                  $this->log->lwrite("Policy derivative created", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                } else {
                  $this->log->lwrite("Derivative not created", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
                  $result = -3;
                }
                $funcresult = $policy->Scholar_Policy($dsid . '_POLICY', $label, $height, $width);
                if ($funcresult == 0) {
                  $this->log->lwrite("Scholar_Policy function successful", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                  $result = 0;
                } else {
                  $this->log->lwrite("Scholar_Policy function failed", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
                  $result = $funcresult;
                }
                return $result;
	}

}
  
?>

