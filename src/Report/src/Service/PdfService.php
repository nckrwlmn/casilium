<?php

declare(strict_types=1);

namespace Report\Service;

use Carbon\CarbonInterface;
use DateTimeImmutable;
use DateTimeZone;
use Dompdf\Dompdf;
use Dompdf\Options;
use Mezzio\Template\TemplateRendererInterface;
use Organisation\Entity\Organisation;
use ServiceLevel\Entity\BusinessHours;

use function ksort;
use function realpath;
use function sprintf;

class PdfService
{
    private string $fontCacheDir;

    private TemplateRendererInterface $renderer;

    /** @var array<string, mixed> */
    private array $options;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(TemplateRendererInterface $renderer, string $fontCacheDir, array $options = [])
    {
        $this->renderer     = $renderer;
        $this->fontCacheDir = $fontCacheDir;
        $this->options      = $options;
    }

    public function generateExecutiveReport(
        array $stats,
        Organisation $organisation,
        CarbonInterface $startDate,
        CarbonInterface $endDate,
        array $reportConfig = [],
        array $unresolvedTickets = [],
    ): string {
        $logoPath         = $this->options['logo_path'] ?? './public/img/casilium-black.svg';
        $resolvedLogoPath = realpath($logoPath);
        if (false !== $resolvedLogoPath) {
            $logoPath = $resolvedLogoPath;
        }

        $html = $this->renderer->render('report::executive-report-pdf', [
            'layout'       => false,
            'stats'        => $stats,
            'organisation' => $organisation,
            'startDate'    => $startDate,
            'endDate'      => $endDate,
            'logoPath'     => $logoPath,
            'reportConfig' => $reportConfig,
            'unresolved'   => $unresolvedTickets,
        ]);

        return $this->renderPdf($html);
    }

    /**
     * @param array<string, mixed> $companyConfig
     */
    public function generateSlaDocument(Organisation $organisation, array $companyConfig = []): string
    {
        $sla           = $organisation->getSla();
        $businessHours = $sla->getBusinessHours();

        $logoPath = $companyConfig['logo_path'] ?? '';
        if ($logoPath !== '') {
            $resolvedLogoPath = realpath($logoPath);
            if (false !== $resolvedLogoPath) {
                $logoPath = $resolvedLogoPath;
            }
        }

        $targets = [];
        foreach ($sla->getSlaTargets() as $target) {
            $targets[$target->getPriority()->getId()] = $target;
        }
        ksort($targets);

        $schedule = $this->buildBusinessHoursSchedule($businessHours);

        $html = $this->renderer->render('report::sla-document-pdf', [
            'layout'       => false,
            'organisation' => $organisation,
            'slaTargets'   => $targets,
            'schedule'     => $schedule,
            'logoPath'     => $logoPath,
            'timezone'     => $businessHours->getTimezone(),
            'company'      => $companyConfig,
        ]);

        return $this->renderPdf($html);
    }

    /**
     * @return array<int, array{day: string, active: bool, start: ?string, end: ?string}>
     */
    private function buildBusinessHoursSchedule(BusinessHours $businessHours): array
    {
        $tz   = new DateTimeZone($businessHours->getTimezone());
        $days = [
            ['Monday',    $businessHours->getMonActive(), $businessHours->getMonStart(), $businessHours->getMonEnd()],
            ['Tuesday',   $businessHours->getTueActive(), $businessHours->getTueStart(), $businessHours->getTueEnd()],
            ['Wednesday', $businessHours->getWedActive(), $businessHours->getWedStart(), $businessHours->getWedEnd()],
            ['Thursday',  $businessHours->getThuActive(), $businessHours->getThuStart(), $businessHours->getThuEnd()],
            ['Friday',    $businessHours->getFriActive(), $businessHours->getFriStart(), $businessHours->getFriEnd()],
            ['Saturday',  $businessHours->getSatActive(), $businessHours->getSatStart(), $businessHours->getSatEnd()],
            ['Sunday',    $businessHours->getSunActive(), $businessHours->getSunStart(), $businessHours->getSunEnd()],
        ];

        $schedule = [];
        foreach ($days as [$day, $active, $rawStart, $rawEnd]) {
            $start = null;
            $end   = null;
            if ($active && $rawStart !== null && $rawEnd !== null) {
                $start = (new DateTimeImmutable($rawStart, $tz))->format('g:ia');
                $end   = (new DateTimeImmutable($rawEnd, $tz))->format('g:ia');
            }

            $schedule[] = [
                'day'    => $day,
                'active' => $active,
                'start'  => $start,
                'end'    => $end,
            ];
        }

        return $schedule;
    }

    private function renderPdf(string $html): string
    {
        $chroot = $this->options['chroot'] ?? null;
        if (null === $chroot || '' === $chroot) {
            $chroot = sprintf('%s/../../../..', __DIR__);
        }

        $options = new Options();
        $options->setIsRemoteEnabled($this->options['remote_enabled'] ?? true);
        $options->setDefaultFont($this->options['default_font'] ?? 'Helvetica');
        $options->setDpi($this->options['dpi'] ?? 96);
        $options->setChroot($chroot);
        $options->setFontCache($this->fontCacheDir);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper(
            $this->options['paper'] ?? 'A4',
            $this->options['orientation'] ?? 'portrait'
        );
        $dompdf->render();

        return $dompdf->output();
    }
}
