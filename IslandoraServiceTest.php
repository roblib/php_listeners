<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

require_once 'soap_serv.php';


class IslandoraServiceTest extends PHPUnit_Framework_TestCase {

  protected $islandoraServ;

  public function setUp() {
    $this->islandoraServ = new IslandoraService();
    $this->islandoraServ->IslandoraService();
  }

  /**
   * @depends allOcr
   */
  public function testAllOcr() {
    $result = $this->islandoraServ->allOcr('islandora:377', 'JPG', 'ALL_OCR_TEST', 'allOcr function test', 'eng');
    $this->assertEquals(0,$result);
  }
  
  /**
   * @depends techmd
   */
  public function testTechmd() {
    $result = $this->islandoraServ->techmd('islandora:313', 'EXIF', 'Technical metadata test');
    $this->assertEquals(0,$result);
  }
  
  /**
   * @depends scholarPolicy
   */
  public function testScholarPolicy() { 
    $result = $this->islandoraServ->scholarPolicy('islandora:313', 'OBJ', 'SCHOLAR_POLICY_TEST','scholar policy function test');
    $this->assertEquals(0,$result);
  }
  
  /**
   * @depends addImageDimensionsToRels
   */
  public function testAddImageDimensionsToRels() {
    $result =  $this->islandoraServ->addImageDimensionsToRels('islandora:313', 'OBJ', 'RELS_INT_TEST', 'addImageDimensionsToRels function test');
    $this->assertEquals(0,$result);
  }

  /**
   * @depends ocr
   */
  public function testOcr() {
    $result = $this->islandoraServ->ocr('islandora:377', 'OCR', 'OCR_TEST', 'ocr function test', 'eng');
    $this->assertEquals(0,$result);
  }

  /**
   * @depends HOCR
   */
  public function testHOcr() {
    $result = $this->islandoraServ->hOcr('islandora:377', 'JPEG', 'HOCR_TEST', 'hOcr function test', 'eng');
    $this->assertEquals(0,$result);
  }

  /**
   * @depends encodedOcr
   */
  public function testEncodedOcr() {
    $result = $this->islandoraServ->encodedOcr('islandora:377', 'JPEG','ENCODED_OCR_TEST', 'eng');
    $this->assertEquals(0,$result);
  }

  /**
   * @depends scholarPdfa
   */
  public function testScholarPdfa() {
    $result = $this->islandoraServ->scholarPdfa('islandora:313', 'JPG', 'SCHOLAR_PDFA_TEST', 'scholar pdfa test');
    $this->assertEquals(0,$result);
  }
  
  /**
   * @depends tn
   */
  public function testTn()
  {
    $result = $this->islandoraServ->tn('islandora:313', 'JPG', 'TN_TEST', 'tn function test', 400, 400);
    $this->assertEquals(0,$result);
  }
  

}

?>
