<?php

namespace LumturioJira;

class Lumturio
{
    protected $apiUrl = 'https://app.lumturio.com/api/';

    protected $apiToken;

    public function __construct()
    {
        $this->apiToken = getenv('LUMTURIO_TOKEN');
    }

    protected function callApi(string $path) : \stdClass
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $this->apiUrl . $path);
        curl_setopt($curl, CURLOPT_VERBOSE, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 2);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('X-API-TOKEN:' . $this->apiToken));

        return json_decode(curl_exec($curl));
    }

    public function getSecurityUpdates() : array
    {
        $result = $this->callApi('/site.getsites');

        $result->securityUpdates = [];
        
        if ($result->ok == true) {
            foreach ($result->items as $item) {
                if (!empty(((array) $item->list_need_security_update))) {
                    $result->securityUpdates[] = new LumturioSite($item);
                }
            }
        }

        return $result->securityUpdates;
    }
}
