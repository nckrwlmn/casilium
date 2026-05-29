<?php

declare(strict_types=1);

namespace Report\Command\Factory;

use Logger\Service\LogService;
use MailService\Service\MailService;
use Organisation\Service\OrganisationManager;
use Psr\Container\ContainerInterface;
use Report\Command\SlaDocumentSend;
use Report\Service\PdfService;

class SlaDocumentSendFactory
{
    public function __invoke(ContainerInterface $container): SlaDocumentSend
    {
        $organisationManager = $container->get(OrganisationManager::class);
        $pdfService          = $container->get(PdfService::class);
        $mailService         = $container->get(MailService::class);
        $logger              = $container->get(LogService::class);
        $config              = $container->get('config');
        $companyConfig       = $config['reports']['company'] ?? [];

        return new SlaDocumentSend(
            $organisationManager,
            $pdfService,
            $mailService,
            $logger,
            $companyConfig
        );
    }
}
