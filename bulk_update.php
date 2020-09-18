<?php
namespace Stanford\TrackCovidGenPopEpicAssistant;
/** @var TrackCovidGenPopEpicAssistant $module */

use \REDCap;

include_once (APP_PATH_DOCROOT . "ProjectGeneral/header.php");

$results = $module->getRecordData();
$updates = $module->parseRecords($results);
$q = $module->updateRecords($updates);


?>

    <h5> This page updates all Stanford MRN fields for the bulk epic reporter </h5>

    <p>A total of <?php echo count($updates) ?> records were updated: </p>
    <pre><?php echo print_r($updates,true) ?></pre>
    <p>Results:</p>
    <pre><?php echo print_r($q,true) ?></pre>

<style>
    pre {overflow-y: scroll; max-height: 240px; font-size: smaller;}
</style>
