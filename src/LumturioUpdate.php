<?php

namespace LumturioJira;

class LumturioUpdate
{
    protected $data;

    public function __construct(\stdClass $data)
    {
        $this->data = $data;
    }

    public function getShortName() : string
    {
        return trim($this->data->short_name);
    }

    public function getSecureVersion() : ?string
    {
        foreach ($this->getDrupalUpdates()->releases->release as $release) {
            if (empty($release->terms)) {
                continue;
            }

            foreach ($release->terms[0] as $term) {
                if (('Release type' == $term->name) &&
                    ('Security update' == $term->value)) {
                    return reset($release->version);
                }
            };
        }

        return null;
    }

    protected function getMajorVersion() : string
    {
        return preg_filter('/^([0-9]+)\..*/', '\1', $this->data->current_version);
    }

    protected function getDrupalUpdates() : \SimpleXMLElement
    {
        $url = "https://updates.drupal.org/release-history/{$this->getShortName()}/{$this->getMajorVersion()}.x";

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_VERBOSE, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 2);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        return simplexml_load_string(curl_exec($curl));
    }
}
