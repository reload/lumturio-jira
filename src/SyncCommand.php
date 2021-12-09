<?php

declare(strict_types=1);

namespace LumturioJira;

use Reload\JiraSecurityIssue;
use Stecman\Component\Symfony\Console\BashCompletion\Completion\CompletionAwareInterface;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

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
     * Return possible values for the named option
     *
     * @param string $optionName
     *
     * @return array<string>
     *
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
     */
    public function completeOptionValues($optionName, CompletionContext $context): array
    {
        return [];
    }

    /**
     * Return possible values for the named argument
     *
     * @param string $argumentName
     *
     * @return array<string>
     *
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
     */
    public function completeArgumentValues($argumentName, CompletionContext $context): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('sync')
            ->setDescription('Sync Lumturio status to Jira')
            ->setHelp('This command allows you to synchronize the security status from Lumturio to Jira.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do dry run (dont change anything)', null);
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $api = new Lumturio();

        [$insecureSites, $secureSites] = $api->getSecurityUpdates();

        $timestamp = \gmdate(\DATE_ATOM);

        foreach ($secureSites as $secureSite) {
            $this->logLine($output, "{$timestamp} - {$secureSite->getHostname()} - is secure.");
        }

        foreach ($insecureSites as $insecureSite) {
            if (!$insecureSite->isDrupal()) {
                $this->logLine(
                    $output,
                    "{$timestamp} - {$insecureSite->getHostname()} - is insecure but is not a Drupal site.",
                );

                continue;
            }

            if (!$insecureSite->hasSecuritySLA()) {
                $this->logLine($output, "{$timestamp} - {$insecureSite->getHostname()} - is insecure but has no SLA.");

                continue;
            }

            $project = $insecureSite->getJiraProject();

            if (\is_null($project)) {
                $this->logLine(
                    $output,
                    "{$timestamp} - {$insecureSite->getHostname()} - is insecure but has no known JIRA project.",
                );

                continue;
            }

            foreach ($insecureSite->getSecurityUpdates() as $update) {
                $this->processSiteUpdate($insecureSite, $project, $update, $input, $output);
            }
        }

        return 0;
    }

    protected function processSiteUpdate(
        LumturioSite $site,
        string $project,
        LumturioUpdate $update,
        InputInterface $input,
        OutputInterface $output,
    ): void {
        $version = $update->getSecureVersion();
        $module = $update->getShortname();
        $description = $site->getDescription() ?: $site->getHostname();
        $watchers = $site->getJiraWatchers();

        // phpcs:disable SlevomatCodingStandard.Files.LineLength.LineTooLong, Generic.Files.LineLength.TooLong
        $body = <<<EOT
- Site: [{$description}|{$site->getSite()}]
- Sikkerhedsopdatering: [{$module}|https://drupal.org/project/{$module}] version [{$version}|https://www.drupal.org/project/{$module}/releases/{$version}]
EOT;
        // phpcs:enable SlevomatCodingStandard.Files.LineLength.LineTooLong, Generic.Files.LineLength.TooLong

        $issue = (new JiraSecurityIssue())
            ->setProject($project)
            ->setKeyLabel($site->getHostname())
            ->setKeyLabel("{$module}")
            ->setKeyLabel("{$module}:{$version}")
            ->setTitle("{$module} ({$version})")
            ->setBody($body);

        foreach ($watchers as $watcher) {
            $issue->setWatcher($watcher);
        }

        $timestamp = \gmdate(\DATE_ATOM);

        $this->log($output, "{$timestamp} - {$site->getHostname()} - {$update->getShortName()}:{$version} - ");

        try {
            $key = $issue->exists();
        } catch (Throwable $t) {
            $this->logLine($output, "ERROR ACCESSING JIRA: {$t->getMessage()}.");

            return;
        }

        if (\is_string($key)) {
            $this->logLine($output, "Existing issue {$key}.");

            return;
        }

        if ($input->getOption('dry-run')) {
            $this->logLine($output, "Would have created an issue in {$project} if not a dry run.");

            return;
        }

        $key = $issue->ensure();

        $this->logLine($output, "Created issue {$key}.");
    }

    protected function log(OutputInterface $output, string $message): void
    {
        if ($output->getVerbosity() < OutputInterface::VERBOSITY_VERBOSE) {
            return;
        }

        $output->write($message);
    }

    protected function logLine(OutputInterface $output, string $message): void
    {
        if ($output->getVerbosity() < OutputInterface::VERBOSITY_VERBOSE) {
            return;
        }

        $output->writeln($message);
    }
}
