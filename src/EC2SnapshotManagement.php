<?php

namespace EC2SnapshotManagement;

use Aws\Ec2\Ec2Client;

/**
 * Manager
 *
 * Helps to manage your EC2 snapshots.
 */
class Manager
{
    private static $week    = 604800;
    private static $month   = 2678400;
    private static $regions = [
        'us-east-1'         => 'US East (N. Virginia)',
        'us-west-2'         => 'US West (Oregon)',
        'us-west-1'         => 'US West (N. California)',
        'eu-west-1'         => 'EU (Ireland)',
        'eu-central-1'      => 'EU (Frankfurt)',
        'ap-southeast-1'    => 'Asia Pacific (Singapore)',
        'ap-southeast-2'    => 'Asia Pacific (Sydney)',
        'ap-northeast-1'    => 'Asia Pacific (Tokyo)',
        'sa-east-1'         => 'South America (Sao Paulo)'
    ];

    private $snapshots          = [];
    private $snapshotsToRemove  = [];
    private $volume             = null;
    private $region             = null;
    private $quiet              = false;
    private $noOperation        = false;
    private $verbose            = false;
    private $description        = null;
    private $client             = null;

    public function __construct($volume = null, $region = null, $quiet = null, $noOperation = null, $verbose = null, $description = null)
    {
        if ($volume == null) {
            // Check parameters
            $params         = getopt('v:r::qno');
            $volume         = (isset($params['v'])) ? $params['v'] : null;
            $region         = (isset($params['r'])) ? $params['r'] : null;
            $quiet          = (isset($params['q'])) ? $params['q'] : null;
            $noOperation    = (isset($params['n'])) ? $params['n'] : null;
            $verbose        = (isset($params['o'])) ? $params['o'] : null;
            $description    = (isset($params['d'])) ? $params['d'] : null;
        }

        $this->volume       = (isset($volume)) ? $volume : exit("EC2 Volume ID required\n");
        $this->region       = (isset($region) && array_key_exists($region, self::$regions)) ? $region : 'us-east-1';
        $this->quiet        = (isset($quiet)) ? true : false;
        $this->noOperation  = (isset($noOperation)) ? true : false;
        $this->verbose      = (isset($verbose)) ? true : false;
        $this->description  = (isset($description)) ? $description : null;

        // Print settings
        $this->printLine("\n", true);
        $this->printLine("SETTINGS\n");
        $this->printLine("========\n");
        $this->printLine("Volume: .......... " . $this->volume . "\n");
        $this->printLine("Region: .......... " . self::$regions[$this->region] . "\n");
        $this->printLine("Quiet: ........... " . (($this->quiet) ? 'Y' : 'N') . "\n");
        $this->printLine("No Operation: .... " . (($this->noOperation) ? 'Y' : 'N') . "\n");
        $this->printLine("Verbose: ......... " . (($this->verbose) ? 'Y' : 'N') . "\n\n");

        // Setup EC2 Client
        $this->client = Ec2Client::factory([
            'profile' => 'ec2snapshot',
            'region'  => $this->region
        ]);
    }

    /**
     * Create a new snapshot of a volume
     */
    public function takeSnapshot()
    {
        try {
            $response = $this->client->createSnapshot([
                'DryRun' => $this->noOperation,
                'VolumeId' => $this->volume,
                'Description' => $this->description
            ]);
        }
        catch (\Aws\Ec2\Exception\Ec2Exception $e) {
            if (!$this->noOperation) {
                $this->printLine("Snapshot could not be initiated for volume " . $this->volume . "\n\n");
            }
        }

        if ($this->noOperation) {
            $this->printLine("No operation taken\n\n", true);
        }
        else {
            $response = $response->toArray();
            $this->printLine("Snapshot [" . $response['SnapshotId'] . "] initiated for volume " . $this->volume . "\n\n");
        }
    }

