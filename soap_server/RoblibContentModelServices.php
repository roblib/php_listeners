<?php
/**
 * Created by IntelliJ IDEA.
 * User: ppound
 * Date: 13-12-02
 * Time: 11:36 AM
 * To change this template use File | Settings | File Templates.
 */

require_once 'includes/Relationships.php';

class RoblibContentModelServices extends IslandoraService {
  public $namespace = 'urn:php-roblibCModel-soapservice';

  function RoblibContentModelServices() {
    parent::__construct();
    $this->connect();

    /**
     * <b>add a cmodel relationship</b> to an object
     */
    $this->__dispatch_map['addCModelToObject'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string',
        'outputdsid' => 'string',
        'label' => 'string',
        'cmodel' => 'string'
      ),
      'out' => array('exit_status' => 'int')
    );

    /**
     * <b>remove a cmodel relationship</b> from an object
     */
    $this->__dispatch_map['removeCModelFromObject'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string',
        'outputdsid' => 'string',
        'label' => 'string',
        'cmodel' => 'string'
      ),
      'out' => array('exit_status' => 'int')
    );
  }

  /**
   * This function adds a cmodel relationship to an objects rels-ext.
   *
   * @param string $pid
   *    The pID of fedora Object which to read and write
   *
   * @param string $dsid
   *    The dsid of fedora Object which to read
   *
   * @param string $outputdsid
   *    The dsid of fedora Object to write back
   *
   * @param string $label
   *    The label of fedora Object to write
   *
   * @param string $cmodel
   *    The cmodel to add to this object
   *
   * @return int
   *
   */
  function addCModelToObject($pid, $dsid = 'RELS-EXT', $outputdsid = 'RELS-EXT', $label = 'RELS-EXT', $cmodel) {
    $params = array(
      'class' => 'Relationship',
      'function' => 'addCModelToObject',
      'cmodel' => $cmodel
    );
    return $this->service($pid, $dsid, $outputdsid, $label, $params);
  }

  /**
   * This function removes a cmodel relationship from the object rels-ext.
   *
   * @param string $pid
   *  The pID of fedora Object which to read and write
   *
   * @param string $dsid
   *    The dsid of fedora Object which to read
   *
   * @param string $outputdsid
   *  The dsid of fedora Object to write back
   *
   * @param string $label
   *  The label of fedora Object to write
   *
   * @param $cmodel
   *   The cmodel to remove from this object
   *
   * @return int
   */
  function removeCModelFromObject($pid, $dsid = 'RELS-EXT', $outputdsid = 'RELS-EXT', $label = 'RELS-EXT', $cmodel) {
    $params = array(
      'class' => 'Relationship',
      'function' => 'removeCModelFromObject',
      'cmodel' => $cmodel
    );
    return $this->service($pid, $dsid, $outputdsid, $label, $params);
  }
}

?>