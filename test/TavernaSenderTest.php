<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 * 
 */
require_once 'tavernaSender.php';
require_once "PHPUnit/Autoload.php";


class TavernaSenderTest extends PHPUnit_Framework_TestCase {

  public $tavernaSender;
  public $config;
  public $testT2flow;
  
  public function __construct() {
    $this->backupGlobals = false;
    $this->backupStaticAttributes = false;

    $location = getenv('PHP_LISTENERS_PATH');
    if ($location == "") {
      $location = "/opt/php_listeners";
    }

    $content = file_get_contents($location . "/config.xml");
    try {
      $this->config = new SimpleXMLElement($content);
      } catch (Exception $e) {
      print("XML file error");
      }

    $protocol = $this->config->taverna->protocol;
    $host = $this->config->taverna->host;
    $context = $this->config->taverna->context;
    $port = $this->config->taverna->port;
    $username = $this->config->taverna->username;
    $password = $this->config->taverna->password;

    $this->tavernaSender = new TavernaSender($protocol, $host, $port, $context, $username, $password);
    
    $this->testT2flow = file_get_contents('test/TestData/testT2flow.t2flow');
  }

  public function testSetSSLStatus() {
    $ssl_status = $this->tavernaSender->ssl_status;
    $ssl_status_in_config = $this->config->taverna->ssl;
    if($ssl_status)
    {
      $this->assertEquals('true',  strtolower($ssl_status_in_config));
    }
    else
    {
      $this->assertEquals('false',  strtolower($ssl_status_in_config));
    }
  }
  
  public function testSentMessage()
  {
    $returns = $this->tavernaSender->send_Message($this->testT2flow);
    $this->assertContains('201 Created',$returns); 
  }
  
  public function testParseUUID()
  {
    $returns = $this->tavernaSender->send_Message($this->testT2flow);
    $uuid=$this->tavernaSender->parse_UUID($returns);
    $uuid_format = "/";
    $digit_format = "[0-9a-z]";
    for($i=0;$i<8;$i++)
    {
      $uuid_format .= $digit_format;
    }
    $uuid_format .= '-';
    for($i=0;$i<3;$i++)
    {
      for($j=0;$j<4;$j++)
      {
        $uuid_format .= $digit_format;
      }
      $uuid_format .= '-';
    }
    for ($i=0;$i<12;$i++)
    {
      $uuid_format .= $digit_format;
    }
    $uuid_format.= "/";
    $this->assertRegExp($uuid_format,$uuid);
  }
  
  public function testGetStatus()
  {
    $returns = $this->tavernaSender->send_Message($this->testT2flow);
    $uuid=$this->tavernaSender->parse_UUID($returns);
    $status = $this->tavernaSender->get_status($uuid);
    $this->assertEquals('Initialized',$status);
  }
  
  public function testDelete()
  {
    $returns = $this->tavernaSender->send_Message($this->testT2flow);
    $uuid=$this->tavernaSender->parse_UUID($returns);
    $start_status = $this->tavernaSender->get_status($uuid);
    $this->assertEquals('Initialized',$start_status);
    $this->tavernaSender->delete_t2flow($uuid);
    $delete_status=0;
    try
    {
      $this->tavernaSender->get_status($uuid);
    }  catch (Exception $e)
    {
      $delete_status=$e->getCode();
    }
    $this->assertEquals(404,$delete_status);
  }
  
  public function testRunT2flow()
  {
    $returns = $this->tavernaSender->send_Message($this->testT2flow);
    $uuid=$this->tavernaSender->parse_UUID($returns);
    $status = $this->tavernaSender->get_status($uuid);
    $this->assertEquals('Initialized',$status);
    $run_result = $this->tavernaSender->run_t2flow($uuid);
    $this->assertContains('200 OK',$run_result);
    $run_array = array ('Operating','Finished');
    $run_status =  $this->tavernaSender->get_status($uuid);
    $this->assertContains($run_status,$run_array);
    $this->tavernaSender->delete_t2flow($uuid);
  }
  
  public function testAddInput()
  {
    {
    $returns = $this->tavernaSender->send_Message($this->testT2flow);
    $uuid=$this->tavernaSender->parse_UUID($returns);
    $add_result = $this->tavernaSender->add_input($uuid, 'pid', 'islandora:313');
    $this->assertContains('200 OK',$add_result);
    
  }
  }
 

}

?>
