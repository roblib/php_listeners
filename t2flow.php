<?php
/*
* this class is to modify a t2flow. Providing services of the t2flow and provide
* the service that read t2flow from a url in fedora.
* 
* @ author: CS482 2013 team2,   28th,Feb,2013
*/
require_once 'datastream.php';
require_once 'tavernaSender.php';
 
class T2Flow extends Datastream
{ 

public $sender=null; 
//public $uuid = null;   

  /**
  * initial variables in this class with pid and dsid
  */
  
  function  __construct($pid =null,$dsid=null,$taverna_host=null,$username=null,$password = null)
  {
  
      parent::__construct($pid,$dsid);
 //     echo $pid.$dsid.$taverna_host;
      $this->sender = new TavernaSender($taverna_host,$username,$password);
  //    echo $this->pid."".$this->dsid;
  } 
  
  /**
   *
   */     
  
  function send_to_taverna()
  {
 //     echo $this->pid."".$this->dsid;
//      $sender = new TavernaSender($taverna_host,$username,$password);
      $result = $this->sender->send_Message($this->get_datastream());
//      echo $result;
      return $result;
  
  }
  
  function run_t2flow()
  {
    $message = $this->send_to_taverna();
    //echo "message: ".$message."\n";
    $uuid = $this->sender->parse_UUID($message);
    echo 'uuid'.$uuid."\n";
    $result = $this->sender->run_t2flow($uuid);
    //echo "run:".$result;
    return $result;
  } 
/*
* get the t2flow from fedora
*/
/*  function get_t2flow_from_fedora()
  {
      //using the url
      $url= 'http://192.168.56.195:8080/fedora/objects/'.$pid.'/datastreams/'.$dsid.'/content';
      //echo $url;
      $datastream_getter = new DatastreamGetter($url);
  
      $datastream_getter->update_datastream_from_fedora();
      $this->t2flow_data = $datastream_getter->get_datastream();
  }
  
  function get_t2flow()
  {
    return $this->t2flow_data;
  }
  
  function get_t2flow_pid()
  {
    return $this->pid;
  }  */
  
  
}

?>
