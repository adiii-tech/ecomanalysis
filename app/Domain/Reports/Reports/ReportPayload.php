<?php

declare(strict_types=1);

namespace App\Domain\Reports\Reports;

use App\Support\Caveat;
use App\Support\Metric;
use App\Support\Verdict;

/**
 * Everything a report page renders: the KPI strip, the verdict that answers
 * "so what?", the sections, and any honesty caveats about the numbers.
 */
final class ReportPayload
{
    /** @var list<Section> */
    public readonly array $sections;

    /** @var list<Caveat> */
    public readonly array $caveats;

    /**
     * @param  list<Metric>  $kpis
     * @param  list<Section|null>  $sections
     * @param  list<Caveat|null>  $caveats
     */
    public function __construct(
        public readonly array $kpis = [],
        array $sections = [],
        public readonly ?Verdict $verdict = null,
        array $caveats = [],
    ) {
        // Reports assemble sections and caveats conditionally, so nulls and
        // gaps are normal on the way in and must not reach the payload.
        $this->sections = array_values(array_filter($sections));
        $this->caveats = array_values(array_filter($caveats));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'kpis' => array_map(static fn (Metric $metric): array => $metric->toArray(), $this->kpis),
            'sections' => array_map(static fn (Section $section): array => $section->toArray(), $this->sections),
            'verdict' => $this->verdict?->toArray(),
            'caveats' => array_map(static fn (Caveat $caveat): array => $caveat->toArray(), $this->caveats),
        ];
    }
}
