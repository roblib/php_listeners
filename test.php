<?php

require_once "PHPUnit/Autoload.php";
require_once "soap_serv.php";
require_once "FedoraMock.php";

// An example unit test
class IslandoraServiceTest extends PHPUnit_Framework_TestCase {

	private $service;

	public function __construct() {
		// I'm not really sure what these do but things blow up without them
		$this->backupGlobals = false;
		$this->backupStaticAttributes = false;
		
		$this->service = new IslandoraService();
		$this->service->fedora_connect = getFedoraMock($this);
	}

	public function testRead() {
		$content = $this->service->read("islandora:313", "TEST_TXT", "txt");
		$this->assertEquals("test text", base64_decode($content));
	}
}

?>
