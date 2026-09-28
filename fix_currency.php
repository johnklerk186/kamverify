<?php
// One-off: convert $-USD display patterns to XAF in blade views.
// USD stays for provider-cost values (HeroSMS bills in USD).

$dir = __DIR__ . '/resources/views';
$usdVars = ['purchase_price', 'liveBalance', 'live_status', 'ps->cost', 'provider_cost_usd'];

$iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
$changed = [];

foreach ($iter as $file) {
    if ($file->isDir() || !str_ends_with($file->getFilename(), '.blade.php')) continue;
    $path = $file->getPathname();
    $c = file_get_contents($path);
    $orig = $c;

    // ${{ number_format(EXPR, 2) }} — EXPR has no nested {{ }} but may contain ()
    $c = preg_replace_callback(
        '/\$\{\{\s*number_format\((.+?),\s*2\)\s*\}\}/',
        function ($m) use ($usdVars) {
            foreach ($usdVars as $v) {
                if (str_contains($m[1], $v)) {
                    return '{{ number_format(' . $m[1] . ', 2) }} USD';
                }
            }
            return '{{ xaf(' . $m[1] . ') }}';
        },
        $c
    );

    // '$'.number_format(EXPR, 2)  and  '$' . number_format(EXPR, 2)
    $c = preg_replace_callback(
        "/'\\$'\\s*\\.\\s*number_format\\((.+?),\\s*2\\)/",
        function ($m) use ($usdVars) {
            foreach ($usdVars as $v) {
                if (str_contains($m[1], $v)) {
                    return "number_format(" . $m[1] . ", 2) . ' USD'";
                }
            }
            return 'xaf(' . $m[1] . ')';
        },
        $c
    );

    // -${{ ... }} → −{{ ... }} (keep the minus, drop the $)
    $c = preg_replace('/-\$\{\{/', '−{{', $c);
    // +${{ → +{{
    $c = preg_replace('/\+\$\{\{/', '+{{', $c);

    // Standalone $ immediately before x-text/number in JS contexts is handled manually.

    if ($c !== $orig) {
        file_put_contents($path, $c);
        $changed[] = $path;
    }
}

echo "Changed " . count($changed) . " files:\n" . implode("\n", $changed) . "\n";
