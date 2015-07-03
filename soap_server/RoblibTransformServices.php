<?php
/**
 * Created by IntelliJ IDEA.
 * User: ppound
 * Date: 2014-07-08
 * Time: 1:00 PM
 */

require_once 'includes/Transforms.php';
require_once 'includes/Datacite.php';

class RoblibTransformServices extends IslandoraService {
  public $namespace = 'urn:php-roblibTransform-soapservice';

  function RoblibTransformServices() {
    parent::__construct();
    $this->connect();

    $this->__dispatch_map['updateEMLTaxonomy'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string',
        'outputdsid' => 'string',
        'itis_string' => 'string',
      ),
      'out' => array('exit_status' => 'int')
    );

    $this->__dispatch_map['mintDOI'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string',
        'prefix' => 'string',
        'application' => 'string',
      ),
      'out' => array('exit_status' => 'int')
    );
  }

  /**
   * This function updates the EML taxonomy classifications.
   *
   * @param string $pid
   *   The pid of fedora Object which to read and write
   *
   * @param string $dsid
   *   The dsid of fedora Object which to read
   *
   * @param string $outputdsid
   *   The dsid of fedora Object which to write to
   *
   * @param string $itis_string
   *   The dsid of fedora Object to write back
   *
   * @return int
   *   return 0 on success
   *
   */
  function updateEMLTaxonomy($pid, $dsid, $outputdsid, $itis_string) {
    $params = array(
      'class' => 'Transforms',
      'function' => 'updateEMLTaxonomy',
      'itisString' => $itis_string,
    );
    return $this->service($pid, $dsid, $outputdsid, 'EML', $params);
  }

  /**
   * This function mints a datacite doi.
   *
   * @param string $pid
   *   The pid of fedora Object which to read and write
   *
   * @param string $dsid
   *   The dsid of fedora Object which to read
   *
   * @param $prefix
   *   The institutions datacite prefix
   * @param $url_prefix
   *   The url_prefix for the url datacite will point to (ie. http://domain.com/islandora/object/
   * @param $application
   *   A localised prefix for the DOI (a doi will look like prefix/application/pid)
   * @param $credentials_file_path
   *   Path to a file that has the user ana password to datacite.
   *
   * @return int
   * return 0 on success
   */
  function mintDOI($pid, $dsid, $prefix, $application) {
    $params = array(
      'class' => 'Datacite',
      'function' => 'mintDOI',
      'prefix' => $prefix,
      'application' => $application,
    );
    return $this->service($pid, $dsid, $dsid, $dsid, $params);
  }
}

