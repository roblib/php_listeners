<?php
      require_once 'sender.php';
      require_once 'TavernaException.php';
      require_once 'tuque/HttpConnection.php';
                                  
class TavernaSender extends Sender
{ 


  function __construct($protocol,$hostname=null,$port=null,$context, $username=null,$password =null)
  {
    parent::__construct($protocol.'://'.$hostname.":".$port .'/'. $context."/rest/runs/",$password,$username);
  }




  function set_ssl()
  {
          curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
          curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
          curl_setopt($ch, CURLOPT_SSLVERSION, 3);
  }
  
  //would like to update this function to use tuque HttpConnection class
  function send_Message($message)
  {
      if (!empty($message))
      {
 /**         $ch = curl_init();
          curl_setopt($ch, CURLOPT_URL, $this->hostname);
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
          curl_setopt($ch, CURLOPT_POST, true);

	  $this->set_ssl();

          curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
          curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
          curl_setopt($ch, CURLOPT_SSLVERSION, 3);

          curl_setopt($ch, CURLOPT_HEADER, true);
          curl_setopt($ch, CURLOPT_USERPWD, "$this->username:$this->password");
          curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/vnd.taverna.t2flow+xml'));
          curl_setopt($ch, CURLOPT_POSTFIELDS, $message);
          curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
          $content = curl_exec($ch);
          $code = curl_getinfo($ch,CURLINFO_HTTP_CODE);
          print('taverna host = '.$this->hostname);
          print("post code = ".$code);
          print('username = '. $this->username.' password '.$this->password);
          if ($code != 201)
          {
              curl_close($ch);
              throw new TavernaException($content);
          }
          
          curl_close($ch);
          return $content;
*/


         $curl_connect =new CurlConnection();
	 $curl_connect->username = $this->username;
	 $curl_connect->password = $this->password;

	 // TO DO: seperate this to an sigal function. 
	 $curl_connect->sslVersion = 3;
	 $curl_connect->verifyHost = FALSE;
	 $curl_connect->verifyPeer = FALSE; 

	 $response = $curl_connect->postRequest($this->hostname,'string',$message,'Content-Type: application/vnd.taverna.t2flow+xml');
        
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
/**          $ch = curl_init();
          curl_setopt($ch, CURLOPT_URL, $this->hostname.$uuid."/status/");
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);    
          curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
          curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
          curl_setopt($ch, CURLOPT_SSLVERSION, 3);
          curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
          curl_setopt($ch, CURLOPT_HEADER, true);
          curl_setopt($ch, CURLOPT_USERPWD, "$this->username:$this->password");
          curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/plain'));
          curl_setopt($ch, CURLOPT_POSTFIELDS, "Operating");
          curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
          $result = curl_exec($ch);
      
          if (curl_getinfo($ch,CURLINFO_HTTP_CODE) != 200)
          {
              curl_close($ch);
              throw new TavernaException($result);
          }
      
      curl_close($ch);
      return $result;
*/

        $url= $this->hostname.$uuid.'/status/';
	$curl_connect =new CurlConnection();
	$curl_connect->username = $this->username;
	$curl_connect->password = $this->password;

	// TO DO: seperate this to an sigal function. 
	$curl_connect->sslVersion = 3;
	$curl_connect->verifyHost = FALSE;
	$curl_connect->verifyPeer = FALSE; 

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

/**        $ch = curl_init();
          curl_setopt($ch, CURLOPT_URL, $this->hostname.$uuid."/status/");
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);                   
          curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
          curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
          curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
          curl_setopt($ch, CURLOPT_SSLVERSION, 3);
          curl_setopt($ch, CURLOPT_HEADER, true);
          curl_setopt($ch, CURLOPT_USERPWD, "$this->username:$this->password");
          curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/plain'));
          curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
          $result = curl_exec($ch);
      
          if (curl_getinfo($ch,CURLINFO_HTTP_CODE) != 200)
          {
              curl_close($ch);
              throw new TavernaException($result);
          }
          curl_close($ch);
*/
	 $url=$this->hostname.$uuid."/status/";

         $curl_connect =new CurlConnection();
	 $curl_connect->username = $this->username;
	 $curl_connect->password = $this->password;

	 // TO DO: seperate this to an sigal function. 
	 $curl_connect->sslVersion = 3;
	 $curl_connect->verifyHost = FALSE;
	 $curl_connect->verifyPeer = FALSE; 

	 $response = $curl_connect->getRequest($url,'string',$message,$input);
        
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

/**      $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->hostname.'/'.$uuid.'/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);                   
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_SSLVERSION, 3);
        curl_setopt($ch, CURLOPT_USERPWD, $this->username.':'.$this->password);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('text/plain'));
        $result = curl_exec($ch);
    
        if ($curl_getinfo($ch,CURLINFO_HTTP_CODE) != 202)
        {
            curl_close($ch);
            throw new TavernaException($result);
        }
    
        curl_close($ch);
        return $result;
*/

	$url= $this->hostname.$uuid.'/';

        $curl_connect =new CurlConnection();
	$curl_connect->username = $this->username;
	$curl_connect->password = $this->password;

	// TO DO: seperate this to an sigal function. 
	$curl_connect->sslVersion = 3;
	$curl_connect->verifyHost = FALSE;
	$curl_connect->verifyPeer = FALSE; 

	 $response = $curl_connect->deleteRequest($url);
        
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
/*          $ch = curl_init();
          curl_setopt($ch, CURLOPT_URL, $this->hostname.$uuid."/input/input/".$key);
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);                   
          curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
          curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
          curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
          curl_setopt($ch, CURLOPT_SSLVERSION, 3);
          curl_setopt($ch, CURLOPT_HEADER, true);
          curl_setopt($ch, CURLOPT_USERPWD, "$this->username:$this->password");
          curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/xml'));
          curl_setopt($ch, CURLOPT_POSTFIELDS, $input);
          curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
          $result = curl_exec($ch);
          
          if (curl_getinfo($ch,CURLINFO_HTTP_CODE) != 200)
          {
              curl_close($ch);
              throw new TavernaException($result);
          }
          
          curl_close($ch);
          return $result;
*/
	 $url=$this->hostname.$uuid."/input/input/".$key;

         $curl_connect =new CurlConnection();
	 $curl_connect->username = $this->username;
	 $curl_connect->password = $this->password;

	 // TO DO: seperate this to an sigal function. 
	 $curl_connect->sslVersion = 3;
	 $curl_connect->verifyHost = FALSE;
	 $curl_connect->verifyPeer = FALSE; 

	 $response = $curl_connect->putRequest($url,'string',$input);
        
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
