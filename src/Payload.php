<?php

namespace Fingerscan;

class Payload implements \Stringable, \Countable
{
    public const TYPE_RESPONSE = 6;
    public const TYPE_REQUEST = 9;

    private const FORMAT = 'S*';

    private ?array $chars = null;

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

    public function body(bool $raw = false, bool $imploded = false): array|string
    {
        $chars = $this->slice($this->index);

        $commands = $raw ? $chars : array_map('dechex', $chars);

        return $imploded ? \implode(' ', $commands) : $commands;
    }

    public function data(bool $imploded = false): array|string
    {
        $spaces = 0;
        $reduced = \array_reduce($this->body(true), function (array $reduced, int $item) use (&$spaces) {
            if ($item !== 0) {
                $spaces = 0;
                $reduced[] = $item;

                return $reduced;
            }

            if ($spaces === 0) {
                $reduced[] = $item;
            }

            $spaces++;
            return $reduced;
        }, []);

        $results = \array_map(function (int $char) {
            if ($char === 0) {
                return ' ';
            }

            $encodings = ['auto'];
            $char = pack(self::FORMAT, $char);
            // $char = \mb_detect_encoding($char, $encodings, true);
            $char = \mb_convert_encoding($char, 'utf-8', $encodings);

            return $char;
        }, $reduced);

        return $imploded ? implode('', $results) : $results;
    }

    public function sequence(): int
    {
        return $this->chars(match ($this->index) {
            self::TYPE_RESPONSE => 4,
            self::TYPE_REQUEST => 7,
        });
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
