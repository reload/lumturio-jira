<?php

declare(strict_types=1);

namespace LumturioJira;

use RuntimeException;
use stdClass;

class Lumturio
{
    protected string $apiUrl;

    protected string $apiToken;

    public function __construct()
    {
        $this->apiUrl = 'https://app.lumturio.com/api/';
        $this->apiToken = \getenv('LUMTURIO_TOKEN') ?: '';
    }

    protected function callApi(string $path): stdClass
    {
        $curl = \curl_init();
        \curl_setopt($curl, \CURLOPT_URL, $this->apiUrl . $path);
        \curl_setopt($curl, \CURLOPT_VERBOSE, false);
        \curl_setopt($curl, \CURLOPT_SSL_VERIFYPEER, 2);
        \curl_setopt($curl, \CURLOPT_SSL_VERIFYHOST, 2);
        \curl_setopt($curl, \CURLOPT_RETURNTRANSFER, 1);
        \curl_setopt($curl, \CURLOPT_HTTPHEADER, array('X-API-TOKEN:' . $this->apiToken));

        $result = \curl_exec($curl);

        if (!\is_string($result)) {
            throw new RuntimeException('Could not retrieve info from Lumturio.');
        }

        return \json_decode($result);
    }

    /**
     * @return array<int, array<int, \LumturioJira\LumturioSite>>
     */
    public function getSecurityUpdates(): array
    {
        $result = $this->callApi('/site.getsites');

        $result->secureSites = [];
        $result->insecureSites = [];

        if ($result->ok === true) {
            foreach ($result->items as $item) {
                $site = new LumturioSite($item);

                if ($site->isSecure()) {
                    $result->secureSites[] = $site;
                } else {
                    $result->insecureSites[] = $site;
                }
            }
        }

        return [$result->insecureSites, $result->secureSites];
    }
}
