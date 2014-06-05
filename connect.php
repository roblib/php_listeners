<?php

require_once 'tavernaSender.php';
require_once 'TavernaException.php';
/**
 * Class to listen for the JMS messages and filter them
 * based on the rules defined in config.xml
 * 
 * @author Richard Wincewicz
 */
$connect = new Connect();
$connect->listen();
unset($connect);

class Connect {

  function __construct() {
    include_once 'message.php';
    include_once 'fedoraConnection.php';
    include_once 'connect.php';
    //include_once 'Derivatives.php';
    include_once 'Logging.php';


    // Load config file
    $config_file = file_get_contents('config.xml');
    $this->config_xml = new SimpleXMLElement($config_file);

    // Logging settings
    $log_file = $this->config_xml->log->file;

    $this->log = new Logging();
    $this->log->lfile($log_file);
    $prot = empty($this->config_xml->fedora->protocol) ? 'http' : $this->config_xml->fedora->protocol;
    $this->fedora_url = $prot . '://' . $this->config_xml->fedora->host . ':' . $this->config_xml->fedora->port . '/fedora';
    $this->user = new stdClass();
    $this->user->name = $this->config_xml->fedora->username;
    $this->user->pass = $this->config_xml->fedora->password;

    // Set up stomp settings
    $stomp_url = 'tcp://' . $this->config_xml->stomp->host . ':' . $this->config_xml->stomp->port;
    $channel = $this->config_xml->stomp->channel;


    // Make a connection
    try {
      $this->con = new Stomp($stomp_url);
    } catch (StompException $e) {
      file_put_contents('php://stderr', "Could not open a connection to $stomp_url - " . $e->getMessage());
      throw($e);
    }
    $this->con->sync = TRUE;
    $this->con->setReadTimeout(1);

    // Subscribe to the queue
    try {
      $this->con->subscribe((string) $channel[0], array('activemq.prefetchSize' => 1));
    } catch (Exception $e) {
      file_put_contents('php://stderr', "Could not subscribe to the channel $channel - " . $e->getMessage());
      throw($e);
    }
  }

  function listen() {
    // Receive a message from the queue
    $returnResult = TRUE; //we will acknowledge message by default
    if ($this->msg = $this->con->readFrame()) {
      // do what you want with the message
      if ($this->msg != NULL) {
        $message = new Message($this->msg->body);
        $pid = $this->msg->headers['pid'];
        // if (!isset($message->dsID)) {
        //   $message->dsID = NULL;
        // }

        $modMethod = $this->msg->headers['methodName'];
        $message_dsid = isset($message->dsID) ? $message->dsID : NULL;
        $this->log->lwrite("Method: " . $modMethod, 'MODIFY_OBJECT', $pid, $message_dsid, $message->author);
        try {
          if (fedora_object_exists($this->fedora_url, $this->user, $pid) === FALSE) {
            $this->log->lwrite("Could not find object", 'DELETED_OBJECT', $pid, NULL, $message->author, 'ERROR');
            $this->con->ack($this->msg);
            unset($this->msg);
            return;
          }
          $fedora_object = new ListenerObject($this->user, $this->fedora_url, $pid);
        } catch (Exception $e) {
          $this->log->lwrite("An error occurred creating the fedora object", 'FAIL_OBJECT', $pid, NULL, $message->author, 'ERROR');
          $this->con->ack($this->msg);
          unset($this->msg);
          return;
        }

        foreach ($fedora_object->object->models as $contentMod) {   //each content model on the modified object
          //$this->log->lwrite("Content Models: ". $contentMod, 'SERVER_INFO');
          $modelObj = new ListenerObject($this->user, $this->fedora_url, $contentMod);

          try
            {
            $trigString = $modelObj->object['Trigger-Datastreams'];

            if ($trigString != "") { //if the content model has a 'Trigger-Datastreams' datastream
              $methods = new SimpleXMLElement($trigString->content);

              foreach ($methods->children() as $method) {
                $this->log->lwrite("Found a child method: " . $method['type'], 'MODIFY_OBJECT', $pid, $message_dsid, $message->author);
                if ((string) $method['type'] == (string) $modMethod) {
                  $this->log->lwrite("Modifing object with workflow defined in Trigger-Datastreams " . $method['type'], 'MODIFY_OBJECT', $pid, $message_dsid, $message->author);
                  foreach ($method->children() as $trigger) {
                    if ($trigger->getName() == "t2flow") {
                      $streamName = (string) $trigger['id'];
                      $returnResult = $this->runT2flow($streamName, $modelObj, $pid, $message_dsid);
                    }
                    else { //we have trigger
                      if ($trigger['id'] == $message_dsid) {
                        $this->log->lwrite("Matching Trigger  " . $trigger->getName(), 'MODIFY_OBJECT', $pid, $message_dsid, $message->author);
                        //  $this->log->lwrite("Listener Object: ". $t2flowList, 'SERVER_cINFO');
                        //TODO error check to make sure children are of type T2flow
                        foreach ($trigger->children() as $t2flow) {
                          $streamName = (string) $t2flow['id'];
                          $returnResult = $this->runT2flow($streamName, $modelObj, $pid, $message_dsid);
                        }   //foreach t2flow file 
                      } //if matching trigger
                    } //else nname wasnt t2flow
                  } //foreach ($method->trigger as $trigger)      
                }//	if( (string)$method['type'] ==  (string)$modMethod )
              }//foreach ($methods->children() as $method) 
            } //if($trigString != "") 
            } catch (Exception $e) {
            $this->log->lwrite("An error occurred parsing workflow for trigger datastreams " . $e->getMessage(), 'FAIL_OBJECT', $pid, NULL, $message->author, 'ERROR');
            }
        } //foreach contentmodel 
      }
      //we can call php code directly if the config.xml file is configured to do so. this would bypass taverna
      $this->triggerDatastreams($message, $pid);
      if ($returnResult) {
        $this->con->ack($this->msg);
      }
      else {
        $this->con->nack($this->msg);
      }
      unset($this->msg);
    }

    // Close log file
    $this->log->lclose();
  }

