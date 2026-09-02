<x-mail::message>
# {{ $reportLabel }}

**{{ $tenantName }}** · {{ $periodLabel }}

@if ($headline)
## {{ $headline }}

@if ($detail){{ $detail }}@endif

@if ($action)
**What to do:** {{ $action }}
@endif
@endif

@if (count($kpis) > 0)
<x-mail::table>
| Metric | Value | vs previous |
| :----- | ----: | ----------: |
@foreach ($kpis as $kpi)
| {{ $kpi['label'] }} | {{ $kpi['value'] }} | {{ $kpi['delta'] ?? '—' }} |
@endforeach
</x-mail::table>
@endif

The full report is attached.

@foreach ($caveats as $caveat)
> {{ $caveat }}

@endforeach

<x-mail::button :url="$url">Open the live report</x-mail::button>

Numbers are as of the time this email was generated.
</x-mail::message>
