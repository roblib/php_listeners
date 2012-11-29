<?php

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

    $this->fedora_url = 'http://' . $this->config_xml->fedora->host . ':' . $this->config_xml->fedora->port . '/fedora';
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
      file_put_contents('php://stderr', "Could not open a connection to $stomp_url - $e");
    }
    $this->con->sync = TRUE;
    $this->con->setReadTimeout(1);

    // Subscribe to the queue
    try {
      $this->con->subscribe((string) $channel[0], array('activemq.prefetchSize' => 1));
    } catch (Exception $e) {
      file_put_contents('php://stderr', "Could not subscribe to the channel $channel - $e");
    }
  }

  function listen() {
    // Receive a message from the queue
    if ($this->msg = $this->con->readFrame()) {
      // do what you want with the message
      if ($this->msg != NULL) {
//        sleep(1);
//        $this->log->lwrite('Message: ' . $this->msg->body, 'SERVER_INFO');
        $message = new Message($this->msg->body);
        $pid = $this->msg->headers['pid'];
        if (!$message->dsID) {
          $message->dsID = NULL;
        }
        $this->log->lwrite("Method: " . $this->msg->headers['methodName'], 'MODIFY_OBJECT', $pid, $message->dsID, $message->author);
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
        }
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
                        $this->log->lwrite("File: $include_file Class: $class for $new_datastream->dsid", 'SERVER_INFO');
                        $include_file =  __DIR__ . $include_file;
                        require_once 'Derivatives.php';
                        require_once $include_file;
                         
                        if (!class_exists($class)) {
                          $this->log->lwrite("Error loading class $class, check your config file", $pid, NULL, $message->author, 'ERROR');
                          //continue;
                        }
                        else {
                          $derivative = new $class ($fedora_object, $datastream, $extension, $this->log, $message->dsID);
                          //$derivative = call_user_func_array()                
//$this->log->lwrite("Adding datastream '$new_datastream->dsid' with label '$new_datastream->label' using function '$new_datastream->function'", 'START_DATASTREAM', $pid, $new_datastream->dsid, $message->author);
                          $function = (string) $new_datastream->function;
                          if (!method_exists($derivative, $function)) {
                            $this->log->lwrite("Error calling $class->$function for $new_datastream->dsid, check your config file", $pid, NULL, $message->author, 'ERROR');
                            //continue;
                          }                          
                            $output = $derivative->{$function}((string) $new_datastream->dsid, (string) $new_datastream->label); 
                            $this->log->lwrite("File: $include_file Class: $class for $new_datastream->dsid output = $output", 'SERVER_INFO');
                        }
                      }
                    }
                  }
                }
              }
            }
          }
          unset($namespaces);
          unset($namespace);
          unset($content_models);
          unset($content_model);
          unset($methods);
          unset($datastream);
          unset($new_datastreams);
          unset($new_datastream);
          unset($derivative);
        }

        // Mark the message as received in the queue
        $this->con->ack($this->msg);
        unset($this->msg);
      }

      // Close log file
      $this->log->lclose();
    }
  }

}

?>
