<?php
/**
* this class is to modify a t2flow. Providing services of the t2flow and provide
* the service that read t2flow from a url in fedora.
* 
* @ author: CS482 2013 team2,   28th,Feb,2013
*/

class Datastream
{ 
public $pid;              //the pid of the content model which contain the datastream.
public $dsid;             //the dsid of datastream
public $datastream;       //t2flow string to store the t2flow file
public $datastream_url;    //url of t2flow in fedora

  /**
  * initial variables in this class
  */
  function  __construct($pid = null,$dsid=null)
  {
    $this->pid = $pid;
    $this->dsid = $dsid;
//    echo 'dsid'.$this->dsid;
    $this->datastream_url='';
    $this->datastream = '';
    
  }
  
   function __destruct() 
   {
    $this->pid;              //the pid of the content model which contain the datastream.
    $this->dsid;             //the dsid of datastream
    $this->datastream;       //t2flow string to store the t2flow file
    $this->datastream_url; 
   }
  
  /**
  * get url from pid and dsid.
  * 
  * $host should contains the  protocal and ip address or dormin name of fedora 
  *  server.
  * For Example: http:// XXX.XXX.XXX.XXX
  * no '/' after the domain name or ip address  
  * 
  */  

  public function prase_url($hostname = null)
  {
 //   echo $this->dsid.'
 //   ';
    $this->datastream_url = $hostname.':8080/fedora/objects/'.$this->pid.'/datastreams/'.$this->dsid.'/content';
    echo $hostname;
    echo $this->datastream_url."\n";
  } 
  
  function get_dsid()
  {
    return $this->dsid;
  }
  
  function get_pid()
  {
    return $this->pid;
  }
  
  /**
   * @Author: 
   * Ian
   * James
   * Richard   
   */           
  function update_datastream_from_fedora()
  {
    echo $this->datastream_url."\n";
    $ch = curl_init();
    curl_setopt ($ch, CURLOPT_URL,$this->datastream_url); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
    $this->datastream =  curl_exec($ch);
    curl_close($ch);
    
//    echo "updating".$this->datastream."\n";
  }
  
  function set_dsid($dsid=null)
  {
    $this->dsid = $dsid;
  }
  
  function set_pid($pid = null)
  {
    $this->pid=$pid;
  }
  /**
   *  set new url
   *  
   */      
  function set_datastream_url($url = null)
  {
    $this->datastream_url = $url;
  }
  
  /**
   *  ruturn the datastream.
   */  
  function get_datastream()
  {
    return $this->datastream;
  }
}

?>
