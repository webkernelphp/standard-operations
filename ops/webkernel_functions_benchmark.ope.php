<?php declare(strict_types=1);

/**
 * Webkernel Functions Benchmark
 *
 * @package webkernel/stdops
 *
 * Usage:
 *
 *   require 'webkernel_benchmark.php';
 *
 *   webkernel_functions_benchmark([
 *
 *       'Group A — finding project root' => [
 *           'base_path()'    => fn() => base_path(),
 *           'project_root()' => fn() => project_root(),
 *       ],
 *
 *       'Group B — vendor dir' => [
 *           'WK::vendorDir()' => fn() => WebkernelComposer::vendorDir(),
 *           'global $var'     => fn() use ($vendorDir) => $vendorDir,
 *       ],
 *
 *   ]);
 */

function webkernel_functions_benchmark(
    array $groups,
    int   $iterations = 10,
    bool  $warmup     = true,
): void {
    // -------------------------------------------------------------------------
    // Flatten to know every callable up-front
    // -------------------------------------------------------------------------
    $flat = [];
    foreach ($groups as $groupLabel => $fns) {
        // allow a flat (non-grouped) map: ['label' => fn, ...]
        if (is_callable($fns)) {
            $flat[$groupLabel] = ['' => [$groupLabel => $fns]];
        } else {
            foreach ($fns as $label => $fn) {
                $flat[$groupLabel][$label] = $fn;
            }
        }
    }

    // -------------------------------------------------------------------------
    // Warm-up — one call per callable, fills every static/cache
    // -------------------------------------------------------------------------
    if ($warmup) {
        foreach ($flat as $fns) {
            foreach ($fns as $fn) {
                try { $fn(); } catch (\Throwable) {}
            }
        }
    }

    // -------------------------------------------------------------------------
    // Run
    // -------------------------------------------------------------------------
    $groupResults = [];

    foreach ($flat as $groupLabel => $fns) {
        $entries = [];
        foreach ($fns as $label => $fn) {
            $times  = [];
            $output = null;
            $error  = null;

            for ($i = 0; $i < $iterations; $i++) {
                $t0 = hrtime(true);
                try {
                    $output = $fn();
                } catch (\Throwable $e) {
                    $error = $e->getMessage();
                }
                $times[] = hrtime(true) - $t0;
            }

            $avg = array_sum($times) / $iterations;
            $min = min($times);
            $max = max($times);

            $entries[$label] = [
                'avg_ns' => $avg,
                'min_ns' => $min,
                'max_ns' => $max,
                'result' => $error !== null ? "[ERROR] {$error}" : _wkb_format_result($output),
            ];
        }

        // sort by avg within group
        uasort($entries, fn($a, $b) => $a['avg_ns'] <=> $b['avg_ns']);
        $groupResults[$groupLabel] = $entries;
    }

    // -------------------------------------------------------------------------
    // Render
    // -------------------------------------------------------------------------
    $COL_RANK   = 4;
    $COL_LABEL  = 36;
    $COL_AVG_NS = 14;
    $COL_AVG_US = 12;
    $COL_MIN    = 12;
    $COL_MAX    = 12;
    $COL_RATIO  = 8;
    $WIDTH = $COL_RANK + 2 + $COL_LABEL + 2 + $COL_AVG_NS + 2 + $COL_AVG_US + 2 + $COL_MIN + 2 + $COL_MAX + 2 + $COL_RATIO + 2 + 30;

    $SEP  = str_repeat('=', $WIDTH);
    $LINE = str_repeat('-', $WIDTH);
    $THIN = str_repeat('·', $WIDTH);

    echo PHP_EOL;
    echo $SEP . PHP_EOL;
    printf("  Webkernel Benchmark  |  iterations: %s  |  warmup: %s\n",
        number_format($iterations),
        $warmup ? 'yes' : 'no'
    );
    echo $SEP . PHP_EOL;

    foreach ($groupResults as $groupLabel => $entries) {
        // group header
        echo PHP_EOL;
        printf("  ► %s\n", $groupLabel);
        echo $LINE . PHP_EOL;

        printf(
            "  %-{$COL_RANK}s  %-{$COL_LABEL}s  %{$COL_AVG_NS}s  %{$COL_AVG_US}s  %{$COL_MIN}s  %{$COL_MAX}s  %{$COL_RATIO}s  %s\n",
            '#', 'Method', 'avg (ns)', 'avg (µs)', 'min (ns)', 'max (ns)', 'ratio', 'result'
        );
        echo $LINE . PHP_EOL;

        $baseline = reset($entries)['avg_ns'];
        $rank     = 1;

        foreach ($entries as $label => $data) {
            $ratio = $baseline > 0 ? $data['avg_ns'] / $baseline : 1.0;
            $flag  = $rank === 1 ? ' ✓' : ($ratio >= 5.0 ? ' ✗' : '');

            printf(
                "  %-{$COL_RANK}d  %-{$COL_LABEL}s  %{$COL_AVG_NS}.2f  %{$COL_AVG_US}.4f  %{$COL_MIN}.2f  %{$COL_MAX}.2f  x%-{$COL_RATIO}.2f  %s%s\n",
                $rank++,
                $label,
                $data['avg_ns'],
                $data['avg_ns'] / 1000,
                $data['min_ns'],
                $data['max_ns'],
                $ratio,
                $data['result'],
                $flag
            );
        }

        echo $THIN . PHP_EOL;

        // inline ranking summary
        $rank = 1;
        foreach ($entries as $label => $data) {
            $ratio = $baseline > 0 ? $data['avg_ns'] / $baseline : 1.0;
            printf("    %d. %-{$COL_LABEL}s  x%.2f\n", $rank++, $label, $ratio);
        }
    }

    echo PHP_EOL . $SEP . PHP_EOL . PHP_EOL;
}

// ─────────────────────────────────────────────────────────────────────────────
// Internal helper — turn any return value into a short display string
// ─────────────────────────────────────────────────────────────────────────────
function _wkb_format_result(mixed $value): string
{
    if ($value === null)          return 'null';
    if (is_bool($value))         return $value ? 'true' : 'false';
    if (is_string($value))       return strlen($value) > 60 ? substr($value, 0, 57) . '...' : $value;
    if (is_int($value) || is_float($value)) return (string) $value;
    if (is_array($value))        return 'array(' . count($value) . ')';
    if (is_object($value))       return get_class($value);
    return gettype($value);
}
