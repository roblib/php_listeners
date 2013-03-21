<?php
require_once 'sender.php';

class TavernaSender extends Sender
{
/*
* intitiallize the
*/
  function __construct($hostname=null,$password=null,$username =null)
  {
    parent::__construct($hostname.":8080/tavernaserver/rest/runs/",$password,$username);
  }
  
  function send_Message($message = null)
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
//      $uuid = substr($content, strrpos($content, $TavernaUrl) + strlen($TavernaUrl), 36);
//            $this->log->lwrite("Status of post to rest/runs: ".curl_getinfo($ch,CURLINFO_HTTP_CODE));
//            $this->log->lwrite("UUID: ".$uuid);
      echo $content;
      curl_close($ch);
      return $content;
    }
  

  function prase_UUID($message = null)
  {
    $uuid = substr($message, strrpos($message, $this->hostname)+strlen($this->hostname), 36);
//    echo $uuid." ".$this->hostname;
    return $uuid;
  }
  
  function run_t2flow($uuid = null)
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
      curl_close($ch);
//            $this->log->lwrite("Results of put to rest/uuid/status: ".$result);
    return $result;
  }
  
  function delete_t2flow($uuid = null)
  {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $this->hostname.'/'.$uuid.'/');
//    echo $this->hostname."/".$uuid."/status";
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);                   
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_USERPWD, $this->username.':'.$this->password);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('text/plain'));
//    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array('Operating')));
    curl_setopt($ch, CURLOPT_HTTPGET, 1); 
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
  }
     
}

?>