<?php

namespace App\Http\Controllers\Admin\Accounting\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** The "Today / This week / This month / … / custom dates" period used by the Dashboard and the Reports */
trait HandlesPeriod
{
    protected function period(Request $request): array
    {
        $labels = [
            'today'      => 'Today',
            'week'       => 'This week',
            'month'      => 'This month',
            'last_month' => 'Last month',
            'year'       => 'This year',
            'all'        => 'All time',
        ];

        $isDate = fn ($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) === 1;
        $now    = now();
        $range  = (string) $request->query('range', 'month');
        $from   = $to = null;

        if ($range === 'custom') {
            $from = $isDate($request->query('from')) ? (string) $request->query('from') : null;
            $to   = $isDate($request->query('to'))   ? (string) $request->query('to')   : null;
            if ($from === null && $to === null) {
                $range = 'month';
            } elseif ($from !== null && $to !== null && $from > $to) {
                [$from, $to] = [$to, $from];
            }
        }

        if ($range !== 'custom') {
            if (!isset($labels[$range])) {
                $range = 'month';
            }
            $today = $now->toDateString();

            switch ($range) {
                case 'today':
                    $from = $to = $today;
                    break;
                case 'week': // the week starts on Saturday
                    $from = $now->copy()->startOfWeek(Carbon::SATURDAY)->toDateString();
                    $to   = $today;
                    break;
                case 'month':
                    $from = $now->copy()->startOfMonth()->toDateString();
                    $to   = $today;
                    break;
                case 'last_month':
                    $last = $now->copy()->subMonthNoOverflow();
                    $from = $last->copy()->startOfMonth()->toDateString();
                    $to   = $last->copy()->endOfMonth()->toDateString();
                    break;
                case 'year':
                    $from = $now->copy()->startOfYear()->toDateString();
                    $to   = $today;
                    break;
                default: // all
                    $from = $to = null;
            }
        }

        $fmt = fn ($d) => Carbon::parse($d)->format('d M Y');
        if ($from && $to) {
            $span = $from === $to ? $fmt($from) : $fmt($from) . ' – ' . $fmt($to);
        } elseif ($from) {
            $span = 'from ' . $fmt($from);
        } elseif ($to) {
            $span = 'until ' . $fmt($to);
        } else {
            $span = 'all dates';
        }

        return [
            'range'  => $range,
            'labels' => $labels,
            'from'   => $from,
            'to'     => $to,
            'title'  => $range === 'custom' ? 'Custom period' : $labels[$range],
            'span'   => $span,
        ];
    }
}
