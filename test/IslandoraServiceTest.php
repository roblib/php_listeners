<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

require_once '../soap_serv.php';
class IslandoraServiceTest extends PHPUnit_Frame_TestCase {
  
  protected $islandoraServ;
  public function setUp()
  {
    $this->islandoraServ = new IslandoraService();
  }
  
  public function testAllOcr()
  {
    $result = $this->islandoraServ->allOcr('islandora:377', 'JPG', 'NEW_JPG', 'allOcr function test', 'eng');
    $this->assertEquals(0);
  }
}
?>