    /**
     * Cleanup old snapshots of a given volume
     */
    public function cleanupSnapshots()
    {
        // Get list of snapshots
        try {
            $response = $this->client->describeSnapshots([
                'Filters' => [
                    ['Name' => 'volume-id', 'Values' => [$this->volume]],
                    ['Name' => 'status', 'Values' => ['completed']],
                ]
            ]);
            $snapshots = $response->toArray();
            $this->snapshots = $snapshots['Snapshots'];
        }
        catch (\Aws\Ec2\Exception\Ec2Exception $e) {
            // Do Nothing
        }

        if (count($this->snapshots) == 0) {
            $this->printLine("No snapshots found for volumne: " . $this->volume . " in region " . self::$regions[$region] . "\n", true);
            exit;
        }

        if (count($this->snapshots) > 0) {
            $numSnapshots = count($this->snapshots);

            // Remove the latest to make sure we always keep at least one snapshot
            $mostRecent = array_pop($this->snapshots);

            $this->printLine("CLEANUP\n");
            $this->printLine("=======\n");
            $this->printSnapshotMessage($mostRecent, 'Keep Most Recent Snapshot');

            // Reverse them
            $this->snapshots = array_reverse($this->snapshots);

            // Figure out if we want to keep or remove each snapshot
            foreach ($this->snapshots as $snapshot) {
                $this->keepOrRemoveSnapshot($snapshot);
            }

            // In no operation mode?
            if ($this->noOperation) {
                $this->printLine("\nSnapshot management complete - No operation taken, but would have deleted " . count($this->snapshotsToRemove) . " snapshot(s)\n\n", true);
                exit;
            }

            // Remove the snapshots
            foreach ($this->snapshotsToRemove as $snapshot) {
                try {
                    $response = $this->client->deleteSnapshot([
                        'SnapshotId' => $snapshot['SnapshotId']
                    ]);
                }
                catch (\Aws\Ec2\Exception\Ec2Exception $e) {
                    $this->printSnapshotMessage($snapshot, 'Failed to delete snapshot');
                }
                if (isset($response)) {
                    $this->printSnapshotMessage($snapshot, 'Snapshot deleted');
                }
            }

            $this->printLine("\nSnapshot management complete - " . count($this->snapshotsToRemove) . " snapshot(s) deleted\n\n", true);
        }
    }

    /**
     * Helper function which decides whether or not to keep or delete a snapshot
     */
    private function keepOrRemoveSnapshot(&$snapshot)
    {
        $now = time();
        $pastWeek = $now - self::$week;
        $pastMonth = $now - self::$month;

        $creation = strtotime($snapshot['StartTime']);

        if ($creation >= $pastWeek) {
            // Made in the last seven days
            $this->printSnapshotMessage($snapshot, 'Keep daily snapshot');
        }
        else if (date('j', $creation) == 1) {
            // Made on the first day of the month
            $this->printSnapshotMessage($snapshot, 'Keep monthly snapshot');
        }
        else if (date('w', $creation) == 0 && $creation >= $pastMonth) {
            // Made on a sunday within the past month
            $this->printSnapshotMessage($snapshot, 'Keep weekly snapshot');
        }
        else {
            // If it hasn't met one of the above criteria then we can delete it
            $this->snapshotsToRemove[] = $snapshot;
            $this->printSnapshotMessage($snapshot, 'Delete snapshot');
        }
    }

    /**
     * Helper function to print a message related to a snapshot
     */
    private function printSnapshotMessage(array &$snapshot, $message = null)
    {
        $this->printLine("[" . $snapshot['SnapshotId'] . "]" . ((!empty($snapshot['Description'])) ? ' ' . $snapshot['Description'] . ' ' : null) ."- " . $message . " from " . date('D d M Y', strtotime($snapshot['StartTime'])) . "\n");
    }

    /**
     * Helper function to print a message depending on verbose and quiet settings
     */
    private function printLine($line, $override = false)
    {
        if (!$this->quiet && ($override || $this->verbose)) {
            print $line;
        }
    }
}