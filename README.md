PHP listeners
=============

These are listeners based in PHP which use the Stomp library to connect to the Fedora JMS broker. 

Currently each instance of the listeners can only listen to one repository. If you want to use a 
processing server to monitor more than one repository then you can set up a second listener and 
point it at the second repository.

### Requirements

* At least PHP 5.3

Installation instructions
-------------------------


### Fedora server setup

1\. To enable Stomp support in the embedded JMS broker download the Spring distribution from http://www.springsource.org/download and copy the .jar files in the dist directory to $FEDORA_HOME/tomcat/webapps/fedora/WEB-INF/lib.

2\. Edit $FEDORA_HOME/server/config/fedora.fcfg and change the section defining the broker to this:

    <param name="java.naming.provider.url" value="vm:(broker:(tcp://localhost:61616,stomp://localhost:61613))"/>

3\. To the same file add a third section underneath to define another channel to send messages to:

    <param name="datastore3" value="apimListenerMessages">
      <comment>A datastore representing a JMS Destination for APIM events used by the JMS listeners</comment>
    </param>

4\. At the bottom of this file add a third datastore section:

    <datastore id="apimListenerMessages">
    <comment>Messaging Destination for API-M events which update the repository</comment>
    <param name="messageTypes" value="apimUpdate">
      <comment>A space-separated list of message types that will be
            delivered to this Destination. Currently, &quot;apimUpdate&quot; and
            &quot;apimAccess&quot; are the only supported message types.</comment>
    </param>
    <param name="name" value="listener.update"/>
    <param name="type" value="queue">
      <comment>Optional, defaults to topic.</comment>
    </param>
    </datastore>

5\. Restart Fedora.

6\. Ensure firewall rules allow access from the listener server.


### Listener server setup

1\. Download the awesome tuque API from https://github.com/Islandora/tuque and ensure that it's in a sub directory called tuque.

2\. Install the PHP-Pear framework and use this to install the PHP Stomp library.

3\. Copy the config.xml.sample to config.xml and update it to reflect your environment.

4\. Start the listener by running "php listener.php".

5\. Ensure the firewall rules allow access from the Fedora server.


### REST interface setup

1\. Install apache.

2\. Copy the listener_rest.conf file to the apache config directory and change the details to suit your environment.

3\. Ensure firewall rules allow access to the REST interface port.

4\. Restart apache.