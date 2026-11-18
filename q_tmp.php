<?php
// Temporary: count the queries one controller action runs. Deleted after use.
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$GLOBALS['qn'] = 0; $GLOBALS['qt'] = 0; $GLOBALS['qs'] = [];
\Illuminate\Support\Facades\DB::listen(function ($q) {
    $GLOBALS['qn']++;
    $GLOBALS['qt'] += $q->time;
    $k = preg_replace('/\s+/', ' ', $q->sql);
    $GLOBALS['qs'][$k] = ($GLOBALS['qs'][$k] ?? 0) + 1;
});
[$class, $method] = explode('@', $argv[1]);
$t0 = microtime(true);
$res = app()->call([app($class), $method]);
$body = method_exists($res, 'getContent') ? $res->getContent() : json_encode($res);
echo "queries=", $GLOBALS['qn'], " dbms=", round($GLOBALS['qt']), " total=", round((microtime(true) - $t0) * 1000), "ms bytes=", strlen($body), "\n";
arsort($GLOBALS['qs']);
foreach (array_slice($GLOBALS['qs'], 0, 12, true) as $k => $v) echo "$v x ", substr($k, 0, 170), "\n";
