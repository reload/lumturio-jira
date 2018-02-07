<?php

namespace LumturioJira;

use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Stecman\Component\Symfony\Console\BashCompletion\Completion\CompletionAwareInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The default sync command.
 */
class SyncCommand extends Command implements CompletionAwareInterface
{
    /**
     * {@inheritDoc}
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * {@inheritDoc}
     */
    public function completeOptionValues($optionName, CompletionContext $context)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function completeArgumentValues($argumentName, CompletionContext $context)
    {
    }

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this
            ->setName('sync')
            ->setDescription('Sync Lumturio status to Jira')
            ->setHelp('This command allows you to synchronize the security status from Lumturio to Jira.')
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Do dry run (dont change anything)',
                null
            );
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $api = new Lumturio();

        [$insecureSites, $secureSites] = $api->getSecurityUpdates();

        $timestamp = gmdate(DATE_ATOM);
        foreach ($secureSites as $secureSite) {
            $this->logLine($output, "{$timestamp} - {$secureSite->getHostname()} - is secure.");
        }

        foreach ($insecureSites as $insecureSite) {
            if (!$insecureSite->isDrupal()) {
                $this->logLine($output, "{$timestamp} - {$insecureSite->getHostname()} - is insecure but is not a Drupal site.");
                continue;
            }

            if (!$insecureSite->hasSLA()) {
                $this->logLine($output, "{$timestamp} - {$insecureSite->getHostname()} - is insecure but has no SLA.");
                continue;
            }

            $project = $insecureSite->getJiraProject();

            if (empty($project)) {
                $this->logLine($output, "{$timestamp} - {$insecureSite->getHostname()} - is insecure but has no known JIRA project.");
                continue;
            }

            foreach ($insecureSite->getSecurityUpdates() as $update) {
                $this->processSiteUpdate($insecureSite, $project, $update, $input, $output);
            };
        }
    }

    protected function processSiteUpdate(
        LumturioSite $site,
        string $project,
        LumturioUpdate $update,
        InputInterface $input,
        OutputInterface $output
    ) {
        $version = $update->getSecureVersion();

        $issue = new JiraIssue(
            $site,
            $site->getHostname(),
            $project,
            $update->getShortname(),
            $version
        );

        $timestamp = gmdate(DATE_ATOM);

        $this->log($output, "{$timestamp} - {$site->getHostname()} - {$update->getShortName()}:{$version} - ");

        try {
            $key = $issue->existingIssue();
        } catch (\Throwable $t) {
            $this->logLine($output, "ERROR ACCESSING JIRA: {$t->getMessage()}.");

            return;
        }

        if ($key) {
            $this->logLine($output, "Existing issue {$key}.");

            return;
        }

        if ($input->getOption('dry-run')) {
            $this->logLine($output, "Would have created an issue in {$project} if not a dry run.");

            return;
        }

        $key = $issue->create();

        $this->logLine($output, "Created issue {$key}");
    }

    protected function log(OutputInterface $output, string $message)
    {
        if ($output->getVerbosity() < OutputInterface::VERBOSITY_VERBOSE) {
            return;
        }

        $output->write($message);
    }

    protected function logLine(OutputInterface $output, string $message)
    {
        if ($output->getVerbosity() < OutputInterface::VERBOSITY_VERBOSE) {
            return;
        }

        $output->writeln($message);
    }
}
