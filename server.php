<?php

set_time_limit(0);

require(__DIR__.'/vendor/autoload.php');

$server_ip = '10.10.0.10';
$server_port = 8080;

$device_ip = '10.10.2.171';
$device_port = 5005;

$sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

if (false === socket_connect($sock, $device_ip, $device_port)) {
    $err = socket_strerror(socket_last_error($sock));
    echo sprintf('Failed to connect %s:%d reason: %s', $device_ip, $device_port, $err);

    socket_close($sock);
    exit(1);
}

$json_str = file_get_contents(__DIR__.'/samples/fingerscan.json');
$json_obj = json_decode($json_str, false);

$idx = (int) $argv[1] ?? 0;
$seq = 0;
$seqs = $json_obj->seq[$idx];

$output = [];
$errors = [];

function p(string $bin, string $fmt = null, bool $res = false) {
    $chrs = [];
    $out = [
        'packed' => null,
        'chars' => [],
    ];

    foreach (unpack($fmt, $bin) as $i => $chr) {
        $chrs[] = $chr;
    }

    $out['chars'] = $chrs;

    if (! empty($chrs)) {
        $packed = pack($fmt, ...$chrs);
        $out['packed'] = iconv('utf-16le', 'utf-8', $packed);
        print_r($packed);
    }

    return $out;
}

do {
    $sample = $seqs[$seq];
    $req = str_replace(':', '', $sample->req);
    $res = str_replace(':', '', $sample->res);

    $msg = hex2bin($req);

    echo sprintf('Sending: %s', $req).PHP_EOL;
    $sample->req_unpack = p($msg, 's*');
    $sent = socket_send($sock, $msg, $len = strlen($msg), MSG_EOF);

    while ($recv = socket_read($sock, 1024)) {
        $res_hex = bin2hex($recv);

        $assert = $res_hex === $res ? 'yes' : $res;

        echo sprintf('Received: %s, expected: %s', $res_hex, $assert).PHP_EOL;
        $sample->res_unpack = p($recv, 's*', true);
        break;
    }

    if (false === $recv) {
        $code = socket_last_error($sock);
        socket_clear_error($sock);

        $errors[] = socket_strerror($code);
    }

    $output[] = $sample;
    $seq++;

    if (! isset($seqs[$seq])) {
        echo 'No more data to send'.PHP_EOL;
        break;
    }

    echo PHP_EOL;
} while (true);

socket_clear_error($sock);
socket_close($sock);

if (! empty($errors)) {
    echo 'Errors:'.PHP_EOL;
    echo implode(PHP_EOL, $errors).PHP_EOL;

    exit(1);
}

file_put_contents(__DIR__."/samples/outputs/{$idx}.json", json_encode($output, JSON_PRETTY_PRINT));

exit(0);
