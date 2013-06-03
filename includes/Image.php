<?php

require_once 'Derivatives.php';

class Image extends Derivative {

  function __destruct() {
    parent::__destruct();
  }

  function jp2($dsid = 'JP2', $label = 'Compressed jp2') {
    $this->log->lwrite('Starting processing', 'PROCESS_DATASTREAM', $this->pid, $dsid);
    if (file_exists($this->temp_file)) {
      try {
        $output_file = $this->temp_file . '_JP2.jp2';
        $command = 'kdu_compress -i ' . $this->temp_file . ' -o ' . $output_file . ' -rate 0.5 Clayers=1 Clevels=7 Cprecincts=\{256,256\},\{256,256\},\{256,256\},\{128,128\},\{128,128\},\{64,64\},\{64,64\},\{32,32\},\{16,16\} Corder=RPCL ORGgen_plt=yes ORGtparts=R Cblk=\{32,32\} Cuse_sop=yes 2>&1';
        $jp2_output = array();
        exec($command, $jp2_output, $return);
        if (file_exists($output_file)) {
          $log_message = "$dsid derivative created using kdu_compress with command - $command || SUCCESS";
          $this->add_derivative($dsid, $label, $output_file, 'image/jp2', $log_message);
        }
        else {
          $this->log->lwrite("Could not find the file '$output_file' for the HOCR derivative!\nTesseract output: " . implode(', ', $jp2_output) . "\nReturn value: $return", 'FAIL_DATASTREAM', $this->pid, 'JP2', NULL, 'ERROR');
          return $return;
        }
      } catch (Exception $e) {
        $this->log->lwrite("Could not create the $dsid derivative! " . $return . ' ' . implode(',', $jp2_output), 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
        unlink($output_file);
      }
    }
    else {
      $this->log->lwrite("Could not create the $dsid derivative! could not find file $this->temp_file " . $return . ' ' . implode($jp2_output), 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
    }
    return $return;
  }

  function tn($dsid = 'TN', $label = 'Thumbnail', $height = '200', $width = '200') {
    $this->log->lwrite('Starting processing', 'PROCESS_DATASTREAM', $this->pid, $dsid);
    try {
      $output_file = $this->temp_file . '_TN.jpg';
      $command = "convert -thumbnail " . $height . "x" . $width . " $this->temp_file $output_file 2>&1";
      exec($command, $tn_output = array(), $return);
      if (file_exists($output_file)) {
        $log_message = "$dsid derivative created using ImageMagick with command - $command || SUCCESS";
        $this->add_derivative($dsid, $label, $output_file, 'image/jpeg', $log_message);
      }
      else {
        $this->log->lwrite("Could not find the file '$output_file' for the Thumbail derivative!\nTesseract output: " . implode(', ', $tn_output) . "\nReturn value: $return", 'FAIL_DATASTREAM', $this->pid, 'TN', NULL, 'ERROR');
        return $return;
      }
      } catch (Exception $e) {
      $this->log->lwrite("Could not create the $dsid derivative!", $return . ' ' . implode(',', $tn_output), 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
      unlink($output_file);
    }
    return $return;
  }

  /**
   * convert a file to a jpg, if resize is not equal to 0 it will also be resized
   * @param string $dsid
   * @param string $label
   * @param string $resize
   * @return string
   *   
   */
  function jpg($dsid = 'JPEG', $label = 'JPEG image', $resize = '800') {
    $this->log->lwrite('Starting processing', 'PROCESS_DATASTREAM', $this->pid, $dsid);
    try {
      $pathinfo = pathinfo($this->temp_file);
      $output_file = $pathinfo['dirname'] . DIRECTORY_SEPARATOR . $pathinfo['filename'] . '_JPG.jpg';
      if ($resize == '0') {
        $command = "convert $this->temp_file $output_file 2>&1";
      }
      else {
        $command = "convert $this->temp_file -resize $resize $output_file 2>&1";
      }
      exec($command, $jpg_output = array(), $return);
      if (file_exists($output_file)) {
        $log_message = "$dsid derivative created using ImageMagick with command - $command || SUCCESS";
        $this->add_derivative($dsid, $label, $output_file, 'image/jpeg', $log_message);
      }
      else {
        $this->log->lwrite("Could not find the file '$output_file' for the Thumbail derivative!\nTesseract output: " . implode(', ', $JPG_output) . "\nReturn value: $return", 'FAIL_DATASTREAM', $this->pid, 'JPG', NULL, 'ERROR');
        return $return;
      }

      } catch (Exception $e) {
      $this->log->lwrite("Could not create the $dsid derivative!", $return . ' ' . implode(',', $jpg_output), 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
      unlink($output_file);

      }
    return $return;
  }

}

?>
