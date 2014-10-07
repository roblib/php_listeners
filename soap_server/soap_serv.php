<?php

/**
 * @file this file is to provide soap services.
 */
/**
 * read a config file of soap to determin the location of microservices.
 */
define('MS_OBJECT_NOT_FOUND', -2);
define('MS_SERVICE_NOT_FOUND', -3);
$data = get_listener_config_data();
$location = $data['location'];

//add the home of the microservices listeners so we can call php files
//from non web accesible directorys.
set_include_path(get_include_path() . PATH_SEPARATOR . $location);

// requires
require_once 'SOAP/Server.php';
require_once 'FedoraConnect.php';
require_once 'tuque/Object.php';
require_once 'Logging.php';

/**
 * SOAP server object creation
 */
$soap = new SOAP_Server;
$service = new IslandoraService();
$service->create_service_map($soap);
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
 * reads the soap serv config file not the listeners config file.  Gets the 
 * location of the listeners and the list of soap services
 * @return array
 *   location is the location of the listeners
 *   services are a list of class to load
 */
function get_listener_config_data() {
  $config_file = file_get_contents('config.xml');
  $config_xml = new SimpleXMLElement($config_file);
  $location = $config_xml->path;
  if (empty($location)) {
    //try using a default
    $location = '/opt/php_listeners';
  }
  $services = $config_xml->services->service;
  return array('location' => $location, 'services' => $services);
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
  var $services;
  var $config_data;

  /**
   * Each of these I/O arrays must be defined specifically for individual 
   * services; as there does not seem to be an existing, unifying way of 
   * defining a generic WSDL, this seems to be the most solid way to expose 
   * the services. Each definition requires an 'in' and 'out' element, each 
   * specifying their own arrays of $pid, $dsid, $label, and any other 
   * parameter required by the service's fucntion.
   * 
   * The array is populated by a subclass
   * 
   *  
   * @var array 
   */
  var $__dispatch_map = array();

  /**
   * reads the listener path and loads the listeners config file so we can 
   * write to the listeners logs. 
   * 
   * construct of this class
   */
  function IslandoraService() {       
    $this->config_data = get_listener_config_data();
    $this->location = $this->config_data['location'];    
    $config_file = file_get_contents($this->location . '/config.xml');    
    try{
      $this->config = simplexml_load_string($config_file);
    } catch (Exception $e)
    {
      error_log("microservices soap server failed to open the listeners config file");
    }
    $this->services = $this->config_data['services'];
    $this->log = new Logging();
    $this->log->lfile($this->config->log->file);

  }

  /**
   * reads the config file for a list of services, instantiates each one 
   * and adds to the soap server.
   * 
   * @param Object $soap
   */
  function create_service_map($soap) {
    foreach ($this->services as $service) {
      require_once($service . '.php');
      if (class_exists($service)) {
        $c = (string) $service;        
        $object = new $c();
        $soap->addObjectMap($object, 'urn:php-islandora-soapservice');
      }
      else {
        $this->log->lwrite("Could not load class $service, check the config files list 
          of services to make sure they are correct", 'SOAP_SERVER', NULL, NULL, 'ERROR');
      }
    }
  }

  /**
   * reads the listeners config.xml file to see if authentication is required
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
        $this->log->lwrite("Could not load microservices user file", 'SOAP_SERVER', NULL, NULL, 'INFO');
        return FALSE;
      }
      $users = $microservice_users_xml->xpath("//user");
      if (empty($users)) {
        $this->log->lwrite("No Users found in microserices user file", 'SOAP_SERVER', NULL, NULL, 'INFO');
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
   * This funciton sanitizes error codes/status and writes to the log file. all
   * services should return 0 on success.  On failure they should return a
   * negative int
   *
   * @param string $funcname
   * @param mixed $funcresult
   * @param string $pid
   * @param string $dsid
   * @return int
   */
  function getFunctionStatus($funcname, $funcresult, $pid, $dsid) {
    $type = gettype($funcresult);
    if ($funcresult == MS_SUCCESS) {
      $this->log->lwrite($funcname . " function successful", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
      $result = MS_SUCCESS;
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
   * calls a service can be used by subclasses 
   * @param string $pid
   * @param string $dsid
   * @param string $outputdsid
   * @param string $label
   * @param array $params
   *   an associative array which must include a class and function keys, the 
   * values of these keys will determine what function gets called from 
   * what class.
   * the params array will be passed to the function called so it may
   * also include custom keys.
   * 
   * @return int
   *  0 for success 
   */
  protected function service($pid, $dsid, $outputdsid, $label, $params) {
    $class = $params['class'];
    $function = $params['function'];
    $result = MS_SYSTEM_EXCEPTION;
    $this->log->lwrite("Function $function starting...", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
    try {
      $fedora_object = $this->fedora_connect->repository->getObject($pid);
      $this->log->lwrite("Fedora object successfully fetched", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
    } catch (Exception $ex) {
      $this->log->lwrite("Fedora object not fetched Aborting", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      // We can't act on this object as it doesn't exist but we may need to return success so
      // Tavarna stops looping if it is configured to loop until it succeeds
      return MS_SUCCESS;
    }
    if ($service = new $class($fedora_object, $dsid, NULL, $this->log, null)) {
      $this->log->lwrite("$class class loaded", 'SOAP_LOG', $pid, $dsid, NULL, 'INFO');
    }
    else {
      $this->log->lwrite("Failed loading $class class", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      return MS_SERVICE_NOT_FOUND;
    }

    if(!method_exists($service, $function)){
      $this->log->lwrite("method not found - $function ", 'SOAP_LOG', $pid, $dsid, NULL, 'ERROR');
      return MS_SERVICE_NOT_FOUND;
    }

    $funcresult = $service->{$function}($outputdsid, $label, $params);
    $result = $this->getFunctionStatus($function, $funcresult, $pid, $dsid);

    return $result;
  }

}
?>

