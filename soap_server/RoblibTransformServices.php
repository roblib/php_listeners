<?php
/**
 * Created by IntelliJ IDEA.
 * User: ppound
 * Date: 2014-07-08
 * Time: 1:00 PM
 */

require_once 'includes/Transforms.php';

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
}

