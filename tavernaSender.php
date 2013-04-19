<?php
      require_once 'sender.php';
      require_once 'TavernaException.php';
                                  
class TavernaSender extends Sender
{
/*
* intitiallize the
*/
  function __construct($hostname=null,$port=null,$password=null,$username =null)
  {
    parent::__construct("http://".$hostname.":".$port."/tavernaserver/rest/runs/",$password,$username);
  }
  
  function send_Message($message)
  {
      if (!empty($message))
      {
          $ch = curl_init();
          curl_setopt($ch, CURLOPT_URL, $this->hostname);
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
          curl_setopt($ch, CURLOPT_POST, true);
          curl_setopt($ch, CURLOPT_HEADER, true);
          curl_setopt($ch, CURLOPT_USERPWD, "$this->username:$this->password");
          curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/vnd.taverna.t2flow+xml'));
          curl_setopt($ch, CURLOPT_POSTFIELDS, $message);
          curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
          $content = curl_exec($ch);
    
          if (curl_getinfo($ch,CURLINFO_HTTP_CODE) != 201)
          {
              curl_close($ch);
              throw new TavernaException($content);
          }
          
          curl_close($ch);
          return $content;
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
  
  function run_t2flow($uuid)
  {
      if (!empty($uuid))
      {
          $ch = curl_init();
          curl_setopt($ch, CURLOPT_URL, $this->hostname.$uuid."/status/");
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);                   
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
      }
      return null;
  }
  
  function get_status($uuid)
  {
      if (!empty($uuid))
      {
          $ch = curl_init();
          curl_setopt($ch, CURLOPT_URL, $this->hostname.$uuid."/status/");
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);                   
          curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
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
      }
      return null;
  }
  
  function delete_t2flow($uuid)
  {
    if (!empty($uuid))
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->hostname.'/'.$uuid.'/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);                   
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $this->username.':'.$this->password);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('text/plain'));
        $result = curl_exec($ch);
    
        if (curl_getinfo($ch,CURLINFO_HTTP_CODE) != 202)
        {
            curl_close($ch);
            throw new TavernaException($result);
        }
    
        curl_close($ch);
        return $result;
    }
    
    return null;
  }
     
  function add_input($uuid, $key, $value)
  {
      if (!empty($uuid) && !empty($key) && !empty($value))
      {
          $input = '<t2sr:runInput xmlns:t2sr="http://ns.taverna.org.uk/2010/xml/server/rest/">
                      <t2sr:value>'.$value.'</t2sr:value>             
                    </t2sr:runInput>';
          $ch = curl_init();
          curl_setopt($ch, CURLOPT_URL, $this->hostname.$uuid."/input/input/".$key);
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);                   
          curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
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
      }
      
      return null;
  }  
     
}
?>
