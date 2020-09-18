<?php

namespace Stanford\TrackCovidGenPopEpicAssistant;

require_once "emLoggerTrait.php";

use \REDCap;

class TrackCovidGenPopEpicAssistant extends \ExternalModules\AbstractExternalModule
{

    use emLoggerTrait;

    CONST EM_EVENT_NAME = "screening_arm_1";


    public function __construct()
    {
        parent::__construct();
        // Other code to run when object is instantiated
    }

    public function autoUpdateCron() {
        $this->emDebug("Starting autoUpdate Cron");

        foreach($this->framework->getProjectsWithModuleEnabled() as $localProjectId) {
            $_GET['pid'] = $localProjectId;
            $this->emDebug("Setting pid to $localProjectId");

            $results = $this->getRecordData();
            $this->emDebug("cron obtained " . count($results) . " records");

            $updates = $this->parseRecords($results);
            $this->emDebug("cron produced " . count($updates) . " updates");

            $q = $this->updateRecords($updates);
            $this->emDebug("cron save results", $q);
        }
    }


    public function redcap_save_record($project_id, $record, $instrument, $event_id, $group_id, $survey_hash = NULL, $response_id = NULL, $repeat_instance = 1) {
        $em_event_id = REDCap::getEventIdFromUniqueEvent($this::EM_EVENT_NAME);

        // $this->emDebug(".".$event_id.".", ".".$em_event_id.".");


        if ($event_id != $em_event_id) {
            // This is the wrong event
            // $this->emDebug("Wrong Event");
            return;
        }
        $this->emDebug("Updating $record");

        $records = $this->getRecordData(array($record));
        $updates = $this->parseRecords($records);
        $q = $this->updateRecords($updates);

        if (!empty($q['errors'])) {
            REDCap::logEvent("Error updating in " . $this->getModuleName(), json_encode($updates),"",$record, $event_id);
        }

    }



    public function getRecordData($records = null) {
        $params = [
            "project_id"    => $this->getProjectId(),
            "records"       => $records,
            "return_format" => "json",
            "events"        => [ "screening_arm_1" ],
        ];

        $q = REDCap::getData($params);
        return json_decode($q,true);
    }


    /**
     * Parse through the records to return an array of updates
     * @param $data array  A json-exported redcap array of records
     * @return array
     */
    public function parseRecords($results) {

        $updates = [];

        $force = $this->getProjectSetting('force-update');

        foreach ($results as $r) {

            list($city, $state, $zip) = $this->parseCsz($r["csz"]);
            $address   = $this->parseAddr($r["addr"]);
            $lang      = $this->parseLang($r['confirm_language']);
            $ethnicity = $this->parseEthnicity($r);
            $sex       = $this->parseSex($r);

            $update = [];

            // Update primary_city from CSZ
            if (( $force || empty($r['primary_city']))           && !empty($city)) $update['primary_city'] = $city;

            // Update State from CSZ
            if (( $force || empty($r['primary_state']))          && !empty($state)) $update['primary_state'] = $state;

            // Update language
            if (( $force || empty($r['stanford_epic_lang']))     && !empty($lang)) $update['stanford_epic_lang'] = $lang;

            // Update ethnicity
            if (( $force || empty($r['stanford_epic_ethnicity']))&& !empty($ethnicity)) $update['stanford_epic_ethnicity'] = $ethnicity;

            // Update ethnicity
            if (( $force || empty($r['stanford_epic_sex']))      && !empty($sex)) $update['stanford_epic_sex'] = $sex;


            if (!empty($update)) {
                $update[REDCap::getRecordIdField()] = $r[REDCap::getRecordIdField()];
                $update['redcap_event_name']        = $this::EM_EVENT_NAME;

                $updates[] = $update;
            }
        }

        return $updates;
    }


    public function updateRecords($updates) {
        $this->emDebug('updates',$updates);
        $q = REDCap::saveData($this->getProjectId(), 'json', json_encode($updates));
        //$this->emDebug($q);
        return $q;
    }


    public function parseSex($row) {
        $saad = $row["saab"];
        switch ($saad) {
            case "1":
                $sex = "M";
                break;
            case "2":
                $sex = "F";
                break;
            default:
                $sex = "U";
                break;
        }
        return $sex;
    }

    public function parseCsz($csz)
    {
        global $module;

        $re      = '/^([^,]+)\,\s+(\w+)\s+(\d{5})/';
        $matches = [];
        $count   = preg_match_all($re, $csz, $matches);

        $city  = isset($matches[1][0]) ? trim($matches[1][0]) : "";
        $state = isset($matches[2][0]) ? trim($matches[2][0]) : "";
        $zip   = isset($matches[3][0]) ? trim($matches[3][0]) : "";

        return array($city, $state, $zip);
    }

    public function parseAddr($addr)
    {
        global $module;
        // $module->emDebug($addr);

        return trim($addr);
    }

    public function parseLang($lang)
    {
        global $module;
        // $module->emDebug($lang);

        switch ($lang) {
            case "1":
                $result = "ENG";
                break;
            case "2":
                $result = "SPA";
                break;
            case "3":
                $result = "MDN";
                break;
            case "4":
                $result = "TGL";
                break;
            case "5":
                $result = "VIE";
                break;
            default:
                $result = "";
        }

        /*
        1	English
        2	Español
        3	T繁體中文e
        4	Tagalog
        5	Tiếng Việt

        ACCEPTABLE VALUES
        Bosnian 	Bosnian
        Haitian Creole 	Haitian Creo
        Albanian 	ALB
        Arabic 	ARA
        Cantonese 	CAN
        Deaf/Non-Sign Language 	NSL
        English 	ENG
        French 	FRE
        German 	GER
        Hebrew 	HEB
        Italian 	ITA
        Japanese 	JPN
        Korean 	KOR
        Mandarin 	MDN
        Farsi 	PES
        Russian 	RUS
        Spanish 	SPA
        Tagalog 	TGL
        Vietnamese 	VIE
        Thai 	THA
        Other 	OTH
        Unknown 	UNK
        American Sign Language 	ASL
        Portuguese 	POR
        Armenian 	ARM
        Creole, French 	CPF
        Croatian 	CRO
        Greek 	GRE
        Hindi 	HIN
        Hmong 	HMN
        Gujarati 	GUJ
        Hungarian 	HUN
        Cambodian, Mon-Khmer 	KHM
        Laotian 	LAO
        Navajo 	NAV
        Polish 	POL
        Samoan 	SMO
        Dari 	DAR
        Tongan 	TON
        Urdu 	URD
        Yiddish 	YID
        Serbian 	SCR
        Fijian 	FIJ
        Latvian 	LAV
        Ilocano 	ILO
        Punjabi (Panjabi) 	PUN
        Romanian 	ROM
        Taiwanese 	TAW
        Shanghainese 	CHI
        Miao 	MIA
        Telugu 	TEL
        Ukrainian 	UKR
        Amharic 	AMH
        Burmese 	BUR
        Indonesian 	IND
        Persian 	PER
        Swahili 	SWA
        Yoruba 	YOR
     */

        return trim($result);

    }

    public function parseEthnicity($row) {
            // Figure out ethnicity
        $latino_origin = $row['latino_origin'];
        switch ($latino_origin) {
            case "1":
                $ethnicity = "Hispanic/Latino";
                break;
            case "0":
                $ethnicity = "Non-Hispanic/Non-Latino";
                break;
            default:
                $ethnicity = "Unknown";
        }
        return trim($ethnicity);
    }


}
