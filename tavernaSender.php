<?php
      require_once 'sender.php';
      require_once 'TavernaException.php';
      require_once 'tuque/HttpConnection.php';
                                  
class TavernaSender extends Sender
{ 

  public $curl_connect;
  
  function __construct($protocol,$hostname=null,$port=null,$context, $username=null,$password =null)
  {
    parent::__construct($protocol.'://'.$hostname.":".$port .'/'. $context."/rest/runs/",$password,$username);
    $this->curl_connect =new CurlConnection();
    $this->curl_connect->username = $this->username;
    $this->curl_connect->password = $this->password;
  }


  function set_ssl()
  {
	
    $this->curl_connect->sslVersion = 3;
    $this->curl_connect->verifyHost = FALSE;
    $this->curl_connect->verifyPeer = FALSE; 
  }
  
  //would like to update this function to use tuque HttpConnection class
  function send_Message($message)
  {
      if (!empty($message))
      {

	 $this->set_ssl();
	 $response = $this->curl_connect->postRequest($this->hostname,'string',$message,'Content-Type: application/vnd.taverna.t2flow+xml');
        
	 if($response['status'] !=201)
         { 
		throw new TavernaException($response['headers'].$response['content']);
	 }

	 return $response['headers'].$response['content'];
      }
      
      return null;
    }
  

  function parse_UUID($message)
  {
    if (!empty($message))
    {
        $uuid = substr($message, strrpos($message, $this->hostname)+strlen($this->hostname), 36);
        return $uuid;
    }
    
    return null;
  }

  //HttpConnection:putRequest  
  function run_t2flow($uuid)
  {
      if (!empty($uuid))
      {

        $url= $this->hostname.$uuid.'/status/';

	$this->set_ssl(); 

	$response = $curl_connect->putRequest($this->hostname,'string','Operating');

	if($response['status'] != 200)
        { 
		throw new TavernaException($response['headers'].$response['content']);
	}

	
	return $response['headers'].$response['content'];
 
      }
      return null;
 }
  

  //HttpConnection:getRequest
  function get_status($uuid)
  {
      if (!empty($uuid))
      {

	 $url=$this->hostname.$uuid."/status/";
	 $this->set_ssl();
	 $response = $this->curl_connect->getRequest($url,FALSE,NULL);
        
	 if($response['status'] !=200)
         { 
		throw new TavernaException($response['headers'].$response['content']);
	 }
	
	 return $response['headers'].$response['content'];
      }

      return null;
  }
  
  function delete_t2flow($uuid)
  {
    if (!empty($uuid))
    {

	$url= $this->hostname.$uuid.'/';

        $this->set_ssl();
	$response = $this->curl_connect->deleteRequest($url);

	if($response['status'] !=202)
        { 
		throw new TavernaException($response['headers'].$response['content']);
	}
 	return $response['headers'].$response['content'];
    }
    
    return null;
  } 

  //would like to use tuque here
  function add_input($uuid, $key, $value)
  {
      if (!empty($uuid) && !empty($key) && !empty($value))
      {

          $input = '<t2sr:runInput xmlns:t2sr="http://ns.taverna.org.uk/2010/xml/server/rest/">
                      <t2sr:value>'.$value.'</t2sr:value>             
                    </t2sr:runInput>';
	 
	 $url=$this->hostname.$uuid."/input/input/".$key;

	 $response = $this->curl_connect->putRequest($url,'string',$input);
        
	 if($response['status'] !=200)
         { 
		throw new TavernaException($response['headers'].$response['content']);
	 }

	 return $response['headers'].$response['content'];
      }
      
      return null;
  }  
     
}
?>
