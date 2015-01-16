<?php
/**
 * Created by IntelliJ IDEA.
 * User: ppound
 * Date: 14-12-16
 * Time: 12:38 PM
 */

require_once 'Derivatives.php';

class FileServices extends Derivative {
  function __destruct() {
    parent::__destruct();
  }

  /**
   * Scp a file from one the services server to another server.
   *
   * The server you are copying the file to must have the matching public
   * key that corresponds to the identity referenced in the path_to_identity_file
   * parameter.
   *
   * @param string $outputdsid
   *   The output dsid
   * @param string $label
   *   the datastream label
   * @param array $params
   *   an array containing the information needed to connect to the remote server
   *
   * @return int
   *   returns 0 on success
   */
  function scpDatastreamToRemoteServer($outputdsid, $label, $params) {
    $this->log->lwrite('Starting processing for scp service', 'PROCESS_DATASTREAM', $this->pid, $outputdsid);
    if (empty($params['outputFileName']) || empty($params['outputFileExtension']) ||
      empty($params['serverIpOrDomain']) ||
      empty($params['serverDirectory']) || empty($params['pathToIdentityFile']) ||
      empty($params['scpUsername'])
    ) {
      $this->log->lwrite("Failed to run scp service missing parameters", 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'ERROR');
      return MS_SYSTEM_EXCEPTION;
    }
    $return = MS_SUCCESS;

    extract($params, EXTR_SKIP);
    $file_name = str_replace(':', '-', $this->pid) . '-' . $outputdsid . '.' . $outputFileExtension;
    $command = <<<TAG
scp -i $pathToIdentityFile -o StrictHostKeyChecking=no $this->temp_file $scpUsername@$serverIpOrDomain:$serverDirectory/$file_name 2>&1
TAG;

    exec($command, $scp_output = array(), $return);
    if ($return) {
      $this->log->lwrite("Failed to run scp service " . implode($scp_output), 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'ERROR');
      return MS_SYSTEM_EXCEPTION;
    }
    unlink($this->temp_file);
    return MS_SUCCESS;
  }

  /**
   * Add an externally referenced datastream to a fedora object.
   *
   *
   *
   * @param string $outputdsid
   *   The output dsid
   * @param string $label
   *   the datastream label
   * @param array $params
   *   an array containing the information needed to connect to the remote server
   *
   * @return int
   *   returns 0 on success
   */

  function addExternalReferenceDatastream($output_dsid, $label, $params) {
    $this->log->lwrite('Starting processing external datastream function', 'PROCESS_DATASTREAM', $this->pid, $output_dsid);

    if (empty($params['locationUrl']) || empty($params['type']) ||
      empty($params['mimetype']))
    {
      $this->log->lwrite("Failed to run external database service missing parameters", 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'ERROR');
      return MS_SYSTEM_EXCEPTION;
    }
    extract($params, EXTR_SKIP);
    $log_message = "Added externally referenced datastream via microservices";
    $return = $this->add_derivative($output_dsid, $label, $locationUrl,
      $mimetype, $log_message, TRUE, FALSE, $type);
    if ($return) {
      $this->log->lwrite("Failed add externally referenced datastream ", 'PROCESS_DATASTREAM', $this->pid, $this->incoming_dsid, 'ERROR');
    }
    return $return;
  }

}