<?php

/**
 * @file this file is to provide soap services.
 */
error_reporting(E_ALL ^ (E_DEPRECATED | E_NOTICE));

/**
 * read a config file of soap to determin the location of microservices.
 */
$location = get_listener_config_path();

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

/**
 * SOAP server object creation
 */
$soap = new SOAP_Server;
$service = new IslandoraService();
$soap->addObjectMap($service, 'urn:php-islandora-soapservice');

/**
 * SOAP server creation:
 * Supports http authentication
 */
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
  $disco = new SOAP_DISCO_Server($soap, 'DiscoServer');
  if (isset($_SERVER['QUERY_STRING']) &&
      strpos($_SERVER['QUERY_STRING'], 'wsdl') === 0) {
    header('Content-type: text/xml');
    echo $disco->getWSDL();
  }
}

/**
 * reads the php listener from an environment varible.  if the variable is not
 * set it returns a default location
 * @return string 
 *   the path to the php_listeners
 */
function get_listener_config_path() {
  $location_env_veriable = 'PHP_LISTENERS_PATH';
  $location = getenv($location_env_veriable);
  if (empty($location)) {
    //try using a default
    $location = '/opt/php_listeners';
  }
  return $location;
}

//Command line tests:
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

/**
 * This class describes all microservices/WSDLs, logging, and handles the 
 * connection to an arbitrary fedora repository (specified in config.xml). The 
 * __dispatch_map methods describe the WSDL inputs and outputs accessible from 
 * Taverna workbench as well as to external users(once given the proper 
 * user/pass for authentication). 
 */
class IslandoraService {

  var $config;
  var $log;
  var $fedora_connect;

  /**
   * Each of these I/O arrays must be defined specifically for individual 
   * services; as there does not seem to be an existing, unifying way of 
   * defining a generic WSDL, this seems to be the most solid way to expose 
   * the services. Each definition requires an 'in' and 'out' element, each 
   * specifying their own arrays of $pid, $dsid, $label, and any other 
   * parameter required by the service's fucntion.
   * 
   * Defining, _L are called from listener with pid only, others can be called
   * from workflow.
   * 
   * @var array Description__dispatch_map
   */
  var $__dispatch_map = array();

  /**
   * construct of this class
   */
  function IslandoraService() {
    $location = get_listener_config_path();
    $config_file = file_get_contents($location . '/config.xml');
    try {
      $this->config = new SimpleXMLElement($config_file);
        } catch (Exception $e) {
      print("fail to open the config file");
        }

    $this->log = new Logging();
    $this->log->lfile($this->config->log->file);
    $this->connect();

    /**
     * <b>jpg</b> processing in Image.php
     */
    $this->__dispatch_map['jpg'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string',
        'outputdsid' => 'string',
        'label' => 'string',
        'resize' => 'int'
      ),
      'out' => array('exit_status' => 'int')
    );

    /**
     * <b>tn</b> processing Thumbnail in Image.php
     */
    $this->__dispatch_map['tn'] = array(
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

    /**
     * <b>jp2</b> processing in Image.php
     */
    $this->__dispatch_map['jp2'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string',
        'outputdsid' => 'string',
        'label' => 'string'
      ),
      'out' => array('exit_status' => 'int')
    );

