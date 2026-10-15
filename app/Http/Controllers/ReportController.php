<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\Reports;
use App\Support\MetricFilter;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private readonly Reports $reports) {}

    public function show(Request $request, string $type = 'sales-summary'): Response
    {
        abort_unless(array_key_exists($type, Reports::TYPES), 404);
        $f = MetricFilter::fromRequest($request);
        $grouping = $this->grouping($request);

        return Inertia::render('Reports/Show', [
            'report' => $this->reports->build($type, $f, $grouping),
            'types' => Reports::TYPES,
            'grouping' => $grouping,
            'filters' => $f->toArray(),
            'products' => Product::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * R2: spreadsheet export with exactly the figures shown on screen.
     */
    public function export(Request $request, string $type): StreamedResponse
    {
        abort_unless(array_key_exists($type, Reports::TYPES), 404);
        $f = MetricFilter::fromRequest($request);
        $report = $this->reports->build($type, $f, $this->grouping($request));
        $range = $f->toArray();

        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($this->reports->toCsvRows($report) as $line) {
                fputcsv($out, $line);
            }
            fclose($out);
        }, "blue-foods-{$type}-{$range['from']}-to-{$range['to']}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function grouping(Request $request): string
    {
        return $request->validate(['grouping' => ['nullable', Rule::in(Reports::GROUPINGS)]])['grouping'] ?? 'month';
    }
}
