<?php

use Fingerscan\Device;
use Fingerscan\Payload;

set_time_limit(0);

$base_path = dirname(__DIR__);

require($base_path.'/vendor/autoload.php');

class Command
{
    private Traversable $data;

    public function __construct(
        private Device $device
    ) {
        $this->data = new ArrayIterator([]);
    }

    public function run(string $path, $cmd = null): static
    {
        $this->normalize($path, function (array $result, string $name) use ($cmd) {
            if ($cmd !== null) {
                return;
            }

            echo PHP_EOL.$name.PHP_EOL.'----'.PHP_EOL;

            $this->dump($result);
        });

        if ($cmd) {
            if (! isset($this->data[$cmd])) {
                echo PHP_EOL."\033[31mCommand '$cmd' not found\033[0m".PHP_EOL;
                return $this;
            }

            echo PHP_EOL.$cmd.PHP_EOL.'----'.PHP_EOL;

            $this->dump($this->data[$cmd], function (Payload $req) {
                $test = $this->device->send($req);

                echo PHP_EOL."chk[\033[34m{$test->chars(4)}\033[0m] \033[34m{$test->pack(1, 3)}\033[0m".PHP_EOL;

                if ($payload = $test->encode()) {
                    echo "\033[34m{$payload}\033[0m".PHP_EOL;
                }
            });
        }

        return $this;
    }

    private function dump(array $data, callable $cb = null): void
    {
        foreach ($data as $item) {
            $req = Payload::fromSample($item['req'], Payload::TYPE_REQUEST);
            $res = Payload::fromSample($item['res'], Payload::TYPE_RESPONSE);

            echo "\033[0mraw: \033[033m{$item['req']} \033[31m➜ \033[32m{$item['res']}".PHP_EOL;

            echo "\033[0mpre: \033[033m{$req->chars(0)} \033[0m({$req->pack(0, 1)}) ";
            echo "\033[31m➜ \033[32m{$res->chars(0)} \033[0m({$res->pack(0, 1)}) ";
            echo "\033[0m[\033[33m{$req->chars(7)}\033[31m:\033[32m{$res->chars(4)}\033[0m]".PHP_EOL;

            echo "\033[0mcmd: \033[33m{$req->segment(1, 6)} \033[0m({$req->pack(1, 6)}) ";
            echo "\033[31m➜ \033[32m{$res->segment(1, 3)} \033[0m({$res->pack(1, 3)})".PHP_EOL;

            echo $this->data($req, $res);

            if ($cb !== null) {
                $cb($req);
            }

            echo PHP_EOL;
        }
    }

    private function data(Payload $req, Payload $res): string
    {
        $reqData = $req->data();
        $resData = $res->data();

        if (! $reqData && ! $resData) {
            return '';
        }

        $return = 'data: ';

        if ($reqData) {
            $return .= "\033[33m{$reqData} \033[0m({$req->encode()})";
        }

        if ($resData) {
            $return .= "\033[31m➜ \033[32m{$resData} \033[0m({$res->encode()})";
        }

        return $return.PHP_EOL;
    }

    private function normalize(string $path, callable $cb): void
    {
        foreach (scandir($path.'/raw') as $file) {
            if (! str_ends_with($file, '.json')) {
                continue;
            }

            [$no, $name] = explode(' - ', $file);
            $name = str_replace('.json', '', ltrim($name, ' '));

            // Read normalized data if exists.
            $result = file_exists("$path/outputs/$file")
                ? $this->readJson("$path/outputs/$file")
                : $this->parseRaw("$path/raw/$file");

            $this->writeJson("$path/outputs/$no - $name", $result);

            $this->data[$name] = $result;

            $cb($result, $name);
        }

        $this->writeJson($path.'/outputs/normalized');
    }

    private function parseRaw(string $path): array
    {
        $result = [];
        $json = $this->readJson($path);

        // Retrieve first packet from the json file.
        $conn = $json[0]['_source']['layers']['ip'];

        foreach ($json as $item) {
            $obj = $item['_source']['layers'];
            $tcp = $obj['tcp'];

            // Skip if there's no payload.
            if (! isset($tcp['tcp.payload'])) {
                continue;
            }

            // Retrieve request payload.
            if ($obj['ip']['ip.src'] === $conn['ip.src']) {
                $result[$tcp['tcp.ack']]['req'] = $tcp['tcp.payload'];
            }

            // Retrieve response payload.
            if ($obj['ip']['ip.src'] === $conn['ip.dst']) {
                $result[$tcp['tcp.seq']]['res'] = $tcp['tcp.payload'];
            }
        }

        return array_values($result);
    }

    private function readJson(string $path): array
    {
        $content = file_get_contents($path);

        return json_decode($content, true);
    }

    private function writeJson(string $path, array $content = []): void
    {
        if (file_exists($filepath = "$path.json")) {
            return;
        }

        $content = empty($content) ? $this->data : $content;

        file_put_contents($filepath, json_encode($content, JSON_PRETTY_PRINT));
    }
}

try {
    $device = new Device('10.10.3.18');
    $command = new Command($device);

    $command->run($base_path.'/samples', ...array_slice($argv, 1));

    exit(0);
} catch (Throwable $e) {
    echo $e->getMessage().PHP_EOL;
    exit(1);
}
