<?php

declare(strict_types=1);

namespace LumturioJira;

use stdClass;

class LumturioSite
{
    protected stdClass $data;

    public function __construct(stdClass $data)
    {
        $this->data = $data;
    }

    public function getId(): string
    {
        return $this->data->id;
    }

    public function getSite(): string
    {
        return $this->data->site_url;
    }

    public function getHostname(): string
    {
        return $this->data->site_hostname;
    }

    public function getDescription(): string
    {
        return $this->data->info_description;
    }

    /**
     * @return array<string>
     */
    public function getInfoTags(): array
    {
        return \array_map('trim', (array) $this->data->info_tags);
    }

    public function isDrupal(): bool
    {
        return \preg_match('/^DRUPAL/', $this->data->engine_version) === 1;
    }

    public function hasSecuritySLA(): bool
    {
        foreach ($this->getInfoTags() as $tag) {
            if ($tag === 'SLA') {
                return true;
            }
        }

        return false;
    }

    public function getJiraProject(): ?string
    {
        foreach ($this->getInfoTags() as $tag) {
            if (\preg_match('/^JIRA:(?<projectKey>.+)$/', $tag, $matches)) {
                return $matches['projectKey'];
            }
        }

        return null;
    }

    public function isSecure(): bool
    {
        $insecure = (array) $this->data->list_need_security_update;

        return \count($insecure) === 0;
    }

    /**
     * @return array<string>
     */
    public function getJiraCC(): array
    {
        $cc = [];

        foreach ($this->getInfoTags() as $tag) {
            if (!\preg_match('/^JIRACC:(?<jiraUser>.+)$/', $tag, $matches)) {
                continue;
            }

            $cc[] = \urldecode($matches['jiraUser']);
        }

        return $cc;
    }

    /**
     * @return array<\LumturioJira\LumturioUpdate>
     */
    public function getSecurityUpdates(): array
    {
        return \array_map(static function ($update) {
            return new LumturioUpdate($update);
        }, (array) $this->data->list_need_security_update);
    }
}
