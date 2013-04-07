<?php

/**
 * Class to make an initial connection to Fedora
 *  
 * @author Richard Wincewicz
 */
require_once 'tuque/Datastream.php';
require_once 'tuque/FedoraApi.php';
require_once 'tuque/FedoraApiSerializer.php';
require_once 'tuque/Object.php';
require_once 'tuque/RepositoryConnection.php';
require_once 'tuque/Cache.php';
require_once 'tuque/RepositoryException.php';
require_once 'tuque/Repository.php';
require_once 'tuque/FedoraRelationships.php';
require_once 'FedoraConnectStaticMethods.php';

class ListenerObject {

  /**
   * Connection to the repository
   *
   * @var RepositoryConnection
   */
  public $connection = NULL;

  /**
   * The Fedora API we are using
   *
   * @var FedoraAPI
   */
  public $api = NULL;

  /**
   * The cache we use to connect.
   *
   * @var SimpleCache
   */
  public $cache = NULL;

  /**
   * The repository object.
   *
   * @var FedoraRepository
   */
  public $repository = NULL;
  public $object = NULL;

  function __construct($user = NULL, $url = NULL, $pid = NULL) {

    $user_string = $user->name;
    $pass_string = $user->pass;



    if (!isset($url)) {
      $url = 'http://localhost:8080/fedora';
    }

    //if (self::exists()) {
    try {
      $this->connection = new RepositoryConnection($url, $user_string, $pass_string);
      $this->connection->reuseConnection = TRUE;
      $this->api = new FedoraApi($this->connection);
      $this->cache = new SimpleCache();
      $this->repository = new FedoraRepository($this->api, $this->cache);
      $this->object = new FedoraObject($pid, $this->repository);
    } catch (Exception $e) {
      file_put_contents('php://stderr', "Could not create fedora object $pid - $e");
    }
    //}
  }

  function saveDatastream($dsid = NULL, $extension = NULL) {
    if (!isset($dsid)) {
      return;
    }
    $datastream_array = array();
    foreach ($this->object as $datastream) {
      $datastream_array[] = $datastream->id;
    }
    if (!in_array($dsid, $datastream_array)) {
      print "Could not find the $dsid datastream!";
    }
    try {
      $datastream = $this->object->getDatastream($dsid);
      $mime_type = $datastream->mimetype;
      if (!$extension) {
        $extension = system_mime_type_extension($mime_type);
      }
      $tempfile = temp_filename($extension);
      $file_handle = fopen($tempfile, 'w');
      fwrite($file_handle, $datastream->content);
      fclose($file_handle);
    } catch (Exception $e) {
      print "Could not save datastream - $e";
    }

    return $tempfile;
  }

  static function exists() {
    return class_exists('RepositoryConnection');
  }

}

?>
