<?php

declare(strict_types=1);

namespace Report;

use Mezzio\Application;
use Psr\Container\ContainerInterface;
use Report\Handler\ExecutiveReportHandler;
use Report\Handler\SlaDocumentHandler;

class RouterDelegator
{
    public function __invoke(ContainerInterface $container, string $serviceName, callable $callback): Application
    {
        /** @var Application $app */
        $app = $callback();

        $app->get(
            '/report/sla/{org_id:[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}}[/]',
            SlaDocumentHandler::class,
            'report.sla-document'
        );

        $app->get(
            '/report/{org_id:[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}}[/]',
            ExecutiveReportHandler::class,
            'report.executive-report'
        );

        return $app;
    }
}
