<?php

namespace Heroic\ScannerApi\Http\Controllers\Breach;

use App\Http\Controllers\Controller;
use Heroic\ScannerApi\Http\Requests\Client\DomainVerification\EmailRequest;
use Heroic\ScannerApi\Http\Requests\Client\DomainVerification\EmailTokenRequest;
use Heroic\ScannerApi\Jobs\ProcessBreachMonitoring;
use Heroic\ScannerApi\Mail\EmailDomainVerification;
use Heroic\ScannerApi\Models\Activation;
use Heroic\ScannerApi\Models\BreachMonitorList;
use Heroic\ScannerApi\Models\BreachSourceHackStat;
use Heroic\ScannerApi\Models\BreachType;
use Heroic\ScannerApi\Models\DomainVerification;
use Heroic\ScannerApi\Models\DomainVerificationEmail;
use Heroic\ScannerApi\Models\DomainVerificationType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Ramsey\Uuid\Uuid;

class DomainVerificationController extends Controller
{
    protected $user;
    protected $team;
    protected $appName;

    public function __construct($appName, $user ,$team = null)
    {
        $this->user = $user;
        $this->team = $team;
        $this->appName = $appName;
    }

    public function getDomainNameOnly($domain)
    {
        $value = trim(strtolower($domain));
        $disallowed = array('http://', 'https://','www.','WWW.','HTTP://','HTTPS://');
        foreach($disallowed as $d) {
            if(strpos($value, $d) === 0) {
                $value = str_replace($d, '', $value);
            }
        }

        // $value = $this->trimTrailingSlash($value);

        return $value;
    }

    public function trimTrailingSlash($value)
    {
        $value = rtrim($value, "/");
        $value = rtrim($value, "\\");

        return $value;
    }

    public function createVerificationUrl($emailDomain)
    {
        try{
            if(!is_null($this->team)){
                $domainVerification = DomainVerification::where(['name'=>$emailDomain,'team_id'=>$this->team->id,'is_verified' => false,'app'=>$this->appName])->first();
            }else{
                $domainVerification = DomainVerification::where(['name'=>$emailDomain,'user_id'=>$this->user->id,'is_verified' => false,'app'=>$this->appName])->first();
            }

            if(is_null($domainVerification)){
                $data = [
                    'name' => $this->getDomainNameOnly($emailDomain),
                    'url' => $this->trimTrailingSlash($emailDomain),
                    'verification_token' => Uuid::uuid4(),
                    'app' => $this->appName,
                    'user_id' => $this->user->id,
                ];

                if(!is_null($this->team)){
                    $data['team_id'] = $this->team->id;
                }

                $domainVerification = DomainVerification::create($data);
            }
            $url = route('datacompromise.domain.verification',['id'=>$domainVerification->id]);
        }catch (\Exception $e){
            $domainVerification = null;
            $url = null;
        }
        return $url;
    }

    public function sendEmailToValidateDomain($request)
    {
        try{
            $status = 200;
            $domainVerification = DomainVerification::findOrFail($request->domain_id);
            $token = Uuid::uuid4();
            $data = [
                'to' => $request->email,
                'verification_token' => $token,
                'domain_verification_id' => $domainVerification->id,
                'user_id' => $this->user->id
            ];

            if(!is_null($this->team)){
                $data['team_id'] = $this->team->id;
            }

            $domainVerificationEmail = DomainVerificationEmail::create($data);
            $id = encrypt($domainVerificationEmail->id);
            $name = $this->user->first_name.' '.$this->user->last_name;
            Mail::send(new EmailDomainVerification($token,$domainVerification->name,"HEROIC: Email Domain Verification",$request->email,$name));
        }catch (\Exception $e){
            $status = 500;
            $id = null;
        }

        return compact('id','status');
    }

    public function getValidPublicUrl($url)
    {
        $prefixArray = ["http://","https://","http://www.","https://www."];

        $publicUrl = NULL;

        foreach($prefixArray as $prefix) {
            // Initialize an URL to the variable
            $url = $prefix.$url;

            // Use get_headers() function
            $headers = @get_headers($url);

            // Use condition to check the existence of URL
            if($headers && strpos( $headers[0], '200')) {
                //$status = "URL Exist";
                $publicUrl = $url;
                break;
            } /*else {
                $status = "URL Doesn't Exist";
            } */
        }

        return $publicUrl;
    }

    public function validateDomainViaEmailToken($request)
    {
        try{
            $status = 200;
            $id = decrypt($request->token_id);
            $domainVerificationEmail = DomainVerificationEmail::where(['id'=>$id,'verification_token' => $request->token])->first();
            if(is_null($domainVerificationEmail)){
                $status = 201;
            }else{
                $type = DomainVerificationType::where('name','email')->first();
                $domain = DomainVerification::findOrFail($domainVerificationEmail->domain_verification_id);
                $domain->is_verified = TRUE;
                $domain->domain_verification_type_id = $type->id;
                $domain->save();
                $this->changeDomainVerificationStatus($domain);
            }
        }catch (\Exception $e){
            $status = 500;
        }
        //return response("",$status);
        return compact('status');
    }

