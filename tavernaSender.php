<?php

/**
 * @file
 * This file defines the class to sent messages to taverna server
 */
require_once 'sender.php';
require_once 'TavernaException.php';
require_once 'TavernaCurlConnection.php';

/**
 * This class defines a sender to sent message to taverna
 */
class TavernaSender extends Sender {

  /**
   * the curl connection which is to sent messages using curl.
   * @var type TavernaCurlConnection
   */
  public $curl_connect;

  /**
   * the option that if verif host and peer
   * @var boolean
   */
  public $ssl_status;
  public $config;

  /**
   * Constructor for the sender.
   * 
   * @param string $protocal
   *  The protocal for the taverna server. 
   *  For example: https
   * 
   * @param string $hostname
   *  The domain name or ip address of taverna sender.
   *  For example: 255.255.255.255
   * 
   * @param string $port
   *  The port that taverna send used.
   *  For example: 8443
   * 
   * @param type $context
   *  The context of taverna server
   *  For example : taverna-server
   * 
   * @param type $username
   *  The username for taverna server
   * 
   * @param type $password 
   *  The password for tavernaserver
   */
  function __construct($protocol, $hostname = null, $port = null, $context, $username = null, $password = null) {
    parent::__construct($protocol . '://' . $hostname . ":" . $port . '/' . $context . "/rest/runs/", $password, $username);
    $this->curl_connect = new TavernaCurlConnection();
    $this->curl_connect->username = $this->username;
    $this->curl_connect->password = $this->password;
    $this->set_ssl_status();
    $this->set_ssl();
  }

  /**
   * This method is to read ssl status from config file
   */
  function set_ssl_status() {
    $location = $this->get_listener_config_path();
    $config_file = file_get_contents($location . '/config.xml');
    try{
      $this->config = new SimpleXMLElement($config_file);
    } catch (Exception $e)
    {
      print("fail to open the config file");
    }

    if (strcasecmp($this->config->taverna->verify_ssl, 'true') == 0) {
      $this->ssl_status = TRUE;
    }
    else {
      $this->ssl_status = FALSE;
    }
  }

  /**
   * This function is to get config file location
   * @return string
   */
  function get_listener_config_path() {
    $location_env_variable = 'PHP_LISTENERS_PATH';
    $location = getenv($location_env_variable);
    if (empty($location)) {
      //try using a default
      $location = '/opt/php_listeners';
    }
    return $location;
  }

  /**
   * This function is to set ssl verify to false
   */
  function set_ssl() {
    $this->curl_connect->sslVersion = 3;
    $this->curl_connect->verifyHost = $this->ssl_status;
    $this->curl_connect->verifyPeer = $this->ssl_status;
  }

  /**
   * This function is to sent t2flow file to taverna server.
   * 
   * @param string $message
   *  T2flow file content
   * 
   * @return string 
   *  information that taverna sender sent back. include headers and content.
   *  Default: null 
   * 
   * @throws TavernaException
   */
  function send_Message($message) {
    if (!empty($message)) {
      $response = $this->curl_connect->postRequest($this->hostname, 'string', $message, 'application/vnd.taverna.t2flow+xml');
      if ($response['status'] != 201) {
        throw new TavernaException($response['headers'] . $response['content'], $response['status'], 'send message');
      }

      return $response['headers'] . $response['content'];
    }
    return null;
  }

  /**
   * This function is to parse uuid from the feedback message.
   * There must have full url of the t2flow on taverna server.
   * For exampe: 
   * https://p1.vre.upei.ca:8443/taverna-server/rest/runs/8fc60906-d2f9-41f8-8
   * ffe-f9824fa7068a/
   * 
   * @param string $message
   *  Message that taverna sender feedback
   * 
   * @return string 
   *  The uuid of t2flow on taverna sender.
   *  Default: null
   */
  function parse_UUID($message) {
    if (!empty($message)) {
      $uuid = substr($message, strpos($message, $this->hostname) + strlen($this->hostname), 36);
      return $uuid;
    }
    return null;
  }

  /**
   * This function is to run t2flow on taverna server
   * 
   * @param sting $uuid
   *  The uuid of t2flow which should run on taverna server
   * 
   * @return string $response
   *  The message that taverna server feed back. It includes headers and 
   *  content
   *  
   * @throws TavernaException
   */
  function run_t2flow($uuid) {
    $auth = $this->check_credentials($uuid);
    if (empty($uuid) || !$auth) {
      throw new TavernaException("Error running t2flow, missing uuid or auth failure");
    }

    $url = $this->hostname . $uuid . '/status/';
    $response = $this->curl_connect->tavernaPutRequest($url, 'string', 'Operating', 'text/plain');
    if ($response['status'] != 200) {
      throw new TavernaException($response['headers'] . $response['content'], $response['status'], 'run t2flow');
    }
    return $response['headers'] . $response['content'];
  }

  /**
   * Get status of t2flow from taverna server
   * 
   * @param string $uuid 
   *  The $uuid of t2flow on taverna server
   * 
   * @return string 
   *  Message that taverna server feedback inculdes headers and content.
   *  Status will be shown on the content part.
   *  Status: Initialized, Operating, Stopped, Finished
   * 
   * @throws TavernaException
   */
  function get_status($uuid) {
    if (!empty($uuid)) {
      $url = $this->hostname . $uuid . "/status/";
      $response = $this->curl_connect->getRequest($url, FALSE, NULL);
      if ($response['status'] != 200) {
        throw new TavernaException($response['headers'] . $response['content'], $response['status'], 'get t2flow status');
      }
      return $response['content'];
    }
    return null;
  }

