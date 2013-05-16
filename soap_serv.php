<?php

// PHP error reporting
error_reporting(E_ALL ^ (E_DEPRECATED | E_NOTICE));

// Working php_listeners dir
//TODO MAKE the path to this more generic in case the listeners are not configured in this directory
set_include_path(get_include_path() . PATH_SEPARATOR . '/opt/php_listeners');

// requires
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

// SOAP server object creation
$soap = new SOAP_Server;
$service = new IslandoraService();
$soap->addObjectMap($service, 'urn:php-islandora-soapservice');

// SOAP server creation:
// Supports http authentication
if (isset($_SERVER['REQUEST_METHOD']) &&
    $_SERVER['REQUEST_METHOD'] == 'POST') {
//	if (!isset($_SERVER['PHP_AUTH_USER']) ||
//		!isset($_SERVER['PHP_AUTH_PW']) ||
//		strcmp($_SERVER['PHP_AUTH_USER'], $service->config->httpAuth->username) !== 0 ||
//		strcmp($_SERVER['PHP_AUTH_PW'], $service->config->httpAuth->username) != 0) {
//		$service->log->lwrite("User " . $_SERVER['PHP_AUTH_USER'] . " unauthorized with password " . $_SERVER['PHP_AUTH_PW'], NULL, NULL, NULL, 'ERROR');
//		header('HTTP/1.0 401 Unauthorized');
//		exit;
//	} else {
  $soap->service($HTTP_RAW_POST_DATA);
//	}
}
else {
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
//$service->TN('islandora:313', 'JPG', 'TN', 'thumbnail', 400, 400);
//$service->read('islandora:313', 'JPG');
//$service->write('islandora:313', 'STANLEY_JPG', 'tiny Stanley', base64_encode(file_get_contents("tiny_stanley.jpg")), "image/jpeg");
// #%#%#%#%#%#%#%#%#%#%#%#%#%#%#%#%#%
//
// IslandoraService:
// This class describes all microservices/WSDLs, logging, and handles the connection to an 
// arbitrary fedora repository (specified in config.xml). The __dispatch_map methods describe
// the WSDL inputs and outputs accessible from Taverna workbench as well as to external users
// (once given the proper user/pass for authentication). 
//
// #%#%#%#%#%#%#%#%#%#%#%#%#%#%#%#%#%
class IslandoraService {

  var $config;
  var $log;
  var $fedora_connect;
  var $__dispatch_map = array();

  function IslandoraService() {
    //TODO MAKE the path to this more generic in case the listeners are not configured in this directory
    $config_file = file_get_contents('/var/www/html/drupal/php_listeners/config.xml');
    $this->config = new SimpleXMLElement($config_file);

    $this->log = new Logging();
    $this->log->lfile($this->config->log->file);

    $this->connect();

    // Defining, _L are called from listener with pid only, 
    // others can be called from workflow
    // #%#%#%#%#%#%#%#%#%#%#%#%#%
    //
		// Dispatch maps:
    // Each of these I/O arrays must be defined specifically
    // for individual services; as there does not seem to be an existing,
    // unifying way of defining a generic WSDL, this seems to be the most
    // solid way to expose the services. Each definition requires
    // an 'in' and 'out' element, each specifying their own arrays of
    // $pid, $dsid, $label, and any other parameter required by the service's fucntion
    //
		// #%#%#%#%#%#%#%#%#%#%#%#%#%
    // Image.php calls
    $this->__dispatch_map['JPG'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string',
        'outputdsid' => 'string',
        'label' => 'string',
        'resize' => 'int'
      ),
      'out' => array('exit_status' => 'int')
    );

    // JPG duplication - default values
    $this->__dispatch_map['JPG_L'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string'
      ),
      'out' => array('exit_status' => 'int')
    );

    // Image.php - Thumbnail
    $this->__dispatch_map['TN'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string',
        'outputdsid' => 'string',
        'label' => 'string',
        'height' => 'int',
        'width' => 'int'
      ),
      'out' => array('exit_status' => 'int')
    );

    // Thumbnail duplication - default values
    $this->__dispatch_map['TN_L'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string'
      ),
      'out' => array('exit_status' => 'int')
    );

    // Image.php - JP2
    $this->__dispatch_map['JP2'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string',
        'outputdsid' => 'string',
        'label' => 'string'
      ),
      'out' => array('exit_status' => 'int')
    );

    // JP2 duplication - default values
    $this->__dispatch_map['JP2_L'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string'
      ),
      'out' => array('exit_status' => 'int')
    );

    // Text.php - AllOCR
    $this->__dispatch_map['AllOCR'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string',
        'outputdsid' => 'string',
        'label' => 'string',
        'language' => 'string'
      ),
      'out' => array('exit_status' => 'int')
    );

    // AllOCR duplication - default values
    $this->__dispatch_map['AllOCR_L'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string'
      ),
      'out' => array('exit_status' => 'int')
    );

    // Image.php - OCR
    $this->__dispatch_map['OCR'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string',
        'outputdsid' => 'string',
        'label' => 'string',
        'language' => 'string'
      ),
      'out' => array('exit_status' => 'int')
    );

    // OCR duplication - default values
    $this->__dispatch_map['OCR_L'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string'
      ),
      'out' => array('exit_status' => 'int')
    );

    // Image.php - HOCR
    $this->__dispatch_map['HOCR'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string',
        'outputdsid' => 'string',
        'label' => 'string',
        'language' => 'string'
      ),
      'out' => array('exit_status' => 'int')
    );

    // HOCR duplication - default values
    $this->__dispatch_map['HOCR_L'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string'
      ),
      'out' => array('exit_status' => 'int')
    );

    // Image.php - ENCODED_OCR
    $this->__dispatch_map['ENCODED_OCR'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string',
        'outputdsid' => 'string',
        'label' => 'string',
        'language' => 'string'
      ),
      'out' => array('exit_status' => 'int')
    );

    // ENCODED_OCR duplication - default values
    $this->__dispatch_map['ENCODED_OCR_L'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string'
      ),
      'out' => array('exit_status' => 'int')
    );

    // Technical.php - TECHMD
    $this->__dispatch_map['TECHMD'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string',
        'outputdsid' => 'string',
        'label' => 'string'
      ),
      'out' => array('exit_status' => 'int')
    );

    // Pdf.php - Scholar_PDFA
    $this->__dispatch_map['Scholar_PDFA'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string',
        'outputdsid' => 'string',
        'label' => 'string'
      ),
      'out' => array('exit_status' => 'int')
    );

    // Scholar_PDFA duplication - default values
    $this->__dispatch_map['Scholar_PDFA_L'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string'
      ),
      'out' => array('exit_status' => 'int')
    );

    // Relationships.php - AddImageDimensionsToRels
    $this->__dispatch_map['AddImageDimensionsToRels'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string',
        'outputdsid' => 'string',
        'label' => 'string'
      ),
      'out' => array('exit_status' => 'int')
    );

    // Scholar.php - Scholar_Policy
    $this->__dispatch_map['Scholar_Policy'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string',
        'outputdsid' => 'string',
        'label' => 'string'
      ),
      'out' => array('exit_status' => 'int')
    );

    // Scholar_policy duplication - default values
    $this->__dispatch_map['Scholar_Policy_L'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string'
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

  // Sets up the connection to the fedora repository
  function connect() {
    $fedora_user = new stdClass();
    $fedora_user->name = $this->config->fedora->username;
    $fedora_user->pass = $this->config->fedora->password;
    $this->fedora_connect = new FedoraConnection($fedora_user, 'http://' . $this->config->fedora->host . ':' . $this->config->fedora->port . '/fedora');
  }

  // Used by SOAP server - Exposes WSDLs
  function __dispatch($method) {
    if (isset($this->__dispatch_map[$method])) {
      return $this->__dispatch_map[$method];
    }
    else {
      return null;
    }
  }

  // Function - Read:
  // Reads a fedora object from an external repository
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

  // Function - Write:
  // Writes a fedora object back to the repository
  function write($pid, $dsid, $label, $base64_content, $mimetype) {
    return $this->fedora_connect->addDerivative($pid, $dsid, $label, base64_decode($content), $mimetype, null, true, false);
  }

  // #%#%#%#%#%#%#%#%#%#%#%#%#%#%#%#%#%#%#%#%
  // 
  // Microservices - Functions:
  // Below are calls to the microservice functions with logging (SOAP_LOG) at each
  // step of the envoking process. Each function returns the same exit codes for the
  // possible failures that each of the functions can experience (explained in the user manual). 
  // Duplicate functions were added for the ease of defaults; these will be used by users
  // who have little knowledge of manipulating objects or invoking certain dsIDs and
  // are directly invoked by the listener.
  //
	// #%#%#%#%#%#%#%#%#%#%#%#%#%#%#%#%#%#%#%#%
  function AllOCR($pid, $dsid = 'JPEG', $outputdsid = 'HOCR', $label = 'HOCR', $language = 'eng') {
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
    }
    else {
      $this->log->lwrite("Derivative not created", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = -3;
    }
    $funcresult = $text->AllOCR($outputdsid, $label, $language);
    if ($funcresult == 0) {
      $this->log->lwrite("AllOCR function successful", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
      $result = 0;
    }
    else {
      $this->log->lwrite("AllOCR function failed", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = $funcresult;
    }
    return $result;
  }

  function AllOCR_L($pid, $dsid = 'JPEG', $outputdsid = 'HOCR', $label = 'HOCR', $language = 'eng') {
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
    }
    else {
      $this->log->lwrite("Derivative not created", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = -3;
    }
    $funcresult = $text->AllOCR($outputdsid, $label, $language);
    if ($funcresult == 0) {
      $this->log->lwrite("AllOCR function successful", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
      $result = 0;
    }
    else {
      $this->log->lwrite("AllOCR function failed", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = $funcresult;
    }
    return $result;
  }

  function OCR($pid, $dsid = 'OCR', $outputdsid = 'OCR', $label = 'Scanned text', $language = 'eng') {
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
    }
    else {
      $this->log->lwrite("Derivative not created", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = -3;
    }
    $funcresult = $text->OCR($outputdsid, $label, $language);
    if ($funcresult == 0) {
      $this->log->lwrite("OCR function successful", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
      $result = 0;
    }
    else {
      $this->log->lwrite("OCR function failed", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = $funcresult;
    }
    return $result;
  }

  function OCR_L($pid, $dsid = 'JPEG', $outputdsid = 'OCR', $label = 'Scanned text', $language = 'eng') {
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
    }
    else {
      $this->log->lwrite("Derivative not created", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = -3;
    }
    $funcresult = $text->OCR($outputdsid, $label, $language);
    if ($funcresult == 0) {
      $this->log->lwrite("OCR function successful", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
      $result = 0;
    }
    else {
      $this->log->lwrite("OCR function failed", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = $funcresult;
    }
    return $result;
  }

  function HOCR($pid, $dsid = 'JPEG', $outputdsid = 'HOCR', $label = 'HOCR', $language = 'eng') {
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
    }
    else {
      $this->log->lwrite("Derivative not created", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = -3;
    }
    $funcresult = $text->HOCR($outputdsid, $label, $language);
    if ($funcresult == 0) {
      $this->log->lwrite("HOCR function successful", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
      $result = 0;
    }
    else {
      $this->log->lwrite("HOCR function failed", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = $funcresult;
    }
    return $result;
  }

  function HOCR_L($pid, $dsid = 'JPEG', $outputdsid = 'HOCR', $label = 'HOCR', $language = 'eng') {
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
    }
    else {
      $this->log->lwrite("Derivative not created", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = -3;
    }
    $funcresult = $text->HOCR($outputdsid, $label, $language);
    if ($funcresult == 0) {
      $this->log->lwrite("HOCR function successful", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
      $result = 0;
    }
    else {
      $this->log->lwrite("HOCR function failed", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = $funcresult;
    }
    return $result;
  }

  function ENCODED_OCR($pid, $dsid = 'JPEG', $outputdsid = 'ENCODED_OCR', $label = 'Encoded OCR', $language = 'eng') {
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
    }
    else {
      $this->log->lwrite("Derivative not created", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = -3;
    }
    $funcresult = $text->ENCODED_OCR($outputdsid, $label, $language);
    if ($funcresult == 0) {
      $this->log->lwrite("ENCODED_OCR function successful", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
      $result = 0;
    }
    else {
      $this->log->lwrite("ENCODED_OCR function failed", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = $funcresult;
    }
    return $result;
  }

  function ENCODED_OCR_L($pid, $dsid = 'JPEG', $outputdsid = 'ENCODED_OCR', $label = 'Encoded OCR', $language = 'eng') {
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
    }
    else {
      $this->log->lwrite("Derivative not created", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = -3;
    }
    $funcresult = $text->ENCODED_OCR($outputdsid, $label, $language);
    if ($funcresult == 0) {
      $this->log->lwrite("ENCODED_OCR function successful", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
      $result = 0;
    }
    else {
      $this->log->lwrite("ENCODED_OCR function failed", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = $funcresult;
    }
    return $result;
  }

  function JPG($pid, $dsid = "JPEG", $outputdsid = "JPG", $label = "JPEG image", $resize = "800") {
    $result = -1;
    try{
      $fedora_object = $this->fedora_connect->repository->getObject($pid);
       $this->log->lwrite("Fedora object successfully fetched for JPG funciton", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
    } catch (Exception $e){
       $this->log->lwrite("Fedora object could not be fetched for JPG function", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
       return -2;
    }
    $image = new Image($fedora_object, $dsid, NULL, $this->log, null);
    return $image->JPG($outputdsid, $label, $resize);
  }

  // Some objects have JPEG, some JPG. Added check
  function JPG_L($pid, $dsid = "JPEG", $outputdsid = "JPG", $label = "JPEG image", $resize = "800") {
    $fedora_object = $this->fedora_connect->repository->getObject($pid);
    if ($fedora_object->getDatastream($dsid) == FALSE) {
      $this->log->lwrite("No JPEG, checking for JPG", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
      $dsid = "JPG";
    }
    $image = new Image($fedora_object, $dsid, 'jpg', $this->log, null);
    return $image->JPG($outputdsid, $label, $resize);
  }

  function JP2($pid, $dsid = "OBJ", $outputdsid = "JP2", $label = "Compressed jp2") {
    $result = -1;
    $this->log->lwrite("Function JP2 starting...", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
    try {
      $fedora_object = $this->fedora_connect->repository->getObject($pid);
      $this->log->lwrite("Fedora object successfully fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                } catch (Exception $ex) {
      $this->log->lwrite("Fedora object not fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      return -2;
                }
    if ($image = new Image($fedora_object, $dsid, NULL, $this->log, NULL)) {
      //$this->log->lwrite("Image derivative created", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
    }
    else {
      $this->log->lwrite("Derivative not created", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = -3;
    }
    $funcresult = $image->JP2($outputdsid, $label);
    if ($funcresult == 0) {
      $this->log->lwrite("JP2 function successful", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
      $result = 0;
    }
    else {
      $this->log->lwrite("JP2 function failed", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = $funcresult;
    }
    return $result;
  }

  function JP2_L($pid, $dsid = "OBJ", $outputdsid = "JP2", $label = "Compressed jp2") {
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
    }
    else {
      $this->log->lwrite("Derivative not created", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = -3;
    }
    $funcresult = $image->JP2($outputdsid, $label);
    if ($funcresult == 0) {
      $this->log->lwrite("JP2 function successful", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
      $result = 0;
    }
    else {
      $this->log->lwrite("JP2 function failed", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = $funcresult;
    }
    return $result;
  }

  function TN($pid, $dsid = "JPG", $outputdsid = "TN", $label = "Thumbnail", $height = 200, $width = 200) {
    $result = -1;
    $this->log->lwrite("Function TN starting...", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
    try {
      $fedora_object = $this->fedora_connect->repository->getObject($pid);
      $this->log->lwrite("Fedora object successfully fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
      } catch (Exception $ex) {
      $this->log->lwrite("Fedora object not fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      return -2;
                }
    if ($image = new Image($fedora_object, $dsid, NULL, $this->log, null)) {
      $this->log->lwrite("Image derivative created", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
    }
    else {
      $this->log->lwrite("Derivative not created", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = -3;
    }
    $funcresult = $image->TN($outputdsid, $label, $height, $width);
    //$this->log->lwrite("FunctionResult: " . $funcresult, 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
    if ($funcresult == 0) {
      $this->log->lwrite("TN function successful", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
      $result = 0;
    }
    else {
      $this->log->lwrite("TN function failed", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = $funcresult;
    }
    return $result;
  }

  function TN_L($pid, $dsid = "JPG", $outputdsid = "TN", $label = "Thumbnail", $height = 200, $width = 200) {
    $result = -1;
    print ("Function TN starting...");
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
    }
    else {
      $this->log->lwrite("Derivative not created", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = -3;
    }
    $funcresult = $image->TN($outputdsid, $label, $height, $width);
    if ($funcresult == 0) {
      $this->log->lwrite("TN function successful", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
      $result = 0;
    }
    else {
      $this->log->lwrite("TN function failed", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = $funcresult;
    }
    return $result;
  }

  function TECHMD($pid, $dsid = 'OBJ', $outputdsid = "TECHMD", $label = 'Technical metadata') {
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
    }
    else {
      $this->log->lwrite("Derivative not created", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = -3;
    }
    $funcresult = $tech->TECHMD($outputdsid, $label, $height, $width);
    if ($funcresult == 0) {
      $this->log->lwrite("TECHMD function successful", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
      $result = 0;
    }
    else {
      $this->log->lwrite("TECHMD function failed", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = $funcresult;
    }
    return $result;
  }

  function Scholar_PDFA($pid, $dsid = 'OBJ', $outputdsid = "PDF", $label = 'PDF') {
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
    }
    else {
      $this->log->lwrite("Derivative not created", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = -3;
    }
    $funcresult = $pdf->Scholar_PDFA($outputdsid, $label);
    if ($funcresult == 0) {
      $this->log->lwrite("Scholar_PDFA function successful", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
      $result = 0;
    }
    else {
      $this->log->lwrite("Scholar_PDFA function failed", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = $funcresult;
    }
    return $result;
  }

  function Scholar_PDFA_L($pid, $dsid = 'JPG', $outputdsid = "PDF", $label = 'PDF') {
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
    }
    else {
      $this->log->lwrite("Derivative not created", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = -3;
    }
    $funcresult = $pdf->Scholar_PDFA($outputdsid, $label);
    if ($funcresult == 0) {
      $this->log->lwrite("Scholar_PDFA function successful", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
      $result = 0;
    }
    else {
      $this->log->lwrite("Scholar_PDFA function failed", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = $funcresult;
    }
    return $result;
  }

  function AddImageDimensionsToRels($pid, $dsid = 'OBJ', $outputdsid = "POLICY", $label = 'RELS-INT') {
    $result = -1;
    $this->log->lwrite("Function AddImageDimensionsToRels starting...", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
    try {
      $fedora_object = $this->fedora_connect->repository->getObject($pid);
      $this->log->lwrite("Fedora object successfully fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
                } catch (Exception $ex) {
      $this->log->lwrite("Fedora object not fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      return -2;
                }
    if ($rels = new Relationship($fedora_object, $dsid, 'xml', $this->log, null)) {
      $this->log->lwrite("Relationship derivative created", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
    }
    else {
      $this->log->lwrite("Derivative not created", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = -3;
    }
    $funcresult = $rels->AddImageDimensionsToRels($outputdsid, $label, $height, $width);
    if ($funcresult == 0) {
      $this->log->lwrite("AddImageDimensionsToRels function successful", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
      $result = 0;
    }
    else {
      $this->log->lwrite("AddImageDimensionsToRels function failed", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = $funcresult;
    }
    return $result;
  }

  function Scholar_Policy($pid, $dsid = 'OBJ', $outputdsid = "POLICY", $label = "Embargo policy - Both") {
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
    }
    else {
      $this->log->lwrite("Derivative not created", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = -3;
    }
    $funcresult = $policy->Scholar_Policy($outputdsid, $label);
    if ($funcresult == 0) {
      $this->log->lwrite("Scholar_Policy function successful", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
      $result = 0;
    }
    else {
      $this->log->lwrite("Scholar_Policy function failed", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = $funcresult;
    }
    return $result;
  }

  function Scholar_Policy_L($pid, $dsid = 'OBJ', $outputdsid = "POLICY", $label = "Embargo policy - Both") {
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
    }
    else {
      $this->log->lwrite("Derivative not created", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = -3;
    }
    $funcresult = $policy->Scholar_Policy($outputdsid, $label);
    if ($funcresult == 0) {
      $this->log->lwrite("Scholar_Policy function successful", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
      $result = 0;
    }
    else {
      $this->log->lwrite("Scholar_Policy function failed", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = $funcresult;
    }
    return $result;
  }

}
?>

