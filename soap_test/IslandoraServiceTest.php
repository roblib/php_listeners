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
    $this->islandoraServ->fedora_connect = getFedoraMock($this);
  }

  function testRead() {
    $result = $this->islandoraServ->read('islandora:313', 'TEST_TXT', NULL);
    $this->assertEquals('test text', base64_decode($result));
  }

  function testWrite() {
    $result = $this->islandoraServ->write('islandora:313', 'STANLEY_JPG', 'tiny Stanley', base64_encode(file_get_contents("soap_test/stanly.jpg")), "image/jpeg");
    $this->assertEquals($result, 'success');
  }

  function testAllOcr() {
    $result = $this->islandoraServ->allOcr('islandora:377', 'JPEG', 'ALL_OCR_TEST', 'allOcr function test', 'eng');
    $this->assertEquals(0, $result);
  }

  function testTechmd() {
    $result = $this->islandoraServ->techmd('islandora:313', 'EXIF', 'Technical metadata test');
    $this->assertEquals(0, $result);
  }

  function testScholarPolicy() {
    $result = $this->islandoraServ->scholarPolicy('islandora:313', 'OBJ', 'SCHOLAR_POLICY_TEST', 'scholar policy function test');
    $this->assertEquals(0, $result);
  }

  function testAddImageDimensionsToRels() {
    $result = $this->islandoraServ->addImageDimensionsToRels('islandora:313', 'OBJ', 'RELS_INT_TEST', 'addImageDimensionsToRels function test');
    $this->assertEquals(0, $result);
  }

  function testOcr() {
    $result = $this->islandoraServ->ocr('islandora:377', 'JPEG', 'OCR_TEST', 'ocr function test', 'eng');
    $this->assertEquals(0, $result);
  }

  function testHOcr() {
    $result = $this->islandoraServ->hOcr('islandora:377', 'JPEG', 'HOCR_TEST', 'hOcr function test', 'eng');
    $this->assertEquals(0, $result);
  }

  public function testEncodedOcr() {
    $result = $this->islandoraServ->encodedOcr('islandora:377', 'JPEG', 'ENCODED_OCR_TEST', 'eng');
    $this->assertEquals(0, $result);
  }

  function testScholarPdfa() {
    $result = $this->islandoraServ->scholarPdfa('islandora:313', 'JPG', 'SCHOLAR_PDFA_TEST', 'scholar pdfa test');
    $this->assertEquals(0, $result);
  }

  function testTn() {
    $result = $this->islandoraServ->tn('islandora:313', 'JPG', 'TN_TEST', 'tn function test', 400, 400);
    $this->assertEquals(0, $result);
  }

}

?>