  /**
   * Delete t2flow file from taverna server
   * 
   * @param string $uuid 
   *  The $uuid of t2flow on taverna server
   * 
   * @return string 
   *  Message that taverna server feedback inculdes headers and content.
   * 
   * @throws TavernaException
   */
  function delete_t2flow($uuid) {
    if (!empty($uuid)) {
      $url = $this->hostname . $uuid . '/';
      $response = $this->curl_connect->deleteRequest($url);

      if ($response['status'] != 204) {
        throw new TavernaException($response['headers'] . $response['content'], $response['status'], 'delete t2flow');
      }
      return $response['status'];
    }
    return null;
  }
  
  
  /**
   * conveince function to delete all orphaned workflows
   */
  function delete_all_t2flows(){
    $url = $this->hostname . 'rest/runs/';
    $response = $this->curl_connect->getRequest($url);
    $xml = $response['content'];
    $sxml = new SimpleXml($xml);
    foreach($sxml->runList->children() as $run){
      $this->delete_t2flow((string)$run);
    }
  }

  /**
   * This function is to add inputs in t2flow on taverna server.
   * 
   * @param string $uuid
   *  The uuid of t2flow on taverna server.
   * 
   * @param string $key
   *  The name of input.
   * 
   * @param string $value
   *  The value of input
   * 
   * @return string 
   *  Message that taverna server feedback inculdes headers and content.
   * 
   * @throws TavernaException
   */
  function add_input($uuid, $key, $value) {
    if (!empty($uuid) && !empty($key) && !empty($value)) {
      $input = '<t2sr:runInput xmlns:t2sr="http://ns.taverna.org.uk/2010/xml/server/rest/">
                        <t2sr:value>' . $value . '</t2sr:value>             
                      </t2sr:runInput>';
      $url = $this->hostname . $uuid . "/input/input/" . $key;
      $response = $this->curl_connect->tavernaPutRequest($url, 'string', $input, 'application/xml');

      if ($response['status'] != 200) {
        throw new TavernaException($response['headers'] . $response['content'], $response['status'], 'add input');
      }

      return $response['headers'] . $response['content'];
    }
    return null;
  }

  /**
   * checks if we want Taverna to send credentials to the services.
   * if we do then it calls send_credentials to send the required creds
   * 
   * @param string $uuid
   * @return boolean
   * returns true if the credentials have been successfully sent.  also returns
   * true if credentials are not required
   */
  function check_credentials($uuid) {
    $location = $this->get_listener_config_path();
    $config_file = file_get_contents($location . '/config.xml');
    $config = NULL;
    try{
      $config = new SimpleXMLElement($config_file);
    } catch (Exception $e) {
      throw new TavernaException('Error parsing config.xml ' . $e->getMessage());
    }
    $needAuth = strcasecmp('false', $config->taverna->needAuth) == 0 ? FALSE : TRUE;
    $return = $needAuth ? $this->send_credentials() : TRUE;
    return $return;
  }

  /**
   * sends a username and password as well as the serviceURI to taverna
   * 
   * currently we don't check the t2flow doc for the service uris we just send
   * all the username and passwords.  in some case there maybe extra overhead
   * doing this but parsing a large t2flow doc has overhead of its own and for 
   * most use case we will have a limited number of users configured.
   * 
   * @param string $uuid
   */
  function send_credentials($uuid) {
    $location = $this->get_listener_config_path();
    $microservice_users_file = file_get_contents($location . '/microservice_users_file.xml');
    try{
      $microservice_users_xml = new SimpleXMLElement($microservice_users_file);
   } catch (Exception $e){
      throw new TavernaException("error reading microservice_users_file.xml " . $e->getMessage(), 'send credentials');
    }
    $users = $microservice_users_xml->users->children();

    if (empty($users)) {
      throw new TavernaException("Authentication is required, but no users defined in microservice_users_file.xml ", 'send credentials');
    }
    $host = $this->hostname . $uuid . '/security/credentials';
    foreach ($users as $user) {
      $data = '<credential xmlns="http://ns.taverna.org.uk/2010/xml/server/rest/">
        <userpass xmlns="http://ns.taverna.org.uk/2010/xml/server/">
        <serviceURI xmlns="http://ns.taverna.org.uk/2010/xml/server/">' . $user['serviceUri'] . '</serviceURI>
        <username xmlns="http://ns.taverna.org.uk/2010/xml/server/">' . $user['username'] . '</username>
        <password xmlns="http://ns.taverna.org.uk/2010/xml/server/">' . $user['password'] . '</password>
        </userpass>
        </credential>';
      $response = $this->curl_connect->postRequest($host, 'String', $data, 'application/xml');
      if ($response['status'] != 201) {
        throw new TavernaException('Error sending credentials ' . $response['headers'] . $response['content'], $response['status'], 'send credentials');
      }
    }
    return TRUE;
  }

}

?>