    public function validateDomainViaMetaTag($request)
    {
        try{
            $status = 200;
            $message = 'success';
            $domain = DomainVerification::where(['id' => $request->domain_id])->first();
            $url = $domain->url;

            if(!is_null($url)) {
                $tags = get_meta_tags($url);
                if(array_key_exists('heroic-domain-verification',$tags) && !empty($tags['heroic-domain-verification'])){
                    if($domain->verification_token == $tags['heroic-domain-verification']){
                        $type = DomainVerificationType::where('name','meta-tag')->first();
                        $domain->is_verified = TRUE;
                        $domain->domain_verification_type_id = $type->id;
                        $domain->save();
                        $this->changeDomainVerificationStatus($domain);
                    }else{
                        $status = 404;
                        $message = "Are you sure it's on the site at the root of the domain and publicly accessible? You can close this dialogue and hit the \"verify\" button again to give it another go when you're ready.";
                    }
                }else{
                    $status = 404;
                    $message = "Are you sure it's on the site at the root of the domain and publicly accessible? You can close this dialogue and hit the \"verify\" button again to give it another go when you're ready.";
                }
            }else{
                $status = 404;
                $message = 'URL is not reachable publically.';
            }
        }catch (\Exception $e) {
            $status = 500;
            //$message = $this->getCatchMessage($e);
            $message = $e->getMessage();
        }
        return compact('message','status');
    }

    public function validateDomainViaFile($request)
    {
        try{
            $status = 200;
            $message = 'success';

            $domain = DomainVerification::where(['id' => $request->domain_id])->first();
            $url = $domain->url;

            if(!is_null($url)) {
                $url = $url."/heroic-domain-verification.txt";
                $file = file_get_contents($url);

                if($domain->verification_token == trim($file)){
                    $type = DomainVerificationType::where('name','file-upload')->first();
                    $domain->is_verified = TRUE;
                    $domain->domain_verification_type_id = $type->id;
                    $domain->save();
                    $this->changeDomainVerificationStatus($domain);
                }else{
                    $status = 404;
                    $message = "Are you sure it's on the site at the root of the domain with the correct content and is publicly accessible? You can close this dialogue and hit the \"verify\" button again to give it another go when you're ready.";
                }
            }else{
                $status = 404;
                $message = 'URL is not reachable publically.';
            }
        }catch (\Exception $e) {
            $status = 500;
            $message = "Are you sure it's on the site at the root of the domain with the correct content and is publicly accessible? You can close this dialogue and hit the \"verify\" button again to give it another go when you're ready.";
            //$message = $this->getCatchMessage($e);
        }
        //return response()->json(compact('message'),$status);
        return compact('message','status');
    }

    public function validateDomainViaTxt($request)
    {
        try{
            $status = 200;
            $message = 'success';

            $domain = DomainVerification::where(['id' => $request->domain_id])->first();
            $url = $this->getDomainNameOnly($domain->url);
            $txts = dns_get_record($url, DNS_TXT);
            $verificationFlag = false;
            foreach($txts as $txt){
                if($txt['txt'] == "heroic-domain-verification=$domain->verification_token"){
                    $verificationFlag = true;
                }
            }
            //$token = str_replace("heroic-domain-verification=","",$txts);

            if($verificationFlag){
                $type = DomainVerificationType::where('name','txt-record')->first();
                $domain->is_verified = TRUE;
                $domain->domain_verification_type_id = $type->id;
                $domain->save();
                $this->changeDomainVerificationStatus($domain);
            }else{
                $status = 404;
                $message = "The TXT record was not found on the domain. It can take some time for DNS to propagate, leave this page open and hit the verify button again a little later if you're confident the record is correct.";
            }
        }catch (\Exception $e) {
            $status = 500;
            $message = "Are you sure it's on the site at the root of the domain with the correct content and is publicly accessible? You can close this dialogue and hit the \"verify\" button again to give it another go when you're ready.";
            //$message = $this->getCatchMessage($e);
        }
        //return response()->json(compact('message'),$status);
        return compact('message','status');
    }

    public function changeDomainVerificationStatus($domain)
    {
        $breachType = BreachType::where('name','emaildomain')->first();

        $data = [
            'breach_type_id'=>$breachType->id,
            'app'=> $this->appName,
            'data'=>$domain->name,
            'user_id'=>$domain->user_id
        ];

        if(!is_null($this->team)) {
            $data['team_id'] = $domain->team_id;
        }

        $breach = BreachMonitorList::where($data)->first();
        if(!is_null($breach)){
            $breach->is_active = TRUE;
            $breach->is_verified = TRUE;
            $breach->save();
        }
    }
    public function createVerificationId($emailDomain)
    {
        try{
            if(!is_null($this->team)){
                $domainVerification = DomainVerification::where(['name'=>$emailDomain,'team_id'=>$this->team->id,'is_verified' => false,'app'=>$this->appName])->first();
            }else{
                $domainVerification = DomainVerification::where(['name'=>$emailDomain,'user_id'=>$this->user->id,'is_verified' => false,'app'=>$this->appName])->first();
            }

            if(is_null($domainVerification)){
                $data = [
                    'name' => $this->getDomainNameOnly($emailDomain),
                    'url' => $emailDomain,
                    'verification_token' => Uuid::uuid4(),
                    'app' => $this->appName,
                    'user_id' => $this->user->id,
                ];
                if(!is_null($this->team)){
                    $data['team_id'] = $this->team->id;
                }

                $domainVerification = DomainVerification::create($data);
            }
            $did = $domainVerification->id;
        }catch (\Exception $e){
            $domainVerification = null;
            $did = null;
        }
        return $did;
    }
}
