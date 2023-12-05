<?php

use Fingerscan\Device;

set_time_limit(0);

$base_path = dirname(__DIR__);

require($base_path.'/vendor/autoload.php');

$server_ip = '10.10.0.10';
$server_port = 8080;

$device_ip = '10.10.2.171';
$device_port = 5005;

$device = new Device($device_ip, $device_port);

$json_str = file_get_contents($base_path.'/samples/fingerscan.json');
$json_obj = json_decode($json_str, false);

$idx = (int) $argv[1] ?? 0;
$seq = 0;
$seqs = $json_obj->seq[$idx];

$output = [];
$errors = [];

function req(int ...$chrs) {
    $parsed = [
        'prefix' => $chrs[0],
        'sequence' => $chrs[7],
        'payload' => array_slice($chrs, 3, 3),
        'unknown-1' => $chrs[1],
        'unknown-2' => $chrs[2],
        'suffix' => array_slice($chrs, 8),
    ];
    echo 'Seq: '.$parsed['sequence'].PHP_EOL;
    var_dump($parsed['payload']);

    return $parsed;
}

function res(array $req, int ...$chrs) {
    $parsed = [
        'prefix' => $chrs[0],
        'sequence' => $chrs[4],
        'payload' => array_slice($chrs, 6),
        'unknown-1' => $chrs[1],
        'unknown-2' => array_slice($chrs, 2, 2),
    ];
    echo 'Seq: '.$parsed['sequence'].PHP_EOL;
    var_dump(p($parsed['payload']));

    return $parsed;
}

function p(array $chrs, bool $convert = false) {
    if (empty($chrs)) {
        return null;
    }

    $pack = pack('S*', ...$chrs);

    if ($convert) {
        return mb_convert_encoding($pack, 'utf-8', 'utf-16le');
    }

    return $pack;
}

function decode(string $bin, array $req = null) {
    $chrs = array_values(unpack('S*', $bin));

    return [
        'hex' => array_map('dechex', $chrs),
        'packed' => p($chrs, true),
        ...(null === $req ? req(...$chrs) : res($req, ...$chrs)),
    ];
}

do {
    $decoded = [];
    $sample = $seqs[$seq];
    $req_hex = str_replace(':', '', $sample->req);
    $res_hex = str_replace(':', '', $sample->res);

    $msg = hex2bin($req_hex);

    echo sprintf('Sending: %s', $req_hex).PHP_EOL;
    $decoded['req'] = decode($msg);

    $recv = $device->send($msg);
    $recv_hex = bin2hex($recv);
    $assert = $recv_hex === $res_hex ? 'yes' : $res_hex;

    echo sprintf('Received: %s, expected: %s', $recv_hex, $assert).PHP_EOL;
    $decoded['res'] = decode($recv, $decoded['req']);

    $output[] = $decoded;
    $seq++;

    echo PHP_EOL;
} while (isset($seqs[$seq]));

file_put_contents(
    __DIR__."/samples/outputs/{$idx}.json",
    json_encode($output, JSON_PRETTY_PRINT | JSON_HEX_QUOT)
);

exit(0);
