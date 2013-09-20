<?php

require_once 'tuque/HttpConnection.php';

/**
 *This class defines extends Tuque's CurlConnectioin
 */
class TavernaCurlConnection extends CurlConnection {

  /**
   * Put a request to the server. This is primarily used for
   * send strings using PUT method
   *
   * @todo Test this for send string to taverna server 
   *
   * @param string $url
   *   The URL to post the request to. Should start with the
   *   protocol. For example: http://.
   * @param string $type
   *   This paramarter must be one of: string, file.
   * @param string $data
   *   What this parameter contains is decided by the $type parameter.
   * @param string $content_type
   *   The content type header to set for the post request.
   *
   * @throws HttpConnectionException
   *
   * @return array
   *   Associative array containing:
   *   * $return['status'] = The HTTP status code
   *   * $return['headers'] = The HTTP headers of the reply
   *   * $return['content'] = The body of the HTTP reply
   */
  public function tavernaPutRequest($url, $type = 'none', $data , $content_type = NULL) {
    
    $this->setupCurlContext($url);
    curl_setopt(self::$curlContext, CURLOPT_CUSTOMREQUEST, 'PUT');
    //curl_setopt(self::$curlContext, CURLOPT_PUT, TRUE);

    switch (strtolower($type)) {
      case 'string':
        if ($content_type) {
          $headers = array("Content-Type: $content_type");
        }
        else {
          $headers = array("Content-Type: text/plain");
        }
        curl_setopt(self::$curlContext, CURLOPT_HTTPHEADER, $headers);
        curl_setopt(self::$curlContext, CURLOPT_POSTFIELDS, $data);
        break;

      case 'none':
        curl_setopt(self::$curlContext, CURLOPT_POSTFIELDS, array());
        break;

      default:
        throw new HttpConnectionException('$type must be: string. ' . "($type).", 0);
    }
   
    $exception = NULL;
    try {
	$results = $this->doCurlRequest();
	} catch (HttpConnectionException $e)
	{
	$exception = $e;
	} 
     
    if($exception) {
        throw $exception;
    }

    return $results;

  } 

}
?>
