<?php

namespace Heroic\ScannerApi\Http\Controllers\Breach;

use App\Http\Controllers\Controller;
use Heroic\ScannerApi\Jobs\ProcessBreachMonitoring;
use Heroic\ScannerApi\Models\BreachMonitorList;
use Heroic\ScannerApi\Models\BreachSourceHackStat;
use Heroic\ScannerApi\Models\BreachType;
use Heroic\ScannerApi\Models\DomainVerification;
use Heroic\ScannerApi\Models\DomainVerificationEmail;
use Heroic\ScannerApi\Traits\MonitoringAPI;
use Illuminate\Support\Facades\Log;
use Throwable;

class BreachController extends Controller
{
    use MonitoringAPI;

    protected $user;
    protected $team;
    protected $appName;
    protected $breachType;
    protected $breachData;

    public function __construct($appName, $breachType, $breachData, $user = null, $team = null)
    {
        $this->user = $user;
        $this->team = $team;
        $this->appName = $appName;
        $this->breachType = $breachType;
        $this->breachData = $breachData;
    }

    public function saveToMonitorList($active = null, $ipaddress1 = null, $ipaddress2 = null)
    {
        try {
            $breachType = BreachType::where('name', $this->breachType)->first();
            $teamId = NULL;

            if (!is_null($this->team)) {
                $teamId = $this->team->id;
            }

            if (is_null($active) || $active == false) {
                if ($this->breachType == 'email' || $this->breachType == 'emaildomain' || $this->breachType == 'ipaddress' || $this->breachType == 'phonenumber' || $this->breachType == 'username' || $this->breachType == 'social_security_number') {
                    $active = false;
                    $isVerified = false;
                    $verifiedOn = null;
                }
            } else {
                $active = true;
                $isVerified = true;
                $verifiedOn = now();
            }

            if (env('RSA_DEMO') && $this->breachType == 'emaildomain') {
                $active = true;
                $isVerified = true;
                $verifiedOn = now();
            }

            if ($this->breachType == 'ipaddress' && !is_null($ipaddress1) && !is_null($ipaddress2)) {
                $data = ['breach_type_id' => $breachType->id, 'app' => $this->appName, 'data' => $this->breachData, 'is_active' => $active, 'user_id' => $this->user->id, 'team_id' => $teamId, 'is_verified' => $isVerified, 'verified_on' => $verifiedOn, 'start_ip' => $ipaddress1, 'end_ip' => $ipaddress2];
            } elseif ($this->breachType == 'emaildomain') {
                $domainName = $this->getDomainNameOnly($this->breachData);
                $originalDomain = $this->breachData;
                $originalDomain = rtrim($originalDomain, "/");
                $originalDomain = rtrim($originalDomain, "\\");
                $data = ['breach_type_id' => $breachType->id, 'app' => $this->appName, 'data' => $domainName, 'is_active' => $active, 'user_id' => $this->user->id, 'team_id' => $teamId, 'is_verified' => $isVerified, 'verified_on' => $verifiedOn, 'original_domain_url' => $originalDomain];
            } elseif ($this->breachType == 'social_security_number') {
                $data = ['breach_type_id' => $breachType->id, 'app' => $this->appName, 'data' => md5($this->breachData), 'ssn_no' => encrypt($this->breachData), 'is_active' => $active, 'user_id' => $this->user->id, 'team_id' => $teamId, 'is_verified' => $isVerified, 'verified_on' => $verifiedOn];
            } else {
                $data = ['breach_type_id' => $breachType->id, 'app' => $this->appName, 'data' => $this->breachData, 'is_active' => $active, 'user_id' => $this->user->id, 'team_id' => $teamId, 'is_verified' => $isVerified, 'verified_on' => $verifiedOn];
            }

            $breachMonitoring = BreachMonitorList::create($data);

            ProcessBreachMonitoring::dispatch($breachMonitoring, $this->user, $this->team)->onQueue('processing');
            // $this->addToMonitoringApi($this->team->agent_uuid,$data['data'],$this->breachType);
        } catch (Throwable $th) {
            $this->errorLog($th, 'BreachController - saveToMonitorList');
        }
    }

    public function getDomainNameOnly($domain)
    {
        $value = trim($domain);
        $disallowed = array('http://', 'https://', 'www.', 'WWW.', 'HTTP://', 'HTTPS://');
        foreach ($disallowed as $d) {
            if (strpos($value, $d) === 0) {
                $value = str_replace($d, '', $value);
            }
        }

        return $value;
    }

    public function getGraphData()
    {
        if (!is_null($this->team))
            return BreachSourceHackStat::where(['app' => $this->appName, 'team_id' => $this->team->id])->first();
        else
            return BreachSourceHackStat::where(['app' => $this->appName, 'user_id' => $this->user->id])->first();
    }

    public function breachCount()
    {
        if (!is_null($this->team))
            $breach = BreachSourceHackStat::where(['app' => $this->appName, 'team_id' => $this->team->id])->first();
        else
            $breach = BreachSourceHackStat::where(['app' => $this->appName, 'user_id' => $this->user->id])->first();

        $breachCount['email'] = 0;
        $breachCount['password'] = 0;
        $breachCount['passwordhash'] = 0;
        $breachCount['passwordhash'] = 0;
        $breachCount['ipaddress'] = 0;
        $breachCount['username'] = 0;
        $breachCount['phonenumber'] = 0;
        $breachCount['sensitive_data'] = 0;
        $breachCount['geographic_data'] = 0;
        $breachCount['social_identity_data'] = 0;
        $breachCount['financial_data'] = 0;
        $breachCount['personal_data'] = 0;
        $breachCount['social_security_number'] = 0;
        if (!is_null($breach)) {
            $breachCount['email'] = $breach->email;
            $breachCount['password'] = $breach->password;
            $breachCount['passwordhash'] = $breach->passwordhash;
            $breachCount['ipaddress'] = $breach->ipaddress;
            $breachCount['username'] = $breach->username;
            $breachCount['phonenumber'] = $breach->phonenumber;
            $breachCount['sensitive_data'] = $breach->sensitive_data;
            $breachCount['geographic_data'] += $breach->geographic_data;
            $breachCount['social_identity_data'] = $breach->social_identity_data;
            $breachCount['financial_data'] = $breach->financial_data;
            $breachCount['personal_data'] = $breach->personal_data;
            $breachCount['social_security_number'] = $breach->social_security_number;
        }

        return $breachCount;
    }

    public function delete($id)
    {
        $breach = BreachMonitorList::findOrFail($id);
        $breach->delete();
    }

    public function deleteEpicAppRecords()
    {
        /* Breach Monitored List */
        $breachData = BreachMonitorList::where('team_id', $this->team->id)->get();
        if (!$breachData->isEmpty()) {
            foreach ($breachData as $breach) {
                $breach->delete();
            }
        }
        /* Domain Verification List */
        $domainVerificationData = DomainVerification::where('team_id', $this->team->id)->get();
        if (!$domainVerificationData->isEmpty()) {
            foreach ($domainVerificationData as $domainVerification) {
                $domainVerification->delete();
            }
        }
        /* Domain Verification Email List */
        $domainVerificationEmails = DomainVerificationEmail::where('team_id', $this->team->id)->get();
        if (!$domainVerificationEmails->isEmpty()) {
            foreach ($domainVerificationEmails as $domainVerificationEmail) {
                $domainVerificationEmail->delete();
            }
        }
        /* Breach Data Stats */
        $breachStats = BreachSourceHackStat::where('team_id', $this->team->id)->first();
        if (!is_null($breachStats)) {
            $breachStats->delete();
        }
    }
}
