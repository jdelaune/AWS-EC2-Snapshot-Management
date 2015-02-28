CONTENTS OF THIS FILE
---------------------

 * [Introduction](#introduction)
 * [Installation](#installation)
 * [Example](#example)
 * [Command Line Parameters](#command-line-parameters)


INTRODUCTION
------------

AWS EC2 Snapshot Management is a php script which prunes EC2 snapshots as if they were backups. It's meant to be run from the command line, but can be called by other PHP scripts if you so desire. As well as cleaning up snapshots it can also be used to take new snapshots as well.

The cleanup function will keep snapshots made:

* Daily: In the last seven days.
* Weekly: One per week in the last month.
* Monthly: One per month.

_It will always keep at least one snapshot even if it was made a year ago._

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

The run `composer update` or `composer install`, alternatively you can just run `composer update jdelaune/aws-ec2-snapshot-management`.

EC2 Snapshot Management uses the AWS PHP SDK. You will needed to setup your credentials by following the instructions given here:

http://docs.aws.amazon.com/aws-sdk-php/guide/latest/credentials.html#credential-profiles

It expects a profile called `ec2snapshot`.


EXAMPLE
-------

You will need to create a sample script like the one below:

```php
<?php
// myScript.php

require 'vendor/autoload.php';

use EC2SnapshotManagement\Manager;

/**
 * Create a new EC2 Snapshot Manager
 *
 * You don't need to supply any arguments if calling from the command line.
 *
 * @param string    $volume EC2 Volume Identifier (optional).
 * @param string    $region EC2 Region Identifier (optional).
 * @param boolean   $quiet Quiet mode, no output (optional).
 * @param boolean   $noOperation No operation mode, nothing will get deleted (optional).
 * @param boolean   $verbose Verbose, tells you exactly what it's doing (optional).
 * @param string    $description Description of new snapshot if creating one (optional).
 */
$manager = new Manager('vol-abcdefgh', 'eu-west-1', false, true, true, 'My Data Backup');

/**
 * Cleans up existing old snapshots
 */
$manager->cleanupSnapshots();

/**
 * Take a new snapshot
 */
$manager->takeSnapshot();
```

Or you can call the script you just created from the command line instead of passing parameters to the class constructor:

```shell
php myScript.php -v=vol-abcdefgh -r=eu-west-1 -d="My Server Backup" -n -o
```

You will probably want to create two scripts to call each function independently!


COMMAND LINE PARAMETERS
-----------------------

Parameter | Value
--------- | -------------------------
-v=       | EC2 volume identifier (Required).
-r=       | EC2 region (Optional). Defaults to us-east-1.<br><br><ul><li>us-east-1 - US East (N. Virginia)</li><li>us-west-1 - US West (N. California)</li> <li>us-west-2 - US West (Oregon)</il><li>eu-west-1 - EU (Ireland)</il><li>eu-central-1 - EU (Frankfurt)</il><li>ap-southeast-1 - Asia Pacific (Singapore)</il><li>ap-southeast-2 - Asia Pacific (Sydney)</il><li>ap-northeast-1 - Asia Pacific (Tokyo)</il><li>sa-east-1 - South America (Sao Paulo)</il></ul>
-d=       | Description, used when creating a snapshot (Optional).
-o        | Verbose mode. Tells you what it's doing (Optional).
-q        | Quiet mode. No output (Optional).
-n        | No operation mode. It won't actually delete or create any snapshots. Useful along with verbose mode to see what it will do the first time you run it (Optional).
