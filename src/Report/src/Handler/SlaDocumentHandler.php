<?php

declare(strict_types=1);

namespace Report\Handler;

use Laminas\Diactoros\Response;
use Laminas\Diactoros\Stream;
use Organisation\Entity\Organisation;
use Organisation\Exception\OrganisationNotFoundException;
use Organisation\Service\OrganisationManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Report\Service\PdfService;
use RuntimeException;

use function preg_replace;
use function sprintf;

class SlaDocumentHandler implements RequestHandlerInterface
{
    private OrganisationManager $organisationManager;

    private PdfService $pdfService;

    /** @var array<string, mixed> */
    private array $companyConfig;

    /**
     * @param array<string, mixed> $companyConfig
     */
    public function __construct(
        OrganisationManager $organisationManager,
        PdfService $pdfService,
        array $companyConfig = []
    ) {
        $this->organisationManager = $organisationManager;
        $this->pdfService          = $pdfService;
        $this->companyConfig       = $companyConfig;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organisationUuid = $request->getAttribute('org_id');
        $organisation     = $this->organisationManager->findOrganisationByUuid($organisationUuid);
        if (! $organisation instanceof Organisation) {
            throw OrganisationNotFoundException::whenSearchingByUuid($organisationUuid);
        }

        if (! $organisation->hasSla()) {
            throw new RuntimeException('Organisation does not have an SLA assigned');
        }

        // generate PDF
        $pdfContent = $this->pdfService->generateSlaDocument($organisation, $this->companyConfig);

        // build filename
        $orgName  = preg_replace('/[^A-Za-z0-9\-]/', '-', $organisation->getName());
        $filename = sprintf('SLA-Document-%s.pdf', $orgName);

        // return PDF response
        $stream = new Stream('php://memory', 'w');
        $stream->write($pdfContent);

        $response = new Response();
        return $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', sprintf('inline; filename="%s"', $filename))
            ->withBody($stream);
    }
}
