<?php
/**
 * @file default services provided by UPEI Robertson Library
 */

require_once 'includes/Image.php';
require_once 'includes/Text.php';
require_once 'includes/Technical.php';
require_once 'includes/Pdf.php';
require_once 'includes/Relationships.php';
require_once 'includes/ObjectManagement.php';
require_once 'includes/Transforms.php';
require_once 'includes/Video.php';
require_once 'includes/Audio.php';

class RoblibServices extends IslandoraService{

  function RoblibServices(){
    parent::__construct();
    $this->connect();
     $this->__dispatch_map['read'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string',
        'extension' => 'string'
      ),
      'out' => array('base64_content' => 'string'),
       'class' => 'RoblibServices'
    );

    /**
     * <b>write</b> write back to fedora
     */
    $this->__dispatch_map['write'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string',
        'label' => 'string',
        'base64_content' => 'string',
        'mimetype' => 'string'
      ),
      'out' => array('message' => 'string')
    );

    /**
     * Deletes the given datastream.
     * Processing in ObjectManagement.php
     */
    $this->__dispatch_map['deleteDatastream'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string',
      ),
      'out' => array('exit_status' => 'int')
    );

    /**
     * <b>allOcr</b> processing in Text.php 
     */
    $this->__dispatch_map['allOcr'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string',
        'outputdsid' => 'string',
        'label' => 'string',
        'language' => 'string'
      ),
      'out' => array('exit_status' => 'int')
    );

    /**
     * <b>ocr</b> processing in Text.php
     */
    $this->__dispatch_map['ocr'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string',
        'outputdsid' => 'string',
        'label' => 'string',
        'language' => 'string'
      ),
      'out' => array('exit_status' => 'int')
    );

    /**
     * <b>hOcr</b> processing in Text.php
     */
    $this->__dispatch_map['hOcr'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string',
        'outputdsid' => 'string',
        'label' => 'string',
        'language' => 'string'
      ),
      'out' => array('exit_status' => 'int')
    );

    /**
     * <b>jpg</b> processing in Image.php
     */
    $this->__dispatch_map['jpg'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string',
        'outputdsid' => 'string',
        'label' => 'string',
        'resize' => 'int'
      ),
      'out' => array('exit_status' => 'int')
    );

    /**
     * <b>video</b> processing in Video.php
     */
    $this->__dispatch_map['video'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string',
        'outputdsid' => 'string',
        'label' => 'string',
        'type' => 'string'
      ),
      'out' => array('exit_status' => 'int')
    );

    /**
     * <b>video thumbnail</b> processing in Video.php
     */
    $this->__dispatch_map['tnFromVideo'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string',
        'outputdsid' => 'string',
        'label' => 'string',
      ),
      'out' => array('exit_status' => 'int')
    );

    /**
     * <b>audio thumbnail</b> processing in audio.php
     */
    $this->__dispatch_map['tnForAudio'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string',
        'outputdsid' => 'string',
        'label' => 'string',
      ),
      'out' => array('exit_status' => 'int')
    );

    /**
     * <b>mp3 derivative</b> processing in Audio.php
     */
    $this->__dispatch_map['mp3'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string',
        'outputdsid' => 'string',
        'label' => 'string',
      ),
      'out' => array('exit_status' => 'int')
    );

    /**
     * <b>jp2</b> processing in Image.php
     */
    $this->__dispatch_map['jp2'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string',
        'outputdsid' => 'string',
        'label' => 'string'
      ),
      'out' => array('exit_status' => 'int')
    );


    /**
     * <b>techmd</b> processing in Technical.php
     */
    $this->__dispatch_map['techmd'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string',
        'outputdsid' => 'string',
        'label' => 'string'
      ),
      'out' => array('exit_status' => 'int')
    );

    /**
     * <b>scholarPdfa</b> processing in Pdf.php
     */
    $this->__dispatch_map['scholarPdfa'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string',
        'outputdsid' => 'string',
        'label' => 'string'
      ),
      'out' => array('exit_status' => 'int')
    );

    /**
     * <b>pdf</b> processing in Pdf.php
     */
    $this->__dispatch_map['pdf'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string',
        'outputdsid' => 'string',
        'label' => 'string'
      ),
      'out' => array('exit_status' => 'int')
    );

    /**
     * <b>addImageDimensionsToRels</b> processing in Relationships.php
     */
    $this->__dispatch_map['addImageDimensionsToRels'] = array(
      'in' => array(
        'pid' => 'string',
        'dsid' => 'string',
        'outputdsid' => 'string',
        'label' => 'string'
      ),
      'out' => array('exit_status' => 'int')
    );

    
  }
  
   /**
   * Reads a fedora object from an external repository used to so we can 
   * read a datastream from Fedora and then pass it to extarnal non islandora 
   * services.  Most islandora services should not need this service.
   * 
   * @param string $pid
   * The object's pid
   * 
   * @param string $dsid
   * The object's dsID
   * 
   * @param string $extension
   *  
   */
  function read($pid, $dsid, $extension) {
    try {
      $fedora_user = new stdClass();
      $fedora_user->name = $this->config->fedora->username;
      $fedora_user->pass = $this->config->fedora->password;
      if (fedora_object_exists($this->config->fedora->protocol . '://' . $this->config->fedora->host . ':' . $this->config->fedora->port . '/fedora', $fedora_user, $pid)) {
        $content = $this->fedora_connect->getDatastream($pid, $dsid)->content;
        return base64_encode($content);
      }
    } catch (Exception $e) {
      $this->log->lwrite("An error occurred creating the fedora object", 'FAIL_OBJECT', $pid, $e->getMessage(), 'ERROR');
    }
  }

  /**
   * Writes a fedora object back to the repository.  Used for storing the 
   * results of external non islandora services.  Most islandora services 
   * should not need this service.
   * 
   * @param string $pid
   *  The pid of fedora object to write back
   * 
   * @param string $dsid
   *  The dsid of fedora object to write back
   * 
   * @param string $label
   *  The object's label
   * 
   * @param string $base64_content
   *  The content of object that base 64
   * 
   * @param string $mimetype
   *  The mime type of object
   * 
   * @return string
   */
  function write($pid, $dsid, $label, $base64_content, $mimetype) {
    return $this->fedora_connect->addDerivative($pid, $dsid, $label, base64_decode($base64_content), $mimetype, null, true, false);
  }

  /**
   * This function deletes the given datastream.
   *
   * @param string $pid
   *  The PID of the fedora object which datastream will be deleted
   *
   * @param string $dsid
   *  The DSID of fedora object datastream which will be deleted
   *
   * @return int
   */
  function deleteDatastream($pid, $dsid) {
    $params = array(
      'class' => 'ObjectManagement',
      'function' => 'deleteDatastream',
      'logMessage' => "$dsid datastream successfully deleted using microservices funcion ObjectManagement->deleteDatastream() || SUCCESS",
    );
    return $this->service($pid, $dsid, $dsid, $dsid, $params);
  }

  /**
   * This function creates an HOCR and OCR datastream
   * 
   * @param string $pid
   *  The pID of fedora Object which to read and write
   * 
   * @param string $dsid
   *  The dsid of fedora Object which to read 
   * 
   * @param string $outputdsid
   *  The dsid of fedora Object to write back
   * 
   * @param string $label
   *  The label of fedora Object to write 
   * 
   * @param string $language
   *  The language of ocr
   * 
   * @return int
   */
  function allOcr($pid, $dsid = 'JPEG', $outputdsid = 'HOCR', $label = 'HOCR', $language = 'eng') {
    $params = array('class' => 'Text', 'function' => 'allOcr', 'language' => $language);
    return $this->service($pid, $dsid, $outputdsid, $label, $params);
  }

  /**
   * This function creates an OCR datastream
   * 
   * @param string $pid
   *  The pID of fedora Object which to read and write
   * 
   * @param string $dsid
   *  The dsid of fedora Object which to read 
   * 
   * @param string $outputdsid
   *  The dsid of fedora Object to write back
   * 
   * @param string $label
   *  The label of fedora Object to write 
   * 
   * @param string $language
   *  The language of ocr
   * 
   * @return int
   */
  function ocr($pid, $dsid = 'OCR', $outputdsid = 'OCR', $label = 'Scanned text', $language = 'eng') {
    $params = array('class' => 'Text', 'function' => 'ocr', 'language' => $language);
    return $this->service($pid, $dsid, $outputdsid, $label, $params);
    
  }

  /**
   * This function creates a hocr datastream
   * 
   * @param string $pid
   *  The pID of fedora Object which to read and write
   * 
   * @param string $dsid
   *  The dsid of fedora Object which to read 
   * 
   * @param string $outputdsid
   *  The dsid of fedora Object to write back
   * 
   * @param string $label
   *  The label of fedora Object to write 
   * 
   * @param string $language
   *  The language of ocr
   * 
   * @return int
   */
  function hOcr($pid, $dsid = 'JPEG', $outputdsid = 'HOCR', $label = 'HOCR', $language = 'eng') {
    $params = array('class' => 'Text', 'function' => 'ocr', 'language' => $language, 'hocr' => 'hocr');
    return $this->service($pid, $dsid, $outputdsid, $label, $params);
  }

  
  /**
   * This function creates jpg derivatives from other image files
   * 
   * @param string $pid
   *  The pID of fedora Object which to read and write
   * 
   * @param string $dsid
   *  The dsid of fedora Object which to read 
   * 
   * @param string $outputdsid
   *  The dsid of fedora Object to write back
   * 
   * @param string $label
   *  The label of fedora Object to write 
   * 
   * @param string $resize
   *  The new size of jpg
   *  
   * @return int
   */
  function jpg($pid, $dsid = "JPEG", $outputdsid = "JPG", $label = "JPEG image", $resize = "800") {
    $params = array('class' => 'Image', 'function' => 'jpg','resize' => $resize);
    return $this->service($pid, $dsid, $outputdsid, $label, $params);
  }

  /**
   * This function creates video derivatives from other video files
   *
   * @param string $pid
   *  The pID of fedora Object which to read and write
   *
   * @param string $dsid
   *  The dsid of fedora Object which to read
   *
   * @param string $outputdsid
   *  The dsid of fedora Object to write back
   *
   * @param string $label
   *  The label of fedora Object to write
   *
   * @param string $type
   *  the type of video to create currently supports mp4 or mkv
   *
   * @return int
   */
  function video($pid, $dsid, $outputdsid, $label, $type){
    $params = array('class' => 'Video', 'function' => 'createVideoDerivative','type' => $type);
    return $this->service($pid, $dsid, $outputdsid, $label, $params);
  }

  /**
   * This function creates video thumbnail derivatives from video files
   *
   * @param string $pid
   *  The pID of fedora Object which to read and write
   *
   * @param string $dsid
   *  The dsid of fedora Object which to read
   *
   * @param string $outputdsid
   *  The dsid of fedora Object to write back
   *
   * @param string $label
   *  The label of fedora Object to write
   *
   *  @return int
   */
  function tnFromVideo($pid, $dsid, $outputdsid, $label){
    $params = array('class' => 'Video', 'function' => 'createThumbnailFromVideo');
    return $this->service($pid, $dsid, $outputdsid, $label, $params);
  }

  /**
   * This function adds thumbnail derivative from default files
   *
   * @param string $pid
   *  The pID of fedora Object which to read and write
   *
   * @param string $dsid
   *  The dsid of fedora Object which to read
   *
   * @param string $outputdsid
   *  The dsid of fedora Object to write back
   *
   * @param string $label
   *  The label of fedora Object to write
   *
   *  @return int
   */
  function tnForAudio($pid, $dsid, $outputdsid, $label){
    $params = array('class' => 'Derivative', 'function' => 'addDefaultThumbnail',
    'type' => 'audio');
    return $this->service($pid, $dsid, $outputdsid, $label, $params);
  }

  /**
   * This function creates an mp3 derivative from audio streams
   *
   * @param string $pid
   *  The pID of fedora Object which to read and write
   *
   * @param string $dsid
   *  The dsid of fedora Object which to read
   *
   * @param string $outputdsid
   *  The dsid of fedora Object to write back
   *
   * @param string $label
   *  The label of fedora Object to write
   *
   *  @return int
   */
  function mp3($pid, $dsid, $outputdsid, $label){
    $params = array('class' => 'Audio', 'function' => 'createMP3Derivative');
    return $this->service($pid, $dsid, $outputdsid, $label, $params);
  }
  
  /**
   * This function creates jp2 derivatives from other tiffs
   * 
   * @param string $pid
   *  The pID of fedora Object which to read and write
   * 
   * @param string $dsid
   *  The dsid of fedora Object which to read 
   * 
   * @param string $outputdsid
   *  The dsid of fedora Object to write back
   * 
   * @param string $label
   *  The label of fedora Object to write 
   * 
   * @return type
   */
  function jp2($pid, $dsid = "OBJ", $outputdsid = "JP2", $label = "Compressed jp2") {
    $params = array('class' => 'Image', 'function' => 'jp2');
    return $this->service($pid, $dsid, $outputdsid, $label, $params);
  }

  /**
   * This file will call command in Technical.php
   * 
   * @param string $pid
   *  The pID of fedora Object which to read and write
   * 
   * @param string $dsid
   *  The dsid of fedora Object which to read 
   * 
   * @param string $outputdsid
   *  The dsid of fedora Object to write back
   * 
   * @param string $label
   *  The label of fedora Object to write 
   *
   *  @return int
   */
  function techmd($pid, $dsid = 'OBJ', $outputdsid = "TECHMD", $label = 'Technical metadata') {
    $params = array('class' => 'Technical', 'function' => 'techmd');
    return $this->service($pid, $dsid, $outputdsid, $label, $params);
  }

  /**
   * This function is to call JODconverter service
   * 
   * @param string $pid
   *  The pID of fedora Object which to read and write
   * 
   * @param string $dsid
   *  The dsid of fedora Object which to read 
   * 
   * @param string $outputdsid
   *  The dsid of fedora Object to write back
   * 
   * @param string $label
   *  The label of fedora Object to write 
   * 
   * @return int
   */
  function scholarPdfa($pid, $dsid = 'OBJ', $outputdsid = "PDF", $label = 'PDF') {
    $params = array('class' => 'Pdf', 'function' => 'scholarPdfa');
    return $this->service($pid, $dsid, $outputdsid, $label, $params);
  }

  /**
   * This function is to add image dimendions to the RELS-INT datastream.
   * 
   * @param string $pid
   *  The pID of fedora Object which to read and write
   * 
   * @param string $dsid
   *  The dsID of fedora datastream to use as a source
   * 
   * @param string $outputdsid
   *  The dsID of fedora Object to write back
   * 
   * @param string $label
   *  The label of fedora Object to write 
   * 
   * @return int
   */
  function addImageDimensionsToRels($pid, $dsid = 'OBJ', $outputdsid = "RELS-INT", $label = 'RELS-INT') {
    $params = array('class' => 'Relationship', 'function' => 'addImageDimensionsToRels');
    return $this->service($pid, $dsid, $outputdsid, $label, $params);
  }

  /**
   * This file will call pdf command Pdf.php
   * 
   * @param string $pid
   *  The pID of fedora Object which to read and write
   * 
   * @param string $dsid
   *  The dsid of fedora Object which to read 
   * 
   * @param string $outputdsid
   *  The dsid of fedora Object to write back
   * 
   * @param string $label
   *  The label of fedora Object to write 
   *
   *  @return int
   */
  function pdf($pid, $dsid = 'OBJ', $outputdsid = "PDF", $label = 'PDF') {
    $params = array('class' => 'Pdf', 'function' => 'toPdf');
    return $this->service($pid, $dsid, $outputdsid, $label, $params);
  }
}
?>
