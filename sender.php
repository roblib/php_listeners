<?php

/*
*  
*
*
*  7th March 2013 @Author: Yuqing Jiang
**/
class Sender
{
  public $hostname = null;     //url of taverna server
  
  public $password = null;
  public $username = null;
  /*
  * initial the host name and pass word
  *
  */    
  function __construct($hostname = null, $password =null,$username=null)
  {
    $this->hostname=$hostname;
    $this->password=$password;
    $this->username=$username;
  }
  
  /*
  *  set the hostname or url
  */  
  
  function set_hostname($hostname = null)
  {
    $this->hostname=$hostname;
  }
  
  /*
  *
  */  
  
  function set_password($password=null)
  {
    $this->password =$password;
  }
  
  function set_username($username = null)
  {
    $this->username = $username;
  }
  
  function get_username()
  {
    return $this->username;
  }
  
  function get_hostname()
  {
    return $this->hostname;
  }
  
  function get_password()
  {
    return $this->password;
  }
  
  function send_Message($message)
  {
  }  
}
?>