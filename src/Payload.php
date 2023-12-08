<?php

namespace Fingerscan;

use IntlChar;

class Payload implements \Stringable, \Countable
{
    public const TYPE_RESPONSE = 6;
    public const TYPE_REQUEST = 9;

    private const FORMAT = 'S*';

    private ?array $chars = null;
    private array $body = [];

    public function __construct(
        readonly public string $bin,
        protected int $index,
    ) {
        // .
    }

    public static function asResponse(string $bin): static
    {
        return new static($bin, self::TYPE_RESPONSE);
    }

    public static function fromSample(string $sample, int $index): self
    {
        $hex = str_replace(':', '', $sample);

        return new static(hex2bin($hex), $index);
    }

    public function chars(int $index = null): array|int
    {
        if (! $this->bin) {
            return [];
        }

        if (! $this->chars) {
            $this->chars = array_values(unpack(self::FORMAT, $this->bin));
        }

        return $index === null ? $this->chars : $this->chars[$index];
    }

    public function slice(int $start = null, int $length = null): array
    {
        if ($start === null) {
            return $this->chars();
        }

        return array_slice($this->chars(), $start, $length);
    }

    public function prefix(bool $raw = false): string|int
    {
        $pre = $this->chars(0);

        return $raw ? $pre : \dechex($pre);
    }

    public function command(bool $raw = false, bool $imploded = false): array|string
    {
        $chars = $this->slice(1, match ($this->index) {
            self::TYPE_RESPONSE => 3,
            self::TYPE_REQUEST => 6,
        });

        $commands = $raw ? $chars : array_map('dechex', $chars);

        return $imploded ? \implode(' ', $commands) : $commands;
    }

    public function body(bool $raw = false, bool $imploded = false, \Closure $callback = null): array|string
    {
        if (empty($this->body)) {
            $spaces = 0;
            foreach ($this->slice($this->index) as $char) {
                if ($this->isValidCodePoint($char)) {
                    $spaces = 0;
                    $this->body[] = $char;

                    continue;
                }

                if ($spaces === 0) {
                    $this->body[] = 0;
                }

                $spaces++;
            }
        }

        $result = $raw ? $this->body : array_map($callback ?: 'dechex', $this->body);

        return $imploded ? \implode(' ', $result) : $result;
    }

    public function data(bool $imploded = false): array|string
    {
        $result = $this->body(callback: function (int $char) {
            if ($char === 0) {
                return ' ';
            }

            // $encoding = \mb_detect_encoding($char, ['utf-16', 'ascii'], true);
            $char = pack(self::FORMAT, $char);

            return \mb_convert_encoding($char, 'UTF-8');
        });

        return $imploded ? implode('', $result) : $result;
    }

    public function sequence(): int
    {
        return $this->chars(match ($this->index) {
            self::TYPE_RESPONSE => 4,
            self::TYPE_REQUEST => 7,
        });
    }

    protected function isValidCodePoint(int $char) {
        return !\in_array($char, [0, 0xffff], true);
    }

    public function count(): int
    {
        return \mb_strlen($this->bin);
    }

    public function __toString(): string
    {
        return $this->bin;
    }
}
