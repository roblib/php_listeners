<?php

class ContentModel
{
  public $pid = null;
  public $datastream_arr = array();
  public $content= null;

  function __construct($pid =null)
  {
    $this->pid =$pid; 
  }
 
 
 /*
 * This function is to get content model from fedora
 * for the input:
 * 
 * $host should contains the  protocal and ip address or dormin name of fedora 
 *  server.
 * For Example: http:// XXX.XXX.XXX.XXX
 * no '/' after the domain name or ip address       
 * 
 * $username is to log into the fedora server. Type: String
 * 
 * $password shoud be the password to log in to the fedora server. Type: String 
 * @Author: Yuqing Jiang(E-mail: yqjiang0830@gmail.com )
 */    
  function set_contect($hostname=null,$username=null,$password=null)
  {
    $url = $hostname.'/fedora/objects/'.$this->pid;
    $ch = curl_init();
    curl_setopt ($ch, CURLOPT_URL,$url); 
    curl_setopt ($ch, CURLOPT_USERPWD,$username.':'.$password);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
    $this->content = curl_exec($ch);
    curl_close($ch);
  }
  
  
  function get_content()
  {
    return $this->content;
  }
  
  function get_pid()
  {
    return $this->pid;
  }
  
  function get_datastream_array()
  {
    return $this->datastream_arr;
  }
  
  

}

?>