    /**
     * <b>allOcr</b> processing in Text.php 
     */
    $this->__dispatch_map['allOcr'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string',
        'outputdsid' => 'string',
        'label' => 'string',
        'language' => 'string'
      ),
      'out' => array('exit_status' => 'int')
    );

    /**
     * <b>ocr</b> processing in Image.php
     */
    $this->__dispatch_map['ocr'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string',
        'outputdsid' => 'string',
        'label' => 'string',
        'language' => 'string'
      ),
      'out' => array('exit_status' => 'int')
    );

    /**
     * <b>encodedOcr</b> processing in Image.php
     */
    $this->__dispatch_map['encodedOcr'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string',
        'outputdsid' => 'string',
        'label' => 'string',
        'language' => 'string'
      ),
      'out' => array('exit_status' => 'int')
    );

    /**
     * <b>techmd</b> processing in Technical.php
     */
    $this->__dispatch_map['techmd'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string',
        'outputdsid' => 'string',
        'label' => 'string'
      ),
      'out' => array('exit_status' => 'int')
    );

    /**
     * <b>scholarPdfa</b> processing in Pdf.php
     */
    $this->__dispatch_map['scholarPdfa'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string',
        'outputdsid' => 'string',
        'label' => 'string'
      ),
      'out' => array('exit_status' => 'int')
    );

    /**
     * <b>addImageDimensionsToRels</b> processing in Relationships.php
     */
    $this->__dispatch_map['addImageDimensionsToRels'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string',
        'outputdsid' => 'string',
        'label' => 'string'
      ),
      'out' => array('exit_status' => 'int')
    );

    /**
     * <b>scholarPolicy</b> processing in Scholar.php
     */
    $this->__dispatch_map['scholarPolicy'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string',
        'outputdsid' => 'string',
        'label' => 'string'
      ),
      'out' => array('exit_status' => 'int')
    );

    /**
     * <b>read</b> read fedora objects
     */
    $this->__dispatch_map['read'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string',
        'extension' => 'string'
      ),
      'out' => array('base64_content' => 'string')
    );

    /**
     * <b>write</b> write back to fedora
     */
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

  /**
   * Sets up the connection to the fedora repository
   */
  function connect() {
    $fedora_user = new stdClass();
    $fedora_user->name = $this->config->fedora->username;
    $fedora_user->pass = $this->config->fedora->password;
    $this->fedora_connect = new FedoraConnection($fedora_user, 'http://' . $this->config->fedora->host . ':' . $this->config->fedora->port . '/fedora');
  }

  /**
   * Used by SOAP server - Exposes WSDLs
   * 
   * @param string $method
   *  Name of the method
   * 
   * @return array
   *  The single array in __dispath_map array
   */
  function __dispatch($method) {
    if (isset($this->__dispatch_map[$method])) {
      return $this->__dispatch_map[$method];
    }
    else {
      return null;
    }
  }

  /**
   * Reads a fedora object from an external repository
   * 
   * @param string $pid
   * The object's pid
   * 
   * @param string $dsid
   * The object's dsID
   * 
   * @param string $extension
   *  
   */
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

  /**
   * Writes a fedora object back to the repository
   * 
   * @param string $pid
   *  The pid of fedora object to write back
   * 
   * @param string $dsid
   *  The dsid of fedora object to write back
   * 
   * @param string $label
   *  The object's label
   * 
   * @param string $base64_content
   *  The content of object that base 64
   * 
   * @param string $mimetype
   *  The mime type of object
   * 
   * @return string
   */
  function write($pid, $dsid, $label, $base64_content, $mimetype) {
    return $this->fedora_connect->addDerivative($pid, $dsid, $label, base64_decode($base64_content), $mimetype, null, true, false);
  }


  /**
   * This fonciton is to write the function excute status to the log file
   * 
   * @param string $funcname
   * @param string $funcresult
   * @return int
   */
  function getFunctionStatus($funcname, $funcresult) {
    if ($funcresult == 0) {
      $this->log->lwrite($funcname . " function successful", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
      $result = 0;
    }
    else {
      $this->log->lwrite($funcname . " function failed", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = $funcresult;
    }
    return $result;
  }

  /**
   * This function is processing all type of the ocr files
   * 
   * @param string $pid
   *  The pID of fedora Object which to read and write
   * 
   * @param string $dsid
   *  The dsid of fedora Object which to read 
   * 
   * @param string $outputdsid
   *  The dsid of fedora Object to write back
   * 
   * @param string $label
   *  The label of fedora Object to write 
   * 
   * @param string $language
   *  The language of ocr
   * 
   * @return int
   */
  function allOcr($pid, $dsid = 'JPEG', $outputdsid = 'HOCR', $label = 'HOCR', $language = 'eng') {
    $result = -1;
    $this->log->lwrite("Function AllOCR starting...", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
    try {
      $fedora_object = $this->fedora_connect->repository->getObject($pid);
      $this->log->lwrite("Fedora object successfully fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
        } catch (Exception $ex) {
      $this->log->lwrite("Fedora object not fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = -2;
        }
    

    if ($text = new Text($fedora_object, $dsid, 'jpeg', $this->log, null)) {
      $this->log->lwrite("Text derivative created", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
    }
    else {
      $this->log->lwrite("Derivative not created", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = -3;
    }
    $funcresult = $text->allOcr($outputdsid, $label, $language);
    $result = $this->getFunctionStatus("allOcr", $funcresult);
    return $result;
  }

  /**
   * This function is processing the ocr files
   * 
   * @param string $pid
   *  The pID of fedora Object which to read and write
   * 
   * @param string $dsid
   *  The dsid of fedora Object which to read 
   * 
   * @param string $outputdsid
   *  The dsid of fedora Object to write back
   * 
   * @param string $label
   *  The label of fedora Object to write 
   * 
   * @param string $language
   *  The language of ocr
   * 
   * @return int
   */
  function ocr($pid, $dsid = 'OCR', $outputdsid = 'OCR', $label = 'Scanned text', $language = 'eng') {
     $result = -1;
     $this->log->lwrite("Function ocr starting...", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
    try {
      $fedora_object = $this->fedora_connect->repository->getObject($pid);
      $this->log->lwrite("Fedora object successfully fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
        } catch (Exception $ex) {
      $this->log->lwrite("Fedora object not fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = -2;
        }
    
   

    if ($text = new Text($fedora_object, $dsid, 'txt', $this->log, null)) {
      $this->log->lwrite("Text derivative created", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
    }
    else {
      $this->log->lwrite("Derivative not created", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = -3;
    }

    $funcresult = $text->ocr($outputdsid, $label, $language);
    $result = $this->getFunctionStatus("ocr", $funcresult);
    return $result;
  }

  /**
   * This function is processing all type of the ocr files
   * 
   * @param string $pid
   *  The pID of fedora Object which to read and write
   * 
   * @param string $dsid
   *  The dsid of fedora Object which to read 
   * 
   * @param string $outputdsid
   *  The dsid of fedora Object to write back
   * 
   * @param string $label
   *  The label of fedora Object to write 
   * 
   * @param string $language
   *  The language of ocr
   * 
   * @return int
   */
  function hOcr($pid, $dsid = 'JPEG', $outputdsid = 'HOCR', $label = 'HOCR', $language = 'eng') {
    $result = -1;
    $this->log->lwrite("Function HOCR starting...", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
    try {
      $fedora_object = $this->fedora_connect->repository->getObject($pid);
      $this->log->lwrite("Fedora object successfully fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
        } catch (Exception $ex) {
      $this->log->lwrite("Fedora object not fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = -2;
        }
    

    if ($text = new Text($fedora_object, $dsid, 'jpg', $this->log, null)) {
      $this->log->lwrite("Text derivative created", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
    }
    else {
      $this->log->lwrite("Derivative not created", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = -3;
    }

    $funcresult = $text->hOcr($outputdsid, $label, $language);
    $result = $this->getFunctionStatus("hOcr", $funcresult);
    return $result;
  }

  /**
   * This function is processing all type of the ocr files
   * 
   * @param string $pid
   *  The pID of fedora Object which to read and write
   * 
   * @param string $dsid
   *  The dsid of fedora Object which to read 
   * 
   * @param string $outputdsid
   *  The dsid of fedora Object to write back
   * 
   * @param string $label
   *  The label of fedora Object to write 
   * 
   * @param string $language
   *  The language of ocr
   * 
   * @return int
   */
  function encodedOcr($pid, $dsid = 'JPEG', $outputdsid = 'ENCODED_OCR', $label = 'Encoded OCR', $language = 'eng') {
        $result = -1;
        $this->log->lwrite("Function encodedOCR starting...", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
    try {
      $fedora_object = $this->fedora_connect->repository->getObject($pid);
      $this->log->lwrite("Fedora object successfully fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
        } catch (Exception $ex) {
      $this->log->lwrite("Fedora object not fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = -2;
        }
    if ($text = new Text($fedora_object, $dsid, 'txt', $this->log, null)) {
      $this->log->lwrite("Text derivative created", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
    }
    else {
      $this->log->lwrite("Derivative not created", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = -3;
    }
    $funcresult = $text->encodedOcr($outputdsid, $label, $language);
    $result = $this->getFunctionStatus("encodedOcr", $funcresult);
    return $result;
  }

  /**
   * This file is to process JPG files
   * 
   * @param string $pid
   *  The pID of fedora Object which to read and write
   * 
   * @param string $dsid
   *  The dsid of fedora Object which to read 
   * 
   * @param string $outputdsid
   *  The dsid of fedora Object to write back
   * 
   * @param string $label
   *  The label of fedora Object to write 
   * 
   * @param string $resize
   *  The new size of jpg
   *  
   * @return int
   */
  function jpg($pid, $dsid = "JPEG", $outputdsid = "JPG", $label = "JPEG image", $resize = "800") {
    $fedora_object = $this->fedora_connect->repository->getObject($pid);
    $image = new Image($fedora_object, $dsid, 'jpg', $this->log, null);
    return $image->jpg($outputdsid, $label, $resize);
  }

  /**
   * This file is to process JP2 files
   * 
   * @param string $pid
   *  The pID of fedora Object which to read and write
   * 
   * @param string $dsid
   *  The dsid of fedora Object which to read 
   * 
   * @param string $outputdsid
   *  The dsid of fedora Object to write back
   * 
   * @param string $label
   *  The label of fedora Object to write 
   * 
   * @return type
   */
  function jp2($pid, $dsid = "OBJ", $outputdsid = "JP2", $label = "Compressed jp2") {
        $result = -1;
        $this->log->lwrite("Function TN starting...", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
    try {
      $fedora_object = $this->fedora_connect->repository->getObject($pid);
      $this->log->lwrite("Fedora object successfully fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
        } catch (Exception $ex) {
      $this->log->lwrite("Fedora object not fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = -2;
        }

    if ($image = new Image($fedora_object, $dsid, 'jp2', $this->log, null)) {
      $this->log->lwrite("Image derivative created", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
    }
    else {
      $this->log->lwrite("Derivative not created", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = -3;
    }
    $funcresult = $image->jp2($outputdsid, $label);
    $result = $this->getFunctionStatus("jp2", $funcresult);
    return $result;
  }

  /**
   * This is function is to process thumbnail.
   * 
   * @param string $pid
   *  The pID of fedora Object which to read and write
   * 
   * @param string $dsid
   *  The dsid of fedora Object which to read 
   * 
   * @param string $outputdsid
   *  The dsid of fedora Object to write back
   * 
   * @param string $label
   *  The label of fedora Object to write 
   * 
   * @param type $height
   *  The thumbnails' hight
   * 
   * @param type $width
   *  The thumbnails' width
   * 
   * @return int
   */
  function tn($pid, $dsid = "JPG", $outputdsid = "TN", $label = "Thumbnail", $height = 200, $width = 200) {
        $result = -1;
        $this->log->lwrite("Function TN starting...", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
    try {
      $fedora_object = $this->fedora_connect->repository->getObject($pid);
      $this->log->lwrite("Fedora object successfully fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
        } catch (Exception $ex) {
      $this->log->lwrite("Fedora object not fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = -2;
        }
    
    if ($image = new Image($fedora_object, $dsid, 'jpg', $this->log, null)) {
      $this->log->lwrite("Image derivative created", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
    }
    else {
      $this->log->lwrite("Derivative not created", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = -3;
    }
    $funcresult = $image->tn($outputdsid, $label, $height, $width);
    $result = $this->getFunctionStatus("tn", $funcresult);
    return $result;
  }

  /**
   * This file will call commend in Technical.php
   * 
   * @param string $pid
   *  The pID of fedora Object which to read and write
   * 
   * @param string $dsid
   *  The dsid of fedora Object which to read 
   * 
   * @param string $outputdsid
   *  The dsid of fedora Object to write back
   * 
   * @param string $label
   *  The label of fedora Object to write 
   *
   *  @return int
   */
  function techmd($pid, $dsid = 'OBJ', $outputdsid = "TECHMD", $label = 'Technical metadata') {
     $result = -1;
        $this->log->lwrite("Function techmd starting...", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
    try {
      $fedora_object = $this->fedora_connect->repository->getObject($pid);
      $this->log->lwrite("Fedora object successfully fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
        } catch (Exception $ex) {
      $this->log->lwrite("Fedora object not fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = -2;
        }
    if ($tech = new Technical($fedora_object, $dsid, 'xml', $this->log, null)) {
      $this->log->lwrite("Technical metadata derivative created", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
    }
    else {
      $this->log->lwrite("Derivative not created", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = -3;
    }
    $funcresult = $tech->techmd($outputdsid, $label, $height, $width);
    $result = $this->getFunctionStatus("techmd", $funcresult);
    return $result;
  }

  /**
   * This function is to call JODconverter service
   * 
   * @param string $pid
   *  The pID of fedora Object which to read and write
   * 
   * @param string $dsid
   *  The dsid of fedora Object which to read 
   * 
   * @param string $outputdsid
   *  The dsid of fedora Object to write back
   * 
   * @param string $label
   *  The label of fedora Object to write 
   * 
   * @return int
   */
  function scholarPdfa($pid, $dsid = 'OBJ', $outputdsid = "PDF", $label = 'PDF') {
     $result = -1;
        $this->log->lwrite("Function scholar starting...", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
    try {
      $fedora_object = $this->fedora_connect->repository->getObject($pid);
      $this->log->lwrite("Fedora object successfully fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
        } catch (Exception $ex) {
      $this->log->lwrite("Fedora object not fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = -2;
        }
    if ($pdf = new Pdf($fedora_object, $dsid, 'jpg', $this->log, null)) {
      $this->log->lwrite("Pdf derivative created", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
    }
    else {
      $this->log->lwrite("Derivative not created", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = -3;
    }
    $funcresult = $pdf->scholarPdfa($outputdsid, $label);
    $result = $this->getFunctionStatus("scholarPdfa", $funcresult);
    return $result;
  }

  /**
   * This function is to add image dimendions to the relationships.
   * 
   * @param string $pid
   *  The pID of fedora Object which to read and write
   * 
   * @param string $dsid
   *  The dsID of fedora Object which to read 
   * 
   * @param string $outputdsid
   *  The dsID of fedora Object to write back
   * 
   * @param string $label
   *  The label of fedora Object to write 
   * 
   * @return int
   */
  function addImageDimensionsToRels($pid, $dsid = 'OBJ', $outputdsid = "POLICY", $label = 'RELS-INT') {
    $ $result = -1;
        $this->log->lwrite("Function addImagedimensionsToRels starting...", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
    try {
      $fedora_object = $this->fedora_connect->repository->getObject($pid);
      $this->log->lwrite("Fedora object successfully fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
        } catch (Exception $ex) {
      $this->log->lwrite("Fedora object not fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = -2;
        }
    if ($rels = new Relationship($fedora_object, $dsid, 'xml', $this->log, null)) {
      $this->log->lwrite("Relationship derivative created", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
    }
    else {
      $this->log->lwrite("Derivative not created", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = -3;
    }
    $funcresult = $rels->addImageDimensionsToRels($outputdsid, $label, $height, $width);
    $result = $this->getFunctionStatus("encodedOcr", $funcresult);
    return $result;
  }

  /**
   * This function is to add scholar policy to derivates.
   * 
   * @param string $pid
   *  The pID of fedora Object which to read and write
   * 
   * @param string $dsid
   *  The dsID of fedora Object which to read 
   * 
   * @param string $outputdsid
   *  The dsID of fedora Object to write back
   * 
   * @param string $label
   *  The label of fedora Object to write 
   * 
   * @return type
   */
  function scholarPolicy($pid, $dsid = 'OBJ', $outputdsid = "POLICY", $label = "Embargo policy - Both") {
     $result = -1;
        $this->log->lwrite("Function scholarPolicy starting...", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
    try {
      $fedora_object = $this->fedora_connect->repository->getObject($pid);
      $this->log->lwrite("Fedora object successfully fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
        } catch (Exception $ex) {
      $this->log->lwrite("Fedora object not fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = -2;
        }
    if ($policy = new Scholar($fedora_object, $dsid, 'xml', $this->log, null)) {
      $this->log->lwrite("Policy derivative created", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
    }
    else {
      $this->log->lwrite("Derivative not created", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      $result = -3;
    }
    $funcresult = $policy->scholarPolicy($outputdsid, $label);
    $result = $this->getFunctionStatus("encodedOcr", $funcresult);
    return $result;
  }

}
?>

