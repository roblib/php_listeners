<?php

/**
 * Class to for testing to verify workflow of connect.php and config.xml
 * Does not verify connections to fedora just helps verify connect.php is 
 * loading the correct classes and functions since we are now allowing classes 
 * from multiple files
 * @author Paul Pound
 */



class MockDerivatives extends Derivative {
  
  /**
   * Creates all ocr datastreams in one call
   */
  function AllOCR($dsid = 'HOCR', $label = 'HOCR', $language = 'eng') {
    return "Called ALLOCR";
    
  }

  /**
   * creates the ENCODED_OCR stream and the OCR stream
   * @param string $file 
   */
  function createEncodedOcrStream($file) {
    return 'Called CreateEncodedOcrStream';
    }

  function OCR($dsid = 'OCR', $label = 'Scanned text', $language = 'eng') {
    return "Called OCR";
    
  }

  function HOCR($dsid = 'HOCR', $label = 'HOCR', $language = 'eng') {
    return "Called HOCR";
    
  }

  
}

?>