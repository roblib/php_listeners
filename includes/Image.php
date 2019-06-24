<?php

require_once 'Derivatives.php';

class Image extends Derivative {

  function __destruct() {
    parent::__destruct();
  }

  /**
   * 
   * @param type $dsid
   * @param type $label
   * @return type int
   * return a 0 on succes and a negative number on failure
   */
  function jp2($dsid = 'JP2', $label = 'Compressed jp2') {
    $kdu_path = getenv('LISTENER_KDU_PATH');
    $return = MS_SYSTEM_EXCEPTION;
    if (empty($kdu_path)) {
      $kdu_path = '/usr/local/bin';
    }
    $mime = strtolower($this->fedora_object['OBJ']->mimetype);
    $use_image_magick = ($mime == 'image/jpeg' || $mime == 'image/jpg') ? TRUE : FALSE;
    $this->log->lwrite('Starting processing with path ' . $kdu_path, 'PROCESS_DATASTREAM', $this->pid, $dsid);
    if (file_exists($this->temp_file)) {
      $output_file = $this->temp_file . '_JP2.jp2';
      if ($use_image_magick) {
        $command = 'convert ' . $this->temp_file . ' ' . implode($this->getMagickJp2Args()) . ' ' . $output_file . ' 2>&1';
      }
      else {
        $command = $kdu_path . '/kdu_compress -i ' . $this->temp_file . ' -o ' . $output_file . ' -no_palette -rate 0.5 Clayers=1 Clevels=7 Cprecincts=\{256,256\},\{256,256\},\{256,256\},\{128,128\},\{128,128\},\{64,64\},\{64,64\},\{32,32\},\{16,16\} Corder=RPCL ORGgen_plt=yes ORGtparts=R Cblk=\{32,32\} Cuse_sop=yes 2>&1';
      }
      $jp2_output = array();
      exec($command, $jp2_output, $return);
      if (file_exists($output_file)) {
        $log_message = "$dsid derivative created using kdu_compress with command - $command || SUCCESS";
        $return = $this->add_derivative($dsid, $label, $output_file, 'image/jp2', $log_message);
      }
      else {
        // We know there was an error because the output file does not exist.
        $return = MS_SYSTEM_EXCEPTION;
        $this->log->lwrite("Could not find the file '$output_file' for output: " . implode(', ', $jp2_output) . "\nReturn value: $return", 'FAIL_DATASTREAM', $this->pid, 'JP2', NULL, 'ERROR');
      }
    }// end file exists
    else {
      $this->log->lwrite("Could not create the $dsid derivative! could not find file $this->temp_file " . $return . ' ' . implode($jp2_output), 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
    }
    return $return;
  }

  /**
   * Returns array of arguements for convert function.
   *
   * @return array
   *   An array of arguements
   */
  function getMagickJp2Args() {
    $args = array();
    $args[] = " -define numrlvls=6";
    $args[] = " -define jp2:tilewidth=1024";
    $args[] = " -define jp2:tileheight=1024";
    $args[] = " -define jp2:rate=1.0";
    $args[] = " -define jp2:lazy";
    $args[] = " -define jp2:prg=rlcp";
    $args[] = " -define jp2:ilyrrates='0.015625,0.01858,0.0221,0.025,0.03125,0.03716,0.04419,0.05,0.0625, 0.075,0.088,0.1,0.125,0.15,0.18,0.21,0.25,0.3,0.35,0.4,0.5,0.6,0.7,0.84'";
    $args[] = " -define jp2:mode=int";
    return $args;
  }


  /**
   * convert a file to a jpg, if resize is not equal to 0 it will also be resized
   * @param string $dsid
   * @param string $label
   * @param string $resize
   * @return string
   *   
   */
  function jpg($dsid = 'JPEG', $label = 'JPEG image', $params = array('resize'=> '800')) {
    $return = MS_SYSTEM_EXCEPTION;
    $resize = $params['resize'];
    $this->log->lwrite('Starting processing', 'PROCESS_DATASTREAM', $this->pid, $dsid);
    $pathinfo = pathinfo($this->temp_file);
    $output_file = $pathinfo['dirname'] . DIRECTORY_SEPARATOR . $pathinfo['filename'] . '_JPG.jpg';
    $command_prefix = "convert $this->temp_file[0] -quality 100 -colorspace sRGB -flatten";
    if ($resize == '0') {
      $command = "$command_prefix $output_file 2>&1";
    }
    else {
      $command = "$command_prefix -resize $resize $output_file 2>&1";
    }
    $jpg_output = array();
    exec($command, $jpg_output, $return);
    $error_out = implode(', ', $jpg_output);
    if (file_exists($output_file)) {
      $log_message = "$dsid derivative created using ImageMagick with command - $command || SUCCESS";
      $return = $this->add_derivative($dsid, $label, $output_file, 'image/jpeg', $log_message);
      if ($return == MS_SUCCESS) {
        $this->log->lwrite("Successfully added the $dsid derivative!", $return, 'SUCCESS', $this->pid, $dsid, NULL, 'INFO');
      }
    }
    // Certain imagemagick errors maybe recoverable.
    elseif (strpos($error_out, 'error/tiff.c/ReadTIFFImage/1619') !== false) {
      $return = MS_FEDORA_EXCEPTION;
      $this->log->lwrite("Could not find the file '$output_file' for the Thumbail derivative, will try again!\nConvert output: " . implode(', ', $jpg_output) . "\nReturn value: $return", 'FAIL_DATASTREAM', $this->pid, 'JPG', NULL, 'ERROR');

    }
    else {
      $this->log->lwrite("Could not find the file '$output_file' for the Thumbail derivative!\nConvert output: " . implode(', ', $jpg_output) . "\nReturn value: $return", 'FAIL_DATASTREAM', $this->pid, 'JPG', NULL, 'ERROR');
      $return = MS_SYSTEM_EXCEPTION;
    }
    return $return;
  }

  function addBinaryThumbnail($dsid = 'TN', $label = 'JPEG image', $params = array('resize'=> '800')) {
    $return = $this->addDefaultThumbnail($dsid, $label, array('type' => 'binary'));
    return $return;
  }

}

?>
