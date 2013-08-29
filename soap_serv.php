<?php

/**
 * @file this file is to provide soap services.
 */
/**
 * read a config file of soap to determin the location of microservices.
 */
define('MS_OBJECT_NOT_FOUND', -2);
define('MS_SERVICE_NOT_FOUND', -3);
$location = get_listener_config_path();


set_include_path(get_include_path() . PATH_SEPARATOR . $location);

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
  if (isset($service->config->taverna->needAuth)) {
    if (!isset($_SERVER['PHP_AUTH_PW']) || !isset($_SERVER['PHP_AUTH_USER'])) {
      $service->log->lwrite("missing username or password, sending 401 unauthorized to Taverna", 'SOAP_SERVER', NULL, NULL, 'ERROR');
      header('WWW-Authenticate: Basic realm="php-islandora-soapservice');
      header('HTTP/1.0 401 Unauthorized');
      echo 'authentication required';
      exit;
    }
    if (!$service->authorize()) {
      $service->log->lwrite("User " . $_SERVER['PHP_AUTH_USER'] . " unauthorized with  " . $_SERVER['PHP_AUTH_PW'], "SOAP_SERVER", NULL, NULL, 'ERROR');
      header('WWW-Authenticate: Basic realm="php-islandora-services"');
      header('HTTP/1.0 403 Forbidden');
      echo 'authentication failed';
      exit;
    }
    $soap->service($HTTP_RAW_POST_DATA);
  }
  else {// auth not required
    $soap->service($HTTP_RAW_POST_DATA);
  }
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
 * reads the php listener config path.  first checks for a local 
 * config.xml file with an element path the for a environment varible.  if 
 * both fail it returns a default location
 * @return string
 *   the path to the php_listeners
 */
