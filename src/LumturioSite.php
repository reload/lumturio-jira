<?php

namespace LumturioJira;

class LumturioSite
{
    protected $data;

    public function __construct(\stdClass $data)
    {
        $this->data = $data;
    }

    public function getId() : string
    {
        return $this->data->id;
    }

    public function getSite() : string
    {
        return $this->data->site_url;
    }

    public function getHostname() : string
    {
        return $this->data->site_hostname;
    }

    public function getDescription() : string
    {
        return $this->data->info_description;
    }

    public function getInfoTags() : array
    {
        return array_map('trim', (array) $this->data->info_tags);
    }

    public function isDrupal() : bool
    {
        return (1 == preg_match('/^DRUPAL/', $this->data->engine_version));
    }

    public function hasSLA() : bool
    {
        foreach ($this->getInfoTags() as $tag) {
            if ('SLA' == $tag) {
                return true;
            }
        }

        return false;
    }

    public function getJiraProject() : ?string
    {
        foreach ($this->getInfoTags() as $tag) {
            if (preg_match('/^JIRA:(.+)$/', $tag, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    public function getSecurityUpdates() : array
    {
        return array_map(function ($update) {
            return new LumturioUpdate($update);
        }, (array) $this->data->list_need_security_update);
    }
}