  /**
   * legacy function
   * if config.xml is configured to do so we can call out to php functions directly.
   * these functions do not require taverna.  this is the old way of calling the services
   * as everything has to be installed on the listener server.
   * @param type $message
   * @param type $pid
   */
  private function triggerDatastreams($message, $pid) {
    $properties = get_object_vars($message);
    $object_namespace_array = explode(':', $pid);
    $object_namespace = $object_namespace_array[0];
    $objects = $this->config_xml->xpath('//object');
    foreach ($objects as $object) {
      $namespaces = $object->nameSpace;
      $content_models = $object->contentModel;
      $xml_methods = $object->method;
      $methods = array();
      foreach ($xml_methods as $xml_method) {
        $methods[] = (string) $xml_method[0];
      }
      $datastream = $object->datastream;
      $datastream = (string) $datastream[0];
      $new_datastreams = $object->derivative;
      $extension = $object->extension;
      $extension = (string) $extension[0];
      $trigger_datastreams = (array) $object->trigger_datastream;
      foreach ($content_models as $content_model) {
//            $this->log->lwrite('Content models: ' . implode(', ', $fedora_object->object->models), "SERVER_INFO");
//            $this->log->lwrite('Config models: ' . $content_model, "SERVER_INFO");
        if (in_array($content_model, $fedora_object->object->models)) {
          foreach ($namespaces as $namespace) {
//                $this->log->lwrite('Namespace: ' . $object_namespace, "SERVER_INFO");
//                $this->log->lwrite('Config namespace: ' . $namespace, "SERVER_INFO");
            if ((string) $namespace == (string) $object_namespace) {
//                  $this->log->lwrite('Method: ' . $this->msg->headers['methodName'], "SERVER_INFO");
//                  $this->log->lwrite('Config method: ' . implode(', ', $methods), "SERVER_INFO");
              if (in_array($this->msg->headers['methodName'], $methods)) {
//                    $this->log->lwrite('Triggers: ' . $message->dsID, "SERVER_INFO");
//                    $this->log->lwrite('Config triggers: ' . implode(', ', $trigger_datastreams), "SERVER_INFO");
                if (in_array($message->dsID, $trigger_datastreams) || $message->dsID == NULL) {
                  foreach ($new_datastreams as $new_datastream) {
                    $include_file = (string) $new_datastream->file;
                    $class = (string) $new_datastream->class;
                    if (empty($include_file)) {
                      $include_file = 'Derivatives.php';
                    }
                    if (empty($class)) {
                      $class = 'Derivative';
                    }
                    $this->log->lwrite("File: $include_file Class: $class for dsid $new_datastream->dsid", 'SERVER_INFO');
                    $include_file = __DIR__ . $include_file;
                    require_once 'Derivatives.php';
                    include_once $include_file;
                    if (!class_exists($class)) {
                      $this->log->lwrite("Error loading class $class, check your config file", $pid, NULL, $message->author, 'ERROR');
                      continue;
                    }
                    else {
                      $derivative = new $class($fedora_object, $datastream, $extension, $this->log, $message->dsID);
                      $function = (string) $new_datastream->function;
                      if (!method_exists($derivative, $function)) {
                        $this->log->lwrite("Error calling $class->$function for $new_datastream->dsid, check your config file", $pid, NULL, $message->author, 'ERROR');
                        continue;
                      }
                      $output = $derivative->{$function}((string) $new_datastream->dsid, (string) $new_datastream->label);
                      if (isset($output)) {
                        $this->log->lwrite("PID: $pid File: $include_file Class: $class for $new_datastream->dsid output = $output", 'SERVER_INFO');
                      }
                    }
                  }
                }
              }
            }
          }
        }
      }
    }
  }

