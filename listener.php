<?php

/**
 * Parent process that coordinates the listener child processes
 * 
 * @author Richard Wincewicz
 */

fclose(STDIN);
fclose(STDOUT);
fclose(STDERR);
$STDIN = fopen('/dev/null', 'r');
$STDOUT = fopen('application.log', 'wb');
$STDERR = fopen('error.log', 'wb');

// Include libraries
include_once 'message.php';
include_once 'fedoraConnection.php';
include_once 'connect.php';

$config_file = file_get_contents('config.xml');
$config_xml = new SimpleXMLElement($config_file);
$children = $config_xml->listeners->child_processes;

// Handle interrupt signals
ini_set('display_errors', 0);
print "Parent : " . getmypid() . "\n";

global $pids;
$pids = array();

// Daemonize
$pid = pcntl_fork();
if ($pid) {
  // Only the parent will know the PID. Kids aren't self-aware
  // Parent says goodbye!
  print "\tParent : " . getmypid() . " exiting\n";
  exit();
}

print "Child : " . getmypid() . "\n";

// Handle signals so we can exit nicely
declare(ticks = 1);

function sig_handler($signo) {
  global $pids, $pidFileWritten;
  if ($signo == SIGTERM || $signo == SIGHUP || $signo == SIGINT) {
    // If we are being restarted or killed, quit all children
    // Send the same signal to the children which we recieved
    foreach ($pids as $p) {
      posix_kill($p, $signo);
    }

    // Women and Children first (let them exit)
    foreach ($pids as $p) {
      pcntl_waitpid($p, $status);
    }
    print "Parent : " . getmypid() . " all my kids should be gone now. Exiting.\n";
    exit();
  }
  else if ($signo == SIGUSR1) {
    print "I currently have " . count($pids) . " children\n";
  }
}

// setup signal handlers to actually catch and direct the signals
pcntl_signal(SIGTERM, "sig_handler");
pcntl_signal(SIGHUP, "sig_handler");
pcntl_signal(SIGINT, "sig_handler");
pcntl_signal(SIGUSR1, "sig_handler");

// All the daemon setup work is done now. Now do the actual tasks at hand
// The program to launch
$program = "/usr/bin/php";
$arguments = array("connect.php");
$timer = 0;
while (TRUE) {
  // In a real world scenario we would do some sort of conditional launch.
  // Maybe a condition in a DB is met, or whatever, here we're going to
  // cap the number of concurrent grandchildren
  if (count($pids) < $children) {
    $pid = pcntl_fork();
    if (!$pid) {
      pcntl_exec($program, $arguments); // takes an array of arguments
      exit();
    }
    else {
      // We add pids to a global array, so that when we get a kill signal
      // we tell the kids to flush and exit.
      $pids[] = $pid;
    }
  }

  // Collect any children which have exited on their own. pcntl_waitpid will
  // return the PID that exited or 0 or ERROR
  // WNOHANG means we won't sit here waiting if there's not a child ready
  // for us to reap immediately
  // -1 means any child
  $dead_and_gone = pcntl_waitpid(-1, $status, WNOHANG);
  while ($dead_and_gone > 0) {
    // Remove the gone pid from the array
    unset($pids[array_search($dead_and_gone, $pids)]);

    // Look for another one
    $dead_and_gone = pcntl_waitpid(-1, $status, WNOHANG);
  }
  // Roughly ever 60 seconds print the memory usage
  if ($timer > 1200) {
    print "Daemon memory usage: " . memory_get_usage() . "\n";
    $timer = 0;
  }
  $timer++;
  // Sleep for 1 second
  usleep(50000);
}
?>