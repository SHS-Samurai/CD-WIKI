<?php

if (! extension_loaded('curl')) {
    fwrite(STDERR, "Die PHP-cURL-Erweiterung fehlt.\n");
    exit(2);
}

$url = $argv[1] ?? '';
$requests = min(10000, max(1, (int) ($argv[2] ?? 250)));
$concurrency = min(100, max(1, (int) ($argv[3] ?? 10)));
if (! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true) || ! parse_url($url, PHP_URL_HOST)) {
    fwrite(STDERR, "Aufruf: php scripts/load-test.php https://wiki.example/ [Anfragen] [Parallelität]\n");
    exit(2);
}

$multi = curl_multi_init();
$active = [];
$started = 0;
$finished = 0;
$failures = 0;
$times = [];

$start = static function () use (&$started, $requests, $url, $multi, &$active): void {
    if ($started >= $requests) {
        return;
    }
    $handle = curl_init($url);
    curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_FOLLOWLOCATION => false, CURLOPT_USERAGENT => 'CD-Wiki-Load-Test/1.0']);
    curl_multi_add_handle($multi, $handle);
    $active[(int) $handle] = $handle;
    $started++;
};

for ($i = 0; $i < min($requests, $concurrency); $i++) {
    $start();
}
do {
    do {
        $status = curl_multi_exec($multi, $running);
    } while ($status === CURLM_CALL_MULTI_PERFORM);
    while ($info = curl_multi_info_read($multi)) {
        $handle = $info['handle'];
        $code = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $times[] = curl_getinfo($handle, CURLINFO_TOTAL_TIME);
        if ($info['result'] !== CURLE_OK || $code < 200 || $code >= 400) {
            $failures++;
        }
        curl_multi_remove_handle($multi, $handle);
        curl_close($handle);
        unset($active[(int) $handle]);
        $finished++;
        $start();
    }
    if ($running > 0) {
        curl_multi_select($multi, 1.0);
    }
} while ($finished < $requests);
curl_multi_close($multi);

sort($times);
$p95 = $times[(int) floor((count($times) - 1) * 0.95)] ?? 0;
printf("Anfragen: %d, Fehler: %d, Mittel: %.3fs, P95: %.3fs\n", $requests, $failures, array_sum($times) / count($times), $p95);
exit($failures === 0 ? 0 : 1);
