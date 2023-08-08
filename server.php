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

function decode(string $bin, bool $res = false) {
    $chrs = array_values(unpack('S*', $bin));

    $toUtf8 = static function (int ...$chrs) use ($res) {
        $packed = pack('S*', ...$chrs);
        $cmp = $res ? 0x55aa : 0xaa55;

        if (count($chrs) > 1) {
            var_dump($cmp, $chrs[0], $chrs[0] === $cmp);
        }

        return iconv('utf-16le', 'utf-8', $packed);
    };

    return [
        'packed' => $toUtf8(...$chrs),
        'int' => $chrs,
        'utf-8' => array_map($toUtf8, $chrs),
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
    $sent = socket_write($sock, $msg, strlen($msg));

    while ($recv = socket_read($sock, 1024)) {
        $recv_hex = bin2hex($recv);

        $assert = $recv_hex === $res_hex ? 'yes' : $res_hex;

        echo sprintf('Received: %s, expected: %s', $recv_hex, $assert).PHP_EOL;
        $decoded['res'] = decode($recv, true);
        break;
    }

    if (false === $recv) {
        $code = socket_last_error($sock);
        socket_clear_error($sock);

        $errors[] = socket_strerror($code);
    }

    $output[] = $decoded;
    $seq++;

    echo PHP_EOL;
} while (isset($seqs[$seq]));

socket_clear_error($sock);
socket_close($sock);

if (! empty($errors)) {
    echo 'Errors:'.PHP_EOL;
    echo implode(PHP_EOL, $errors).PHP_EOL;

    exit(1);
}

file_put_contents(__DIR__."/samples/outputs/{$idx}.json", json_encode($output, JSON_PRETTY_PRINT));

exit(0);
