<?php

declare(strict_types=1);

namespace Report;

use Mezzio\Application;

/**
 * The configuration provider for the Mfa module
 *
 * @see https://docs.zendframework.com/zend-component-installer/
 */
class ConfigProvider
{
    /**
     * Returns the configuration array
     *
     * To add a bit of a structure, each section is defined in a separate
     * method which returns an array with its configuration.
     */
    public function __invoke(): array
    {
        return [
            'dependencies'  => $this->getDependencies(),
            'templates'     => $this->getTemplates(),
            'access_filter' => $this->getAccessFilter(),
            'console'       => $this->getConsoleCommands(),
        ];
    }

    /**
     * Returns the container dependencies
     */
    public function getDependencies(): array
    {
        return [
            'delegators' => [
                Application::class => [
                    RouterDelegator::class,
                ],
            ],
            'factories'  => [
                Command\ExecutiveReportSend::class    => Command\Factory\ExecutiveReportSendFactory::class,
                Command\SlaDocumentSend::class        => Command\Factory\SlaDocumentSendFactory::class,
                Handler\ExecutiveReportHandler::class => Handler\Factory\ExecutiveReportHandlerFactory::class,
                Handler\SlaDocumentHandler::class     => Handler\Factory\SlaDocumentHandlerFactory::class,
                Service\PdfService::class             => Service\Factory\PdfServiceFactory::class,
                Service\ReportService::class          => Service\Factory\ReportServiceFactory::class,
            ],
        ];
    }

    /**
     * Returns the templates configuration
     */
    public function getTemplates(): array
    {
        return [
            'paths' => [
                'report'      => [__DIR__ . '/../templates/'],
                'report_mail' => [__DIR__ . '/../templates/report_mail/'],
            ],
        ];
    }

    /**
     * Define access filter for accessing organisation
     */
    public function getAccessFilter(): array
    {
        return [
            'routes' => [
                'report' => [
                    ['allow' => '@'],
                ],
            ],
        ];
    }

    public function getConsoleCommands(): array
    {
        return [
            'commands' => [
                Command\ExecutiveReportSend::class,
                Command\SlaDocumentSend::class,
            ],
        ];
    }
}
