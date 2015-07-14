<?php

require_once 'Derivatives.php';
define('DATACITE_DOI_URL', 'mds.datacite.org/doi');

class Datacite extends Derivative {
  private $curl_connect;
  private $namespace;
  private $datacite_config;

  function __construct($fedora_object, $incoming_dsid, $extension = NULL, $log, $created_datastream) {
    parent::__construct($fedora_object, $incoming_dsid, $extension = NULL, $log, $created_datastream);
    $this->namespace = $this->getNamespace($this->pid);
    $this->datacite_config = $this->parseDataCiteConfig($this->namespace);
    $this->curl_connect = $this->createCurlConnection($this->datacite_config);
  }

  function __destruct() {
    parent::__destruct();
  }

  function getNamespace($pid) {
    return substr($pid, 0, strlen(strstr($pid, ':', TRUE)));
  }

  /**
   * Create and mint Datacite DOI.
   *
   * @param string $outputdsid
   *   The output dsid
   * @param string $label
   *   the datastream label
   * @param array $params
   *   an array containing a datacite prefix and an application name
   *
   * @return int|string
   *   0 for success negative numbers for unrecoverable errors and positive numbers for recoverable errors
   */
  function mintDOI($outputdsid, $label, $params) {
    $this->log->lwrite('Starting processing', 'PROCESS_DATASTREAM', $this->pid, $outputdsid);
    if (empty($this->curl_connect)) {
      return MS_SYSTEM_EXCEPTION;
    }
    $return_value = MS_SUCCESS;
    $doi = $this->createDOI($params);
    if ($outputdsid == 'DDI') {
      $return_value = $this->registerDDIDOI($this->incoming_dsid, $doi);
      if ($return_value == MS_SUCCESS && !empty($this->datacite_config['url_prefix'])) {
        $response = $this->sendDoiToDatacite($doi, $this->datacite_config['url_prefix']);
        $test = '20';
        if (empty($response['status']) || !(substr($response['status'], 0, strlen($test)) === $test)) {
          $this->log->lwrite("Failed to register datacite xml $dsid error connecting to datacite, " . $response['status'], 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'ERROR');
          return MS_SYSTEM_EXCEPTION;
        }
        $new_DDI = $this->updateDDI($doi);
        // We've updated the doi in the ddi so send it back to Fedora.
        $this->fedora_object[$this->incoming_dsid]->setContentFromString($new_DDI);
      }
      else {
        $this->log->lwrite("Failed to mint Datacite DOI check the datacite_users.xml", 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'ERROR');
        return MS_SYSTEM_EXCEPTION;
      }
    }
    return $return_value;
  }

  /**
   * Register datacite xml as metadata.
   *
   * We need to register a DOI with metadata before we can mint it.
   *
   * @param string $dsid
   *   The dsid containing the metadata to crosswalk.  Currently only works
   * with DDI but hope to add MODS
   * @param $doi
   *   A new DOI
   *
   * @return int
   *   0 for success
   */
  function registerDDIDOI($dsid = 'DDI', $doi) {
    $this->log->lwrite("DOI = $doi", 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'INFO');
    if ($doi == MS_SYSTEM_EXCEPTION) {
      return $doi;
    }
    $datacite_xml = $this->transformDDIToDatacite($this->temp_file, $doi);
    if (empty($datacite_xml)) {
      $this->log->lwrite("Failed to create datacite xml $dsid can't locate xslt", 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'ERROR');
      return MS_SYSTEM_EXCEPTION;
    }

    $response = $this->sendXmlToDatacite($datacite_xml);

    $test = '20';
    if (empty($response['status']) || !(substr($response['status'], 0, strlen($test)) === $test)) {
      $this->log->lwrite("Failed to register datacite xml $dsid error connecting to datacite, " . $response['status'], 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'ERROR');
      return MS_SYSTEM_EXCEPTION;
    }
    else {
      $this->log->lwrite("Successfully registered datacite xml $dsid", 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'INFO');
    }
    return MS_SUCCESS;
  }

  /**
   * Create a DOI following UPEI conventions.
   *
   * @param array $params
   *   an array with keys of prefix and application
   *
   * @return int|string
   *   return the doi of -1 if there is an error.
   */
  function createDOI($params) {
    if (empty($params['prefix']) || empty($params['application'])) {
      $this->log->lwrite("Failed creating DOI for $this->incoming_dsid invalid parameters " . implode(', ', $params), 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'ERROR');
      return MS_SYSTEM_EXCEPTION;
    }
    return $params['prefix'] . '/' . $params['application'] . '/' . $this->pid;
  }

  /*function registerMODSDOI($outputdsid, $label, $params){
    $prefix = $params['prefix'];
    $application_name = $params['application'];
    $doi = createDOI($prefix, $application_name, $this->pid);
    $credentials_file = $params['credentials_file_path'];
  } */

  /**
   * Transform the DDI to Datacite xml.
   *
   * We use an xslt that can transform DDI3.2 to datacite 3.1
   *
   * @param string $xml_file
   *   The path to a DDI xml file
   * @param string $doi
   *   The doi to register
   *
   * @return null|string
   *   returns the datacite xml including the doi.  Null on failure
   */
  function transformDDIToDatacite($xml_file, $doi) {
    $xslt_path = realpath(dirname(__FILE__)) . '/xslt/ddi_3_2-datacite_3_1.xsl';
    $datacite_path = tempnam(NULL, 'datacite');
    $command = "java -jar /opt/saxon/saxon9he.jar -s:$xml_file -xsl:$xslt_path -o:$datacite_path doi=$doi";
    $ret = 0;
    exec($command, $output, $ret);
    if (!empty($ret)) {
      $this->log->lwrite("output of transforming DDI to Datacite value = $ret and " . implode(', ', $output), 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'ERROR');
      unlink($datacite_path);
      return NULL;
    }
    $datacite_xml = file_get_contents($datacite_path);
    unlink($datacite_path);
    return $datacite_xml;

  }

