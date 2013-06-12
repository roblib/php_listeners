<?php

require_once "PHPUnit/Autoload.php";
require_once "FedoraMock.php";
require_once 'soap_serv.php';

class IslandoraServiceTest extends PHPUnit_Framework_TestCase {

  private $islandoraServ;

  function __construct() {
    $this->backupGlobals = false;
    $this->backupStaticAttributes = false;

    $this->islandoraServ = new IslandoraService();
  }
  
  function testAuthorize()
  {
    $result = $this->islandoraServ->authorize('taverna', 'testDoNotExist','read');
    $this->assertEquals(false, $result);
    $result = $this->islandoraServ->authorize('taverna', 'taverna','read');
    $this->assertEquals(true, $result);
  }

  function testRead() {
    $result = $this->islandoraServ->read('islandora:313', 'TEST_TXT', NULL,'taverna','taverna');
    $this->assertEquals('test text', base64_decode($result));
    $result = $this->islandoraServ->read('islandora:313', 'TEST_TXT', NULL,'read','notexist');
    $this->assertNull($result);
  }

  function testWrite() {
    $result = $this->islandoraServ->write('islandora:313', 'STANLEY_JPG', 'tiny Stanley', base64_encode(file_get_contents("test/TestData/stanley.jpg")), "image/jpeg",'taverna','taverna');
    $this->assertEquals($result, 'success');    
    $result = $this->islandoraServ->write('islandora:313', 'STANLEY_JPG', 'tiny Stanley', base64_encode(file_get_contents("test/TestData/stanley.jpg")), "image/jpeg",'taverna','notexist');
    $this->assertNull($result);
  }

  function testAllOcr() {
    $result = $this->islandoraServ->allOcr('islandora:377', 'JPEG', 'ALL_OCR_TEST', 'allOcr function test', 'eng','taverna','taverna');
    $this->assertEquals(0, $result);
      $result = $this->islandoraServ->allOcr('islandora:377', 'JPEG', 'ALL_OCR_TEST', 'allOcr function test', 'eng','allOcr','notexist');
    $this->assertEquals(-4, $result);
  }
  
  function testOcr() {
    $result = $this->islandoraServ->ocr('islandora:377', 'JPEG', 'OCR_TEST', 'ocr function test', 'eng','taverna','taverna');
    $this->assertEquals(0, $result);
    $result = $this->islandoraServ->ocr('islandora:377', 'JPEG', 'OCR_TEST', 'ocr function test', 'eng','ocr','notexist');
    $this->assertEquals(-4, $result);
  }
  
   function testHOcr() {
    $result = $this->islandoraServ->hOcr('islandora:377', 'JPEG', 'HOCR_TEST', 'hOcr function test', 'eng','taverna','taverna');
    $this->assertEquals(0, $result);
    $result = $this->islandoraServ->hOcr('islandora:377', 'JPEG', 'HOCR_TEST', 'hOcr function test', 'eng','hOcr','notexist');
    $this->assertEquals(-4, $result);
  }

  public function testEncodedOcr() {
    $result = $this->islandoraServ->encodedOcr('islandora:377', 'JPEG', 'ENCODED_OCR_TEST','Scanned text', 'eng','taverna','taverna');
    $this->assertEquals(0, $result);
    $result = $this->islandoraServ->encodedOcr('islandora:377', 'JPEG', 'ENCODED_OCR_TEST', 'Scanned text','eng','encodedOcr','notexist');
    $this->assertEquals(-4, $result);
  }
  
  public function testJpg()
  {
    $result =$this->islandoraServ->jpg('islandora:377', 'JPEG', 'JPG_TEST', 'JPEG image', '800', 'taverna','taverna');
    $this->assertEquals(0, $result);
    $result =$this->islandoraServ->jpg('islandora:377', 'JPEG', 'JPG_TEST', 'JPEG image', '800', 'jpg','notexist');
    $this->assertEquals(-4, $result);
  }
  
  public function testJp2()
  {
    $result =$this->islandoraServ->jp2('islandora:313', 'OBJ', 'JP2_TEST', 'JPEG image', 'taverna','taverna');
    $this->assertEquals(0, $result);
    $result =$this->islandoraServ->jp2('islandora:313', 'OBJ', 'JP2_TEST', 'JPEG image', 'jp2','notexist');
    $this->assertEquals(-4, $result);
  }
 
  function testTn() {
    $result = $this->islandoraServ->tn('islandora:313', 'JPG', 'TN_TEST', 'tn function test', 400, 400,'taverna','taverna');
    $this->assertEquals(0, $result);
    $result = $this->islandoraServ->tn('islandora:313', 'JPG', 'TN_TEST', 'tn function test', 400, 400,'tn','notexist');
    $this->assertEquals(-4, $result);
  }

  function testTechmd() {
    $result = $this->islandoraServ->techmd('islandora:313', 'EXIF',"TECHMD_TEST", 'Technical metadata test', 'taverna','taverna');
    $this->assertEquals(0, $result);
    $result = $this->islandoraServ->techmd('islandora:313', 'EXIF',"TECHMD_TEST", 'Technical metadata test', 'techmd','notexist');
    $this->assertEquals(-4, $result);
  }

  function testScholarPolicy() {
    $result = $this->islandoraServ->scholarPolicy('islandora:313', 'OBJ', 'SCHOLAR_POLICY_TEST', 'scholar policy function test', 'taverna','taverna');
    $this->assertEquals(0, $result);
    $result = $this->islandoraServ->scholarPolicy('islandora:313', 'OBJ', 'SCHOLAR_POLICY_TEST', 'scholar policy function test', 'scholarPolicy','notexist');
    $this->assertEquals(-4, $result);
  }

  function testAddImageDimensionsToRels() {
    $result = $this->islandoraServ->addImageDimensionsToRels('islandora:313', 'OBJ', 'RELS_INT_TEST', 'addImageDimensionsToRels function test', 'taverna','taverna');
    $this->assertEquals(0, $result);
    $result = $this->islandoraServ->addImageDimensionsToRels('islandora:313', 'OBJ', 'RELS_INT_TEST', 'addImageDimensionsToRels function test', 'addImageDimensionsToRels','notexist');
    $this->assertEquals(-4, $result);
  }


  function testScholarPdfa() {
    $result = $this->islandoraServ->scholarPdfa('islandora:313', 'JPG', 'SCHOLAR_PDFA_TEST', 'scholar pdfa test', 'taverna','taverna');
    $this->assertEquals(0, $result);
    $result = $this->islandoraServ->scholarPdfa('islandora:313', 'JPG', 'SCHOLAR_PDFA_TEST', 'scholar pdfa test', 'scholarPdfa','notexist');
    $this->assertEquals(-4, $result);
  }


}

?>
