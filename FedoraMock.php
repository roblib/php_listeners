<?php

require_once 'PHPUnit/Autoload.php';
require_once 'FedoraConnect.php';

function getFedoraMock($test_case) {

	$fedora_mock = $test_case->getMock('FedoraConnection', array(
		'getDatastream'
	));
	$repo_mock = getRepoMock($test_case);
	
	$fedora_mock->expects($test_case->any())
				->method('__get')
				->with($test_case->equalTo('repository'))
				->will($test_case->returnValue($repo_mock));

	$fedora_mock->expects($test_case->any())
				->method('getDatastream')
				->with($test_case->equalTo('islandora:313'),
					$test_case->equalTo('TEST_TXT'))
				->will($test_case->returnValue((object)array(
					'content' => "test text"
				)));

	return $fedora_mock;
}

function getRepoMock($test_case) {

	$repo_mock = $test_case->getMockBuilder('FedoraRepository')
						->disableOriginalConstructor()
						->getMock();
	
	$fedora_objects = array(
		'islandora:313' => getMockFedoraObject($test_case, array(
			'TEST_TXT' => 'test text'
		))
	);

	$islandora313 = $test_case->getMockBuilder('FedoraObject')
						->disableOriginalConstructor()
						->getMock();
	
	$repo_mock->expects($test_case->any())
			->method('getObject')
			->with($test_case->equalTo("islandora:313"))
			->will($test_case->returnValue($islandora313));

	return $repo_mock;
}

function getMockFedoraObject($test_case, $datastreams) {

	$object = $test_case->getMockBuilder('FedoraObject')
						->disableOriginalConstructor()
						->getMock();

	foreach ($datastreams as $dsid => $content) {
		$object->expects($test_case->any())
			->method('getDatastream')
			->with($test_case->equalTo($dsid))
			->will($test_case->returnValue($content));
	}

	return $object;
}

?>
