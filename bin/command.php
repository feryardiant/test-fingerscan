<?php

use Fingerscan\Device;
use Fingerscan\Payload;

set_time_limit(0);

$base_path = dirname(__DIR__);
$cmd_name = $argv[1] ?? null;

require($base_path.'/vendor/autoload.php');

class Validator
{
    private const SERVER_IP = '10.10.3.19';

    private Traversable $data;

    public function __construct(
        private Device $device
    ) {
        $this->data = new ArrayIterator([]);
    }

    public function handle(string $path): static
    {
        foreach (scandir("$path/raw") as $file) {
            if (! str_ends_with($file, '.json')) {
                continue;
            }

            [$no, $name] = explode(' - ', $file);
            $name = str_replace('.json', '', ltrim($name, ' '));

            echo PHP_EOL.$name.PHP_EOL.'----'.PHP_EOL;

            $result = $this->content("$path/raw/$file");

            foreach ($result as $item) {
                $req = Payload::fromSample($item['req'], 8);
                $res = Payload::fromSample($item['res'], 6);

                // $test = $this->device->send($req);

                // echo 'test: '.$test->encode().PHP_EOL;

                echo "\033[0mraw: \033[033m{$req} \033[31m➜ \033[32m{$res}".PHP_EOL;

                echo "\033[0mpre: \033[033m{$req->chars(0)} \033[0m({$req->pack(0)}) ";
                echo "\033[31m➜ \033[32m{$res->chars(0)} \033[0m({$res->pack(0)}) ";
                echo "\033[0m[\033[33m{$req->chars(7)}\033[31m:\033[32m{$res->chars(4)}\033[0m]".PHP_EOL;

                echo "\033[0mcmd: \033[33m{$req->slice(1, 6)} \033[0m({$req->pack(1, 6)}) ";
                echo "\033[31m➜ \033[32m{$res->slice(1, 3)} \033[0m({$res->pack(1, 3)})".PHP_EOL;

                echo $this->data($req, $res);

                echo PHP_EOL;
            }

            $this->data[$name] = $result;

            $this->writeJson("$path/outputs/$no $name", $result);
        }

        $this->writeJson($path.'/outputs/normalized');

        return $this;
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

    private function content(string $path): array
    {
        $content = file_get_contents($path);
        $result = [];

        foreach (json_decode($content, true) as $item) {
            $obj = $item['_source']['layers'];
            $tcp = $obj['tcp'];

            if (! isset($tcp['tcp.payload'])) {
                continue;
            }

            // is request
            if ($obj['ip']['ip.src'] === self::SERVER_IP) {
                $result[$tcp['tcp.ack']]['req'] = $tcp['tcp.payload'];
            }

            // is response
            if ($obj['ip']['ip.src'] === $this->device->ip) {
                $result[$tcp['tcp.seq']]['res'] = $tcp['tcp.payload'];
            }
        }

        return array_values($result);
    }

    private function writeJson(string $path, array $content = []): void
    {
        $content = empty($content) ? $this->data : $content;

        file_put_contents("$path.json", json_encode($content, JSON_PRETTY_PRINT));
    }
}

try {
    $device = new Device('10.10.3.15');
    $validator = new Validator($device);

    $validator->handle($base_path.'/samples');

    exit(0);
} catch (Throwable $e) {
    echo $e->getMessage().PHP_EOL;
    exit(1);
}
