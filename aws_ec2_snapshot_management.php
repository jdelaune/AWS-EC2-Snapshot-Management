<?php
/**
 * @file
 * An EC2 snapshot management script for keeping regular backups.
 *
 * A backup management script which keeps the last seven days of
 * backups, as well as one per week for the last month, and then
 * one per month after that.
 *
 * Based on a script by Erik Dasque and possibly Oren Solomianik
 * @link http://www.thecloudsaga.com/aws-ec2-manage-snapshots/ @endlink
 *
 * @param v
 *  The Volume ID of snapshots which you wish to manage.
 * @param r
 *  (Optional) Defaults to US-EAST-1.
 *  The region where the snapshots are held.
 *  Options include: us-e1, us-w1, eu-w1 and
 *  apac-se1.
 * @param o
 *  (Optional) Defaults to FALSE.
 *  Verbose mode, tells you what it's doing.
 * @param q
 *  (Optional) Defaults to FALSE.
 *  Quiet mode, no ouput.
 * @param n
 *  (Optional) Defaults to FALSE.
 *  No operation mode, it won't delete any snapshots.
 *
 * Example usage:
 * @code
 * php aws_ec2_snapshot_management.php -v=vol-11a22222 -r=apac-se1 -v -q -n
 * @endcode
 */

// Debug overriding mode
define("DEBUG", FALSE);

// Set HTML headers
header("Content-type: text/html; charset=utf-8");

// Include the SDK
require_once 'aws-sdk/sdk.class.php';

// Process paramters and setup constants
$parameters = getopt('v:r::qno');

if (!isset($parameters['v'])) {
  exit('EC2 Volume ID required' . "\n");
}
else {
  define("VOLUME", $parameters['v']);
}

if (isset($parameters['r'])) {
  switch($parameters['r']) {
    case 'us-e1':
      define("REGION", 'us-east-1');
      break;
    case 'us-w1':
      define("REGION", 'us-west-1');
      break;
    case 'eu-w1':
      define("REGION", 'eu-west-1');
      break;
    case 'apac-se1':
      define("REGION", 'ap-southeast-1');
      break;
  }
}
else {
  define("REGION", 'us-east-1');
}

if (isset($parameters['q']) || DEBUG) {
  define("QUIET", TRUE);
}
else {
  define("QUIET", FALSE);
}

if (isset($parameters['n']) || DEBUG) {
  define("NOOP", TRUE);
}
else {
  define("NOOP", FALSE);
}

if (isset($parameters['o']) || DEBUG) {
  define("VERBOSE", TRUE);
}
else {
  define("VERBOSE", FALSE);
}

define("WEEK", 604800);
define("MONTH", 2678400);

// Instantiate the AmazonEC2 class
$ec2 = new AmazonEC2();

// Set Region
$ec2->set_region(REGION);

// Get Response
$response = $ec2->describe_snapshots(
  array(
    'Filter' => array(
        array('Name' => 'volume-id', 'Value' => VOLUME),
        array('Name' => 'progress', 'Value' => '100%'),
    ),
  )
);

$snapshots = array();
$snapshots_to_delete = array();

foreach ($response->body->snapshotSet->item as $snapshot) {
  $snapshots[] = $snapshot;
}

// Find out how many snapshots we have
$num = count($snapshots);

if ($num <= 1) {
  // We have less than one snapshot don't do anything
  if (!QUIET) {
    exit('Not enough snapshots found to manage' . "\n");
  }
  else{
    exit;
  }
}
else {
  // Remove the latest to make sure we always keep at least one snapshot
  $most_recent = array_pop($snapshots);
  if (VERBOSE && !QUIET) { print date('D d M Y', strtotime($most_recent->startTime)) .' - Keep Most Recent' . "\n"; }
}

$snapshots = array_reverse($snapshots);

// Figure out if we want to keep or delete each snapshot
foreach ($snapshots as $snapshot) {
  keep_or_delete_backup($snapshot, $snapshots_to_delete);
}

// Actually delete the snapshots we don't need
if (!NOOP) {
  foreach ($snapshots_to_delete as $snapshotId) {
    $response = $ec2->delete_snapshot($snapshotId);
    if (!$response->isOK() && !QUIET) {
      print 'Failed to delete snapshot: ' . $snapshotId . "\n";
    }
    else if (!QUIET) {
      print 'Snapshot ' . $snapshotId . ' deleted.' . "\n";
    }
  }
}

if (!QUIET) {
  print date('D d M Y') . ' - Snapshot Management Complete' . "\n";
}
else {
  return;
}

/**
 * Check if we want to keep or delete snapshot
 */
function keep_or_delete_backup(&$snapshot, &$snapshots_to_delete) {
  $now = time();
  $past_week = $now - WEEK;
  $past_month = $now - MONTH;
  
  $creation = strtotime($snapshot->startTime);
  
  if ($creation >= $past_week) {
    // Made in the last seven days
    if (VERBOSE && !QUIET) { print date('D d M Y', $creation) .' - Keep Daily' . "\n"; }
  }
  else if (date('j', $creation) == 1) {
    // Made on the first day of the month
    if (VERBOSE && !QUIET) { print date('D d M Y', $creation) .' - Keep Monthly' . "\n"; }
  }
  else if (date('w', $creation) == 0 && $creation >= $past_month) {
    // Made on a sunday within the past month
    if (VERBOSE && !QUIET) { print date('D d M Y', $creation) .' - Keep Weekly' . "\n"; }
  }
  else {
    // If it hasn't met one of the above criteria then we can delete it
    $snapshots_to_delete[] = $snapshot->snapshotId;
    if (VERBOSE && !QUIET) { print date('D d M Y', $creation) .' - Delete' . "\n"; }
  }
}