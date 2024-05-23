<?php

namespace Heroic\ScannerApi\Traits;


trait AgentApi
{
    public function guzzleClientCall($url,$method,$body = NULL)
    {
        try{
            if(is_null($body)) {
                $client = new \GuzzleHttp\Client([
                    'base_url' => [$url],
                    'headers' => [
                        'Accept' => 'application/json',
                        'content-type' => 'application/json',
                        'Authorization' => env('CASSANDRA_API_KEY'),
                        'CF-Access-Client-Id' => env('CLOUDFLARE_CLIENT_ID'),
                        'CF-Access-Client-Secret' => env('CLOUDFLARE_CLIENT_SECRET')
                    ]
                ]);
            }else{
                $client = new \GuzzleHttp\Client([
                    'base_url' => [$url],
                    'headers' => [
                        'Accept' => 'application/json',
                        'content-type' => 'application/json',
                        'Authorization' => env('CASSANDRA_API_KEY'),
                        'CF-Access-Client-Id' => env('CLOUDFLARE_CLIENT_ID'),
                        'CF-Access-Client-Secret' => env('CLOUDFLARE_CLIENT_SECRET')
                    ],
                    'json' => $body

                ]);
            }

            $response = $client->request($method, $url);
            $body = $response->getBody();
            $stringBody = (string) $body;
            $response = json_decode($stringBody);
            return $response;
        } catch(\Exception $e){
            return;
        }

    }

    public function signUpApi($agentUuid,$email,$accountType)
    {
        $url = env('DB_UPLOADER_BASE_URL').'/users/sign-up';

        $data = [
            "uuid" => $agentUuid,
            "email" => $email,
            "account_type" => $accountType
        ];
        $response = $this->guzzleClientCall($url,'POST',$data);
    }

    public function getLicenseKey($agentUuid)
    {
        $url = env('DB_UPLOADER_BASE_URL').'/user/agent/get-license-key';

        $data = [
            "user_uuid" => $agentUuid
        ];
        $response = $this->guzzleClientCall($url,'GET',$data);
        return $response;
    }

    public function getDevices($agentUuid)
    {
        $url = env('DB_UPLOADER_BASE_URL').'/user/agent/devices';

        $data = [
            "user_uuid" => $agentUuid
        ];
        $response = $this->guzzleClientCall($url,'GET',$data);
        return $response;
    }

    public function getDeviceDetails($deviceId)
    {
        $url = env('DB_UPLOADER_BASE_URL').'/user/agent/device-details';
        $data = [
            "device_uuid" => $deviceId
        ];
        $response = $this->guzzleClientCall($url,'GET',$data);
        return $response;
    }
}
