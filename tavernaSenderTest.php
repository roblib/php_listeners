<?php

require_once 'tavernaSenderNew.php';
//require_once 'tavernaSender.php';

$tavernaS= new TavernaSender('https','p1.vre.upei.ca','8443','taverna-server','yuqing','11t@v3rn@13');

//test construct

echo "there are taverna infomations\r\n";
echo "host ".$tavernaS->hostname."\r\n";
echo "username ".$tavernaS->username."\r\n";
echo "password ".$tavernaS->password."\r\n";

//test sentmessage
$filename = 't2flow/at.t2flow';
$workflow = file_get_contents($filename);
//echo $workflow;
$result = $tavernaS->send_Message($workflow);
echo $result;

$uuid = $tavernaS->parse_uuid($result);
print("uuid ".$uuid."\r\n");

$sent_dsid =$tavernaS->add_input($uuid,"dsid","JPG");
$sent_pid =$tavernaS->add_input($uuid,"pid","islandora:299");
$sent_label =$tavernaS->add_input($uuid,"label","stanly");
$sent_resize =$tavernaS->add_input($uuid,"resize","200");

print($sent_dsid."\r\n");
print($sent_label."\r\n");
print($sent_pid."\r\n");
print($sent_resize."\r\n");

$run_t2flow_res= $tavernaS->run_t2flow($uuid);
print($run_t2flow_res);

$status = $tavernaS->get_status($uuid);
print($status."\r\n");

/*
$tavernaS->delete_t2flow($uuid);
print("deleted ".$uuid);
*/

?>
