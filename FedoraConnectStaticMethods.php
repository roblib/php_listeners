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

function system_extension_mime_types() {
  # Returns the system MIME type mapping of extensions to MIME types, as defined in /etc/mime.types.
  $out = array();
  $file = fopen('/etc/mime.types', 'r');
  while (($line = fgets($file)) !== false) {
    $line = trim(preg_replace('/#.*/', '', $line));
    if (!$line)
      continue;
    $parts = preg_split('/\s+/', $line);
    if (count($parts) == 1)
      continue;
    $type = array_shift($parts);
    foreach ($parts as $part)
      $out[$part] = $type;
  }
  fclose($file);
  return $out;
}

function system_extension_mime_type($file) {
  # Returns the system MIME type (as defined in /etc/mime.types) for the filename specified.
  #
    # $file - the filename to examine
  static $types;
  if (!isset($types))
    $types = system_extension_mime_types();
  $ext = pathinfo($file, PATHINFO_EXTENSION);
  if (!$ext)
    $ext = $file;
  $ext = strtolower($ext);
  return isset($types[$ext]) ? $types[$ext] : null;
}

function system_mime_type_extensions() {
  # Returns the system MIME type mapping of MIME types to extensions, as defined in /etc/mime.types (considering the first
  # extension listed to be canonical).
  $out = array();
  $file = fopen('/etc/mime.types', 'r');
  while (($line = fgets($file)) !== false) {
    $line = trim(preg_replace('/#.*/', '', $line));
    if (!$line) {
      continue;
    }
    $parts = preg_split('/\s+/', $line);
    if (count($parts) == 1) {
      continue;
    }
    $type = array_shift($parts);
    if (!isset($out[$type])) {
      $out[$type] = array_shift($parts);
    }
    // we only support mp3s for now and lame expects the extentsion to be mp3
    // not mpga which is what is coming from the mimetype file.
    if ($type == 'audio/mpeg') {
      $out[$type] = 'mp3';
    }

  }
  fclose($file);
  return $out;
}

function system_mime_type_extension($type) {
  # Returns the canonical file extension for the MIME type specified, as defined in /etc/mime.types (considering the first
  # extension listed to be canonical).
  #
    # $type - the MIME type
  static $exts;
  if (!isset($exts))
    $exts = system_mime_type_extensions();
  return isset($exts[$type]) ? $exts[$type] : null;
}

function temp_filename($extension = NULL) {
  while (true) {
    $filename = sys_get_temp_dir() . '/' . uniqid(rand()) . '.' . $extension;
    if (!file_exists($filename))
      break;
  }
  return $filename;
}

function fedora_object_exists($fedora_url = 'http://localhost:8080/fedora', $user = NULL, $pid = NULL) {
  if (!isset($pid)) {
    return;
  }

  $return = TRUE;
  $fedora_user = $user->name;
  $fedora_pass = $user->pass;

  $connection = new RepositoryConnection($fedora_url, $fedora_user, $fedora_pass);
  $connection->reuseConnection = TRUE;
  $api = new FedoraApi($connection);
  $cache = new SimpleCache();
  $repos = new FedoraRepository($api, $cache);

  try {
    $repos->getObject($pid);
  } catch (RepositoryException $exc) {
    $return = FALSE;

  }
  return $return;
  }

?>