  /**
   * Reads an xml file to get the datacite username and password, and a url prefix.
   *
   * We associate usernames and urls with pid namespaces so we can support multisites
   *
   * @param striing $namespace
   *   a pid namespace
   * @return null|array
   *   returns and array on succes and null on failure
   */
  function parseDataCiteConfig($namespace) {
    $config_path = realpath(dirname(__FILE__)) . '/../datacite_users.xml';
    if (!file_exists($config_path)) {
      $this->log->lwrite("Failed to load datacite credentials could not find file, ", 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'ERROR');
      return NULL;
    }
    $users_xml = simplexml_load_file($config_path);
    if ($users_xml == FALSE) {
      $this->log->lwrite("Failed to load parse xml file, ", 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'ERROR');
      return NULL;
    }
    $datacite_namespace = $users_xml->xpath("//namespace[@name=\"$namespace\"]");
    if ($datacite_namespace == FALSE) {
      $this->log->lwrite("could not find a namespace match for $namespace, nothing to do", 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'INFO');
      return NULL;
    }
    $datacite_config['url_prefix'] = $datacite_namespace[0]->url_prefix;
    $datacite_config['username'] = $datacite_namespace[0]->user['username'];
    $datacite_config['password'] = $datacite_namespace[0]->user['password'];
    return $datacite_config;
  }

  function createCurlConnection($datacite_config) {
    extract($datacite_config);
    if (empty($username) || empty($password)) {
      return NULL;
    }
    $curl_connect = new CurlConnection();
    $curl_connect->username = $username;
    $curl_connect->password = $password;
    return $curl_connect;
  }

  /**
   * Sends the datacite xml to register the metadata for the new DOI.
   *
   * @param string $datacite_xml
   *   The datacite xml
   *
   * @return array|null
   *   array on success or NULL on failure
   */
  function sendXmlToDatacite($datacite_xml) {
    $metadata_endpoint = 'https://mds.datacite.org/metadata';
    try {
      $response = $this->curl_connect->postRequest($metadata_endpoint, 'string', $datacite_xml, 'application/xml');
    }
    catch (Exception $e) {
      $this->log->lwrite("Error registering DOI, " . $e->getMessage(), 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'ERROR');
      return NULL;
    }
    return $response;
  }

  /**
   * Send the doi and url to datacite to actually mint the new doi.
   *
   * @param string $doi
   *   The doi to mint.
   * @param string $url
   *   The url to associate with the doi.
   * @return array|null
   *   array on success null on failure
   */
  function sendDoiToDatacite($doi, $url) {
    $doi_endpoint = 'https://mds.datacite.org/doi';
    $data = sprintf("doi=%s\nurl=%s", $doi, $url . '/' . $this->pid);
    try {
      $response = $this->curl_connect->postRequest($doi_endpoint, 'string', $data, 'text/plain;charset=UTF-8');
    }
    catch (Exception $e) {
      $this->log->lwrite("Error sendig DOI to datacite, " . $e->getMessage() . $e->getTraceAsString(), 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'ERROR');
      return NULL;
    }
    return $response;
  }

  /**
   * Update the DDI xml with the doi so we can send it back to Fedora.
   *
   * @param string $doi
   *   The doi.
   *
   * @return int|string
   * the update xml on success.
   */
  function updateDDI($doi) {
    $xml = new DOMDocument();
    $test = $xml->load($this->temp_file);
    if(empty($test)){
      $this->log->lwrite("Error loading DDI xml can't update the DDI with the new DOI", 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'ERROR');
      return MS_SYSTEM_EXCEPTION;
    }
    $query = "//r:Citation/r:InternationalIdentifier[r:ManagingAgency/text() = 'Datacite']/r:IdentifierContent";
    $xpath = new DOMXPath($xml);
    //$xpath->registerNamespace('s','ddi:studyunit:3_2');
    $xpath->registerNamespace('r', 'ddi:reusable:3_2');
    $results = $xpath->query($query,$xml);
    $found = FALSE;
    foreach ($results as $element){
      $found = TRUE;
      $element->nodeValue = $doi;
    }
    if(!$found){
      // We need to add the new identifier
      $study_unit = $xml->getElementsByTagName('StudyUnit');
      $citation = $study_unit->item(0)->getElementsbyTagName('Citation');
      $internationalIdentifier = $xml->createElementNS("ddi:reusable:3_2", "r:InternationalIdentifier");
      $citation->item(0)->appendChild($internationalIdentifier);
      $managingAgency = $xml->createElementNS("ddi:reusable:3_2", "r:ManagingAgency", "Datacite");
      $internationalIdentifier->appendChild($managingAgency);
      $identifierContent = $xml->createElementNS("ddi:reusable:3_2", "r:IdentifierContent", $doi);
      $internationalIdentifier->appendChild($identifierContent);
    }
    return $xml->saveXML();
  }

}