function get_listener_config_path() {
  $config_file = file_get_contents('config.xml');
  $config_xml = new SimpleXMLElement($config_file);
  $location = $config_xml->path;
  if (empty($location)) {
    $location_env_variable = 'PHP_LISTENERS_PATH';
    $location = getenv($location_env_variable);
    if (empty($location)) {
      //try using a default
      $location = '/opt/php_listeners';
    }
  }
  return $location;
}

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
  var $location;

  /**
   * Each of these I/O arrays must be defined specifically for individual 
   * services; as there does not seem to be an existing, unifying way of 
   * defining a generic WSDL, this seems to be the most solid way to expose 
   * the services. Each definition requires an 'in' and 'out' element, each 
   * specifying their own arrays of $pid, $dsid, $label, and any other 
   * parameter required by the service's fucntion.
   * 
   *  
   * @var array Description__dispatch_map
   */
  var $__dispatch_map = array();

  /**
   * construct of this class
   */
  function IslandoraService() {
    //documentation must mention the need to set the php listener path
    $this->location = get_listener_config_path();
    $config_file = file_get_contents($this->location . '/config.xml');
    try{
      $this->config = new SimpleXMLElement($config_file);
    } catch (Exception $e)
    {
      print("fail to open the config file");
    }

    $this->log = new Logging();
    $this->log->lfile($this->config->log->file);
    $this->connect();
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
     * <b>ocr</b> processing in Text.php
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
     * <b>hOcr</b> processing in Text.php
     */
    $this->__dispatch_map['hOcr'] = array(
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
     * <b>pdf</b> processing in Pdf.php
     */
    $this->__dispatch_map['pdf'] = array(
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
  }

  /**
   * reads the config.xml file to see if authentication is required
   * @return boolean TRUE if authentication is required
   */
  function auth_required() {
    $authinconfig = $this->config->taverna->needAuth;
    if (strcasecmp($authinconfig, 'false') == 0) {
      return FALSE; //no authorization required so we are authorized
    }
    return TRUE;
  }

  /**
   * checks the micoroservices user file for a password and username that
   * match the PHP_AUTH_USER and PHP_AUTH_PW php $_SERVER variables
   * 
   * @return boolean TRUE if authentication is successfull FALSE otherwise
   */
  function authorize() {
    if (!$this->auth_required()) {
      return TRUE; //we are authorized as authorization is not required
    }
    else {
      $microservice_users_file = $this->location . '/microservice_users.xml';
      $microservice_users_xml = simplexml_load_file($microservice_users_file);
      if (empty($microservice_users_xml)) {
        $service->log->lwrite("Could not load microservices user file", 'SOAP_SERVER', NULL, NULL, 'INFO');
        return FALSE;
      }
      $users = $microservice_users_xml->xpath("//user");
      if (empty($users)) {
        $service->log->lwrite("No Users found in microserices user file", 'SOAP_SERVER', NULL, NULL, 'INFO');
        return FALSE;
      }
      foreach ($users as $user) {
        if (strcmp($_SERVER['PHP_AUTH_USER'], $user['username']) == 0 && strcmp($_SERVER['PHP_AUTH_PW'], $user['password']) == 0) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * Sets up the connection to the fedora repository
   */
  function connect() {
    $fedora_user = new stdClass();
    $fedora_user->name = $this->config->fedora->username;
    $fedora_user->pass = $this->config->fedora->password;
    $this->fedora_connect = new FedoraConnection($fedora_user, $this->config->fedora->protocol . '://' . $this->config->fedora->host . ':' . $this->config->fedora->port . '/fedora');
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
      $fedora_user = new stdClass();
      $fedora_user->name = $this->config->fedora->username;
      $fedora_user->pass = $this->config->fedora->password;
      if (fedora_object_exists($this->config->fedora->protocol . '://' . $this->config->fedora->host . ':' . $this->config->fedora->port . '/fedora', $fedora_user, $pid)) {
        $content = $this->fedora_connect->getDatastream($pid, $dsid)->content;
        return base64_encode($content);
      }
    } catch (Exception $e) {
      $this->log->lwrite("An error occurred creating the fedora object", 'FAIL_OBJECT', $pid, $e->getMessage(), 'ERROR');
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
   * This funciton is to write the function excute status to the log file all 
   * services should return 0 on success.  On failure they should return a 
   * negative int
   * 
   * @param string $funcname
   * @param mixed $funcresult
   * @return int
   */
  function getFunctionStatus($funcname, $funcresult, $pid, $dsid) {
    $type = gettype($funcresult);
    if ($funcresult == MS_SUCCESS) {
      $this->log->lwrite($funcname . " function successful", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
      $result = 0;
    }
    else {
      $this->log->lwrite($funcname . " function failed with code $funcresult", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      if ($type == 'integer') {
        $result = $funcresult;
      }
      else {
        $result = MS_SYSTEM_EXCEPTION; //make sure we return an int;
      }
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
    $params = array('class' => 'Text', 'function' => 'allOcr', 'language' => $language);
    return $this->service($pid, $dsid, $outputdsid, $label, $params);
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
    $params = array('class' => 'Text', 'function' => 'ocr', 'language' => $language);
    return $this->service($pid, $dsid, $outputdsid, $label, $params);
    
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
    $params = array('class' => 'Text', 'function' => 'hOcr', 'language' => $language);
    return $this->service($pid, $dsid, $outputdsid, $label, $params);
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
    $params = array('class' => 'Text', 'function' => 'encoded', 'language' => $language);
    return $this->service($pid, $dsid, $outputdsid, $label, $params);
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
    $params = array('class' => 'Image', 'function' => 'jpg','resize' => $resize);
    return $this->service($pid, $dsid, $outputdsid, $label, $params);
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
    $params = array('class' => 'Image', 'function' => 'jp2');
    return $this->service($pid, $dsid, $outputdsid, $label, $params);
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
    $params = array('class' => 'Image', 'function' => 'tn', 'height'=>$height, 'width' => $width);
    return $this->service($pid, $dsid, $outputdsid, $label, $params);
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
    $params = array('class' => 'Technical', 'function' => 'techmd');
    return $this->service($pid, $dsid, $outputdsid, $label, $params);
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
    $params = array('class' => 'Pdf', 'function' => 'scholarPdfa');
    return $this->service($pid, $dsid, $outputdsid, $label, $params);
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
    $params = array('class' => 'Relationship', 'function' => 'addImageDimensionsToRels');
    return $this->service($pid, $dsid, $outputdsid, $label, $params);
  }

  /**
   * This file will call pdf command Pdf.php
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
  function pdf($pid, $dsid = 'OBJ', $outputdsid = "PDF", $label = 'PDF') {
    $params = array('class' => 'Pdf', 'function' => 'toPdf');
    return $this->service($pid, $dsid, $outputdsid, $label, $params);
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
    $params = array('class' => 'Scholar', 'function' => 'scholarPolicy');
    return $this->service($pid, $dsid, $outputdsid, $label, $params);
  }
  
  private function service($pid, $dsid, $outputdsid, $label, $params){
    $class = $params['class'];
    $function = $params['function'];
    $result = MS_SYSTEM_EXCEPTION;
    $this->log->lwrite("Function $function starting...", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');

    try {
      $fedora_object = $this->fedora_connect->repository->getObject($pid);
      $this->log->lwrite("Fedora object successfully fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
    } catch (Exception $ex) {
      $this->log->lwrite("Fedora object not fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      return MS_OBJECT_NOT_FOUND;
    }
    if ($service = new $class($fedora_object, $dsid, NULL, $this->log, null)) {
      $this->log->lwrite("$class class loaded", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
    }
    else {
      $this->log->lwrite("Failed loading $class class", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      return MS_SERVICE_NOT_FOUND;
    }

    $funcresult = $service->{$function}($outputdsid, $label, $params);
    $result = $this->getFunctionStatus($function, $funcresult, $pid, $dsid);

    return $result;
  }


}
?>

