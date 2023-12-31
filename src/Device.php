<?php

namespace Fingerscan;

class Device
{
    private \Socket $sock;

    public function __construct(
        public readonly string $ip,
        public readonly int $port = 5005,
    ) {
        $this->sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        if (false === socket_connect($this->sock, $ip, $port)) {
            $this->throwError();
        }
    }

    public function send(Payload $cmd): Payload
    {
        $sent = socket_write($this->sock, $cmd, $cmd->count());

        if (false === $sent) {
            $this->throwError();
        }

        $recv = socket_read($this->sock, 2048);

        if (false === $recv) {
            $this->throwError();
        }

        return Payload::asResponse($recv);
    }

    protected function throwError(): void
    {
        $code = socket_last_error($this->sock);

        throw new \RuntimeException(socket_strerror($code), $code);
    }

    public function __destruct()
    {
        socket_clear_error($this->sock);
        socket_close($this->sock);
    }
}
