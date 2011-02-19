CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Installation
 * Example
 * Parameters


INTRODUCTION
------------

Current Maintainer: Jordan de Laune

AWS EC2 Snapshot Management is a php script which prunes EC2 snapshots as
if they were backups. It's meant to be run from the command line.

It will keep snapshots made in the last seven (7) days [Daily], one (1) per
week for the last month [Weekly] and one (1) per month [Monthly].

It will always keep at least one snapshot even if it was made a year ago.

This script took inspiration from 'EC2 Manage Snapshots' made by Erik Dasque
and previously Oren Solomianik. It however requires the AWS PHP SDK.

This script is provided under the Apache 2.0 License.


INSTALLATION
------------

AWS EC2 Snapshot Management requires the AWS PHP SDK. Found here:
http://aws.amazon.com/sdkforphp/

1. Download the AWS PHP SDK and drop it in the aws-sdk directory. So you
   should see the class here: aws-sdk/sdk.class.php

2. Copy aws-sdk/config-sample.inc.php to aws-sdk/config.inc.php and fill
   in your security details.


EXAMPLE
-------

php aws_ec2_snapshot_management.php -v=vol-11a22222 -r=eu-e1 -q


PARAMETERS
----------

v  EC2 Volume ID (Required).

r  EC2 Region (Optional). Defaults to US-EAST-1. Options: us-e1, us-w1, eu-w1
   and apac-se1

o  Verbose mode. Tells you what it's doing.

q  Quiet mode. No output.

n  No Operation mode. It won't actually delete any snapshots. Useful along with
   verbose mode to see what it will delete the first time you run it.