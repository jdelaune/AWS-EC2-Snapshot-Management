CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Installation
 * Example
 * Command Line Parameters


INTRODUCTION
------------

Current Maintainer: Jordan de Laune

AWS EC2 Snapshot Management is a php script which prunes EC2 snapshots as if they were backups. It's meant to be run from the command line, but can be called by other PHP scripts if you so desire. As well as cleaning up snapshots it can also be used to take new snapshots.

The cleanup function will keep snapshots made in the last seven days (Daily), one per week for the last month (Weekly) and one per month (Monthly).

It will always keep at least one snapshot even if it was made a year ago.

This script took inspiration from 'EC2 Manage Snapshots' made by Erik Dasque and previously Oren Solomianik. It however requires the AWS PHP SDK.

This script is provided under the MIT License.


INSTALLATION
------------

Use Composer by adding this requirement to your existing `composer.json` file or by creating one and adding this:

```json
{
    "require": {
        "jdelaune/aws-ec2-snapshot-management": "2.0.*"
    }
}
```

The run `composer update` or `composer install`, alternatively you can just run `composer update jdelaune/aws-ec2-snapshot-management.

EC2 Snapshot Management uses the AWS PHP SDK. You will needed to setup your credentials by following the instructions given here:

http://docs.aws.amazon.com/aws-sdk-php/guide/latest/credentials.html#credential-profiles

It expects a profile called `ec2snapshot`.


EXAMPLE
-------

You will need to create a sample script like the one below, uncomment as needed:

```php
<?php
// myScript.php

require 'vendor/autoload.php';

use EC2SnapshotManagement\Manager;

$manager = new Manager;

// Cleanup existing snapshots
// $manager->cleanupSnapshots('vol-abcdefij', 'eu-west-1', false, true, true);
// OR no arguments if calling this script from the command line
// $manager->cleanupSnapshots();

// Create a new snapshot
// $manager->takeSnapshot('vol-abcdefgh', 'eu-west-1', false, true, true, 'My Server Backup');
// OR no arguments if calling this script from the command line
// $manager->takeSnapshot();
```

Or you can call the script you just created from the command line:

```shell
php myScript.php -v=vol-abcdefgh -r=eu-west-1 -n -o -d="My Server Backup"
```

You will probably want to create two scripts to call each function independently!


COMMAND LINE PARAMETERS
-----------------------

Parameter | Value
--------- | -------------------------
v         | EC2 volume identifier (Required).
r         | EC2 region (Optional). Defaults to us-east-1. Options: us-east-1, us-west-1, us-west-2, eu-west-1, eu-central-1, ap-southeast-1, ap-southeast-2, ap-northeast-1 or sa-east-1.
d         | Description, used when creating a snapshot (Optional).
o         | Verbose mode. Tells you what it's doing (Optional).
q         | Quiet mode. No output (Optional).
n         | No operation mode. It won't actually delete or create any snapshots. Useful along with verbose mode to see what it will do the first time you run it (Optional).