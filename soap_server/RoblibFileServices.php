<?php
/**
 * Created by IntelliJ IDEA.
 * User: ppound
 * Date: 14-12-16
 * Time: 11:46 AM
 */
require_once 'includes/FileServices.php';

class RoblibFileServices extends IslandoraService {
  public $namespace = 'urn:php-roblibFile-soapservice';
  // create a dispatch map so the soap server will add our function to the wsdl
  function RoblibFileServices() {
    parent::__construct();
    $this->connect();

    $this->__dispatch_map['scpDatastreamToRemoteServer'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string',
        'outputfile_name' => 'string',
        'outputfile_extension' => 'string',
        'server_ip_or_domain' => 'string',
        'server_directory' => 'string',
        'scp_username' => 'string',
        'path_to_identity_file' => 'string',
      ),
      'out' => array('exit_status' => 'int')
    );
  }

  /**
   * This function reads a datastream from Fedoroa and scp's it to a remote server.
   *
   * @param string $pid
   *   The pid of fedora Object which to read and write
   *
   * @param string $dsid
   *   The dsid of fedora Object which to read
   *
   * @param string $outputfile_name
   *   The name of the file to copy to on the remote server
   *
   * @param string $outputfile_extension
   *   The extension for the file
   *
   * @param string $server_ip_or_domain
   *   The remote server
   *
   * @param string $server_directory
   *   The directory to copy the file into
   *
   * @param string $scp_username
   *   The username to use to connect to the remote server.
   *
   * @param string $encrypted_scp_password
   *   The password to use to connect to the remote server.  should be encrypted
   *   using a key on the server the service will run on.
   *
   * @return int
   *   return 0 on success
   *
   */
  function scpDatastreamToRemoteServer($pid, $dsid, $outputfile_name, $outputfile_extension
, $server_ip_or_domain, $server_directory, $scp_username, $path_to_identity_file) {

    $params = array(
      'class' => 'FileServices',
      'function' => 'scpDatastreamToRemoteServer',
      'outputFileName' => $outputfile_name,
      'outputFileExtension' => $outputfile_extension,
      'serverIpOrDomain' => $server_ip_or_domain,
      'serverDirectory' => $server_directory,
      'scpUsername' => $scp_username,
      'pathToIdentityFile' => $path_to_identity_file,
    );
    return $this->service($pid, $dsid, $dsid, $dsid, $params);
  }

}