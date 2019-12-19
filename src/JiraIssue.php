<?php

namespace LumturioJira;

use JiraRestApi\Configuration\ArrayConfiguration;
use JiraRestApi\Issue\Comment;
use JiraRestApi\Issue\IssueField;
use JiraRestApi\Issue\IssueService;
use JiraRestApi\Issue\Visibility;
use JiraRestApi\Issue\Watcher;
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

    protected $cc;

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

    public function create() : ?string
    {
        $this->cc = $this->site->getJiraCC();

        $issueField = new IssueField();

        $issueField->setProjectKey($this->project)
            ->setSummary("{$this->module} ({$this->version})")
            ->setIssueType($this->issueType)
            ->setDescription($this->body())
            ->addLabel($this->hostname)
            ->addLabel($this->module)
            ->addLabel($this->label());

        unset($issueField->priority);
        unset($issueField->versions);

        try {
            $ret = $this->issueService->create($issueField);
        } catch (\Throwable $t) {
            echo "Could not create issue {$this->hostname}/{$this->project}:{$this->version}: {$t->getMessage()}" . PHP_EOL;

            return null;
        }

        try {
            $this->issueService->addComment($ret->key, $this->restrictedComment());
        } catch (\Throwable $t) {
            echo "Could not add comment to issue {$ret->key}: {$t->getMessage()}" . PHP_EOL;
        }

        foreach ($this->cc as $cc) {
            try {
                $this->issueService->addWatcher($ret->key, $cc);
            } catch (\Throwable $t) {
                echo "Adding {$cc} as watcher to {$ret->key}: {$t->getMessage()}" . PHP_EOL;

                return null;
            }
        }

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
* Site: [{$this->description}|{$this->site->getSite()}]
* Sikkerhedsopdatering: [{$this->module}|https://drupal.org/project/{$this->module}] version [{$this->version}|https://www.drupal.org/project/{$this->module}/releases/{$this->version}]
EOT;
    }

    protected function restrictedComment() : Comment
    {
        $cc = $this->formatCC($this->cc);
        $body = <<<EOT
* Lumturio: [module overview|https://app.lumturio.com/#/user/site/{$this->site->getId()}/modules]
* [Guide i Confluence|https://reload.atlassian.net/wiki/spaces/RW/pages/89030669/Sikkerhedstriage]
* [JIRA Security Dashbaord|https://reload.atlassian.net/secure/Dashboard.jspa?selectPageId=12600]

{$cc}
EOT;

        $comment = new Comment();
        $comment->setBody($body);

        $visibility = new Visibility();
        $visibility->setType('role');
        $visibility->setValue('Developers');

        $comment->visibility = $visibility;

        return $comment;
    }

    protected function formatCC(array $cc) : string
    {
        if (empty($cc)) {
            return '';
        }

        return 'Bemærk der er følgende watchers på issuet: [~' . implode('], [~', $this->cc) . '].';
    }

}
