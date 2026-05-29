<?php

declare(strict_types=1);

namespace Report\Handler\Factory;

use Organisation\Service\OrganisationManager;
use Psr\Container\ContainerInterface;
use Report\Handler\SlaDocumentHandler;
use Report\Service\PdfService;

class SlaDocumentHandlerFactory
{
    public function __invoke(ContainerInterface $container): SlaDocumentHandler
    {
        $organisationManager = $container->get(OrganisationManager::class);
        $pdfService          = $container->get(PdfService::class);
        $config              = $container->get('config');
        $companyConfig       = $config['reports']['company'] ?? [];

        return new SlaDocumentHandler($organisationManager, $pdfService, $companyConfig);
    }
}
