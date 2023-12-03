<?php

use Fingerscan\Device;

set_time_limit(0);

$base_path = dirname(__DIR__);
$cmd_name = $argv[1] ?? null;

require($base_path.'/vendor/autoload.php');

$sample_dir = $base_path.'/samples';
$server_ip = '10.10.3.19';
$device_ip = '10.10.3.21';

$device = new Device($device_ip);

function decode(array $data): string {
    $pack = pack('S*', ...$data);

    return mb_convert_encoding($pack, 'utf-8');
}

function transform(?string $str, callable $cb = null): ?string {
    if (! $str) {
        return null;
    }

    $hex = str_replace(':', '', $str);
    $bin = hex2bin($hex);

    return !is_null($cb) ? $cb($bin, $hex) : $bin;
}

function recv(?string $bin) {
    $chrs = array_values(unpack('S*', $bin));
    return count($chrs) > 5 ? array_slice($chrs, 6) : array_slice($chrs, 1, 3);
}

$trans = static function (array $data) use ($device) {
    $data['req'] = $data['req'] ?? null;
    $data['res'] = $data['res'] ?? null;
    $res = null;

    echo "raw: \033[32m{$data['req']} \033[31m➜ \033[32m{$data['res']}\033[0m".PHP_EOL;

    echo transform($data['req'], function (string $bin) use ($device, &$res): string {
        $chrs = array_values(unpack('S*', $bin));
        $data = array_slice($chrs, 1, 3);

        $res = $device->send($bin);

        return "req: \033[31m{$chrs[7]}\033[0m \033[33m".decode($data)."\033[0m";
    }).PHP_EOL;

    echo transform($data['res'], function (string $bin) use ($res): string {
        $expect = recv($bin);
        $actual = recv($res);

        var_dump(implode(':', $expect), implode(':', $actual));

        return "res: \033[33m".decode($expect)." \033[31m: \033[33m".decode($actual)."";
    }).PHP_EOL;

    echo '----'.PHP_EOL;

    return $data;
};

$normalized = [];

foreach (scandir($sample_dir.'/raw') as $file) {
    if (! str_ends_with($file, '.json')) {
        continue;
    }

    $result = [];
    $content = file_get_contents($sample_dir.'/raw/'.$file);

    foreach (json_decode($content, true) as $raw) {
        $obj = $raw['_source']['layers'];
        $tcp = $obj['tcp'];

        if (! isset($tcp['tcp.payload'])) {
            continue;
        }

        // is request
        if ($obj['ip']['ip.src'] === $server_ip) {
            $result[$tcp['tcp.ack']]['req'] = $tcp['tcp.payload'];
        }

        // is response
        if ($obj['ip']['ip.src'] === $device_ip) {
            $result[$tcp['tcp.seq']]['res'] = $tcp['tcp.payload'];
        }
    }

    [$no, $name] = explode('-', $file);
    $name = ltrim($name, ' ');
    $result = array_values($result);

    if ($cmd_name === $name) {
        echo PHP_EOL.$name.PHP_EOL.'----'.PHP_EOL;
        array_map($trans, $result);
    }

    $normalized[$name] = $result;
}

if ($cmd_name === null) {
    foreach ($normalized as $name => $results) {
        echo PHP_EOL.$name.PHP_EOL.'----'.PHP_EOL;

        foreach ($results as $i => $data) {
            $trans($data);

            if ($i === 7) {
                break;
            }
        }
    }
}

file_put_contents(
    $sample_dir.'/outputs/normalized.json',
    json_encode($normalized, JSON_PRETTY_PRINT)
);
