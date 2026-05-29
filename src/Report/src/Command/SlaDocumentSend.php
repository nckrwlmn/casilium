<?php

declare(strict_types=1);

namespace Report\Command;

use MailService\Service\MailService;
use Monolog\Logger;
use Organisation\Entity\Organisation;
use Organisation\Service\OrganisationManager;
use Report\Service\PdfService;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function array_filter;
use function array_unique;
use function array_values;
use function count;
use function explode;
use function file_put_contents;
use function is_array;
use function preg_replace;
use function sprintf;
use function sys_get_temp_dir;
use function tempnam;
use function trim;
use function unlink;

class SlaDocumentSend extends Command
{
    private OrganisationManager $organisationManager;
    private PdfService $pdfService;
    private MailService $mailService;
    private Logger $logger;

    /** @var array<string, mixed> */
    private array $companyConfig;

    /**
     * @param array<string, mixed> $companyConfig
     */
    public function __construct(
        OrganisationManager $organisationManager,
        PdfService $pdfService,
        MailService $mailService,
        Logger $logger,
        array $companyConfig = []
    ) {
        parent::__construct();

        $this->organisationManager = $organisationManager;
        $this->pdfService          = $pdfService;
        $this->mailService         = $mailService;
        $this->logger              = $logger;
        $this->companyConfig       = $companyConfig;
    }

    public function configure(): void
    {
        $this->setName('report:sla-document')
            ->setDescription('Generate and email the SLA document');

        $this->addOption('org', null, InputOption::VALUE_REQUIRED, 'Organisation UUID');
        $this->addOption('to', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Recipient email');
        $this->addOption('out', null, InputOption::VALUE_REQUIRED, 'Write PDF to this file path');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            if (! $this->mailService->isEnabled()) {
                $output->writeln('<comment>Mail service disabled; skipping SLA document send</comment>');
                $this->logger->info('Mail service disabled; skipping SLA document send');
                return Command::SUCCESS;
            }

            $orgUuid = trim((string) $input->getOption('org'));
            if ($orgUuid === '') {
                $output->writeln('<error>Missing required --org option</error>');
                return Command::FAILURE;
            }

            $recipients = $this->parseRecipients($input->getOption('to'));
            if ($recipients === []) {
                $output->writeln('<error>Missing recipient(s); use --to</error>');
                return Command::FAILURE;
            }

            $organisation = $this->organisationManager->findOrganisationByUuid($orgUuid);
            if (! $organisation instanceof Organisation) {
                $output->writeln('<error>Organisation not found for provided UUID</error>');
                return Command::FAILURE;
            }

            if (! $organisation->hasSla()) {
                $output->writeln('<error>Organisation does not have an SLA assigned</error>');
                return Command::FAILURE;
            }

            $pdfContent = $this->pdfService->generateSlaDocument($organisation, $this->companyConfig);

            $attachmentPath = $this->writePdf($pdfContent, $input->getOption('out'), $organisation);

            $subject = sprintf('SLA Document - %s', $organisation->getName());
            $body    = $this->mailService->prepareBody('report_mail::sla-document', [
                'organisation' => $organisation,
            ]);

            foreach ($recipients as $recipient) {
                $this->mailService->sendWithAttachment($recipient, $subject, $body, $attachmentPath);
            }

            if ($input->getOption('out') === null) {
                unlink($attachmentPath);
            }

            $output->writeln(sprintf('<info>Sent SLA document to %d recipient(s)</info>', count($recipients)));
            $this->logger->info('SLA document sent', [
                'org_uuid'   => $orgUuid,
                'recipients' => $recipients,
            ]);

            return Command::SUCCESS;
        } catch (Throwable $e) {
            $output->writeln(sprintf('<error>Fatal error: %s</error>', $e->getMessage()));
            $this->logger->critical('SLA document send failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return Command::FAILURE;
        }
    }

    /**
     * @param array<int, string>|string|null $raw
     * @return array<int, string>
     */
    private function parseRecipients(array|string|null $raw): array
    {
        if ($raw === null) {
            return [];
        }

        $values = is_array($raw) ? $raw : [$raw];
        $split  = [];
        foreach ($values as $value) {
            $parts = explode(',', (string) $value);
            foreach ($parts as $part) {
                $split[] = trim($part);
            }
        }

        return array_values(array_unique(array_filter($split)));
    }

    private function writePdf(string $pdfContent, ?string $out, Organisation $organisation): string
    {
        $outputPath = null;
        if ($out !== null) {
            $outputPath = $out;
        }

        if ($outputPath === null || $outputPath === '') {
            $outputPath = $this->buildTempPath($organisation);
        }

        if (file_put_contents($outputPath, $pdfContent) === false) {
            throw new RuntimeException('Failed to write SLA document PDF');
        }
        return $outputPath;
    }

    private function buildTempPath(Organisation $organisation): string
    {
        $safeName = preg_replace('/[^A-Za-z0-9\-]/', '-', $organisation->getName());
        $temp     = tempnam(sys_get_temp_dir(), 'sla-doc-');
        if ($temp === false) {
            throw new RuntimeException('Failed to create temporary file for SLA document');
        }

        unlink($temp);

        return sprintf('%s-%s.pdf', $temp, $safeName);
    }
}
