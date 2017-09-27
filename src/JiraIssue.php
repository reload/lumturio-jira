<?php

namespace LumturioJira;

use JiraRestApi\Configuration\ArrayConfiguration;
use JiraRestApi\Issue\IssueField;
use JiraRestApi\Issue\IssueService;
use JiraRestApi\JiraException;

class JiraIssue
{
    protected $site;

    protected $hostname;

    protected $description;

    protected $project;

    protected $module;

    protected $version;

    protected $iss;

    public function __construct(LumturioSite $site, string $hostname, string $project, string $module, string $version)
    {
        $this->site = $site;
        $this->hostname = $hostname;
        $this->description = $site->getDescription() ?: $this->hostname;
        $this->project = $project;
        $this->module = $module;
        $this->version = $version;
        $this->issueType = getenv('JIRA_ISSUETYPE') ?: 'Bug';

        $this->issueService = new IssueService(new ArrayConfiguration(
            array(
                'jiraHost' => getenv('JIRA_HOST'),
                'jiraUser' => getenv('JIRA_USER'),
                'jiraPassword' => getenv('JIRA_PASS'),
            )
        ));
    }

    public function existingIssue() : ?string
    {
        $jql = <<<EOT
PROJECT = {$this->project} AND labels IN ({$this->label()}) AND labels in ({$this->hostname}) ORDER BY created DESC
EOT;

        $ret = $this->issueService->search($jql);

        if ($ret->total > 0) {
            return  reset($ret->issues)->key;
        }

        return null;
    }

    public function create() : string
    {
        $issueField = new IssueField();

        $issueField->setProjectKey($this->project)
            ->setSummary("{$this->module} ({$this->version})")
            ->setIssueType($this->issueType)
            ->setDescription($this->body())
            ->addLabel($this->hostname)
            ->addLabel($this->module)
            ->addLabel($this->label());

        unset($issueField->priority);

        $ret = $this->issueService->create($issueField);

        //If success, Returns a link to the created issue.
        return $ret->key;
    }

    protected function label() : string
    {
        return "{$this->module}:{$this->version}";
    }

    protected function body() : string
    {
        return <<<EOT
* Drupal project: [{$this->module}|https://drupal.org/project/{$this->module}]

* Security update: [{$this->version}|https://www.drupal.org/project/{$this->module}/releases/{$this->version}]

* Site: [{$this->description}|{$this->site->getSite()}]

* Lumturio: [module overview|https://app.lumturio.com/#/user/site/{$this->site->getId()}/modules]
EOT;
    }
}
