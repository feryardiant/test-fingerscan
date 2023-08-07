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
//socket_set_nonblock($sock);

$seq = 0;
$seqs = [
    '55:aa:01:80:00:00:00:00:01:00:ff:ff:00:00:01:00',
];

do {
    $msg = $seqs[$seq];
    echo 'Sending'.PHP_EOL;
    $sent = socket_write($sock, $msg, $len = strlen($msg));

    if ($sent === $len) {
        echo 'Message sent'.PHP_EOL;
    }

    echo 'Reading'.PHP_EOL;
    while ($res = socket_read($sock, 1024)) {
        var_dump($res);
    }

    if (false === $res) {
        $code = socket_last_error($sock);
        socket_clear_error($sock);

        var_dump(socket_strerror($code));
    }

    $seq++;

    if (! isset($seqs[$seq])) {
        echo 'No more data to send';
        break;
    }
} while (true);

socket_clear_error($sock);
socket_close($sock);
exit(0);
