<?php

require_once "PHPUnit/Autoload.php";
require_once "soap_serv.php";

// An example unit test
class IslandoraServiceTest extends PHPUnit_Framework_TestCase {

	public function __construct() {
		// I'm not really sure what these do but things blow up without them
		$this->backupGlobals = false;
		$this->backupStaticAttributes = false;
	}

	public function testRead() {
		$service = new IslandoraService();
		// ensure you actually have something in Fedora that matches this
		$content = $service->read("islandora:313", "TEST_TXT", "txt");
		$this->assertEquals("test text\n", base64_decode($content));
	}
}

?>
