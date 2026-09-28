<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(protected AnalyticsService $analytics) {}

    public function index(Request $request)
    {
        [$range, $from, $to] = $this->analytics->resolveRange(
            $request->query('range'),
            $request->query('from'),
            $request->query('to')
        );

        $granularity = $request->query('granularity', 'daily');
        $series = $this->analytics->salesSeries($from, $to, $granularity);
        $business = $this->analytics->businessMetrics($from, $to);

        return view('admin.analytics.index', compact(
            'range', 'from', 'to', 'granularity', 'series', 'business'
        ));
    }

    /**
     * JSON feed for the chart — lets the range/granularity selectors
     * update the chart without a full page reload.
     */
    public function salesData(Request $request): JsonResponse
    {
        [$range, $from, $to] = $this->analytics->resolveRange(
            $request->query('range'),
            $request->query('from'),
            $request->query('to')
        );

        return response()->json(
            $this->analytics->salesSeries($from, $to, $request->query('granularity', 'daily'))
        );
    }
}