  /**
   * send a t2flow document to Tavarna also sends the inputs to the input 
   * ports
   * @param string $stream
   * @param string $pid
   * @param string $dsID
   * @param int $count
   * @return boolean
   */
  private function processT2flowOnTaverna($stream, $pid, $dsID, $count = 0) {
    if(empty($dsID)){
      $dsID = 'empty_stream_id';//in order to share workflows with ingest and other methods
      //(like addDatastream) we must always send both a PID and DSID otherwise taverna will complain that
      //the number of inputs don't match what was defined in the workflow.
    }
    try {
      $prot = empty($this->config_xml->taverna->protocol) ? 'http' : $this->config_xml->taverna->protocol;
      $context = empty($this->config_xml->taverna->context) ? 'http' : $this->config_xml->taverna->context;
      $taverna_sender = new TavernaSender($prot, $this->config_xml->taverna->host, $this->config_xml->taverna->port, $context, $this->config_xml->taverna->username, $this->config_xml->taverna->password);
      //Post t2flow
      $result = $taverna_sender->send_Message($stream);
      $uuid = $taverna_sender->parse_UUID($result);
      if (empty($uuid)) {
        //This message should never be seen, as it should break in send message first
        $this->log->lwrite('No UUID was found', "TAVERNA_ERROR");
      }
      else { //uuid has a value
        $this->log->lwrite('uuid = ' . $uuid, "SERVER_INFO");
        $taverna_sender->add_input($uuid, "pid", $pid);
        $taverna_sender->add_input($uuid, "dsid", $dsID);
        $result = $taverna_sender->run_t2flow($uuid);
        $this->log->lwrite('pid = ' . $pid, "SERVER_INFO");
        $this->log->lwrite('dsid = ' . $dsID, "SERVER_INFO");
        $status = $this->pollStatus($uuid, $taverna_sender);
        if ($status) {
          $this->log->lwrite("deleting workflow $uuid $pid $dsID", "SERVER_INFO");
          $taverna_sender->delete_t2flow($uuid);
        }
        return TRUE;
      }
    } catch (Exception $e) {
      $this->log->lwrite($e->getMessage() . ' ' . $e->getCode(), 'TAVERNA_ERROR', $pid, $dsID, NULL, 'ERROR');
      $responseString = $e->getMessage();
      $response = $taverna_sender->delete_t2flow($uuid); //try to delete the failed attempt on the taverna server
      //we rest and retry here as the most common taverna error will probable be a 403 forbidden
      //due to the server being overloaded.  
      sleep(10);
      if ($count <= 10) {
        $this->log->lwrite("Taverna error $responseString, workflow t2flow for $pid $dsID failed, sending agian.", 'SERVER_INFO', $pid, $dsID, NULL, 'INFO');
        $this->processT2flowOnTaverna($stream, $pid, $dsID, ++$count);
      }
      else {
        $this->log->lwrite($e->getMessage() . ' ' . $e->getCode() . " $pid $dsID reached the maximum number of tries giving up", "SERVER_INFO", $pid, $dsID, NULL, 'ERROR');
        $taverna_sender->delete_t2flow($uuid);
      }
      return FALSE; //we return false here so a negative ack will be sent.  this probably means (depending on the stomp server configs) that 
      //we will get this message again.  This prevents us from losing messages but could cause a loop if Taverna is down, 
      //inaccesible or just not responding
    }
  }

  /**
   * Polls taverna until a workflow is finished or we get an error.
   * as implemented this will poll forever if a workflow never reaches a
   * status of  Finished and
   * Taverna does not throw an exception.
   * @param string $uuid
   * @param TarvernaSender $tavernaSender
   * @param int $wait
   * @return boolean
   *   returns true if status is Finished
   */
  private function pollStatus($uuid, $tavernaSender, $wait = 2) {
    sleep(1);
    $status = $tavernaSender->get_status($uuid);
    while ($status != 'Finished') {
      sleep($wait);
      $status = $tavernaSender->get_status($uuid);
    }
    return TRUE;
  }

  private function runT2flow($streamName, $modelObj, $pid, $dsID) {
    $this->log->lwrite('Names of t2flows ' . $streamName, "SERVER_INFO");
    $stream = $modelObj->object[$streamName]->content;
    //get t2flow with t2flow doc      

    if ($stream != '') { //if thl content model contained a t2flow 
      return $this->processT2flowOnTaverna($stream, $pid, $dsID);
    }
    else { //stream =''
      $this->log->lwrite('No T2flow found on content model ' . $stream, 'FEDORA_ERROR');
      return TRUE; //we want to acknowledge that we received the message and have it removed from the queue
    }
  }

}

?>
