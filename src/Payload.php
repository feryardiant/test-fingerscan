<?php

namespace Fingerscan;

class Payload implements \Stringable
{
    public const TYPE_RESPONSE = 6;
    public const TYPE_REQUEST = 8;

    private const FORMAT = 'S*';

    public static ?string $raw = null;
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
        self::$raw = $sample;
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

    public function pack(int $start = null, int $length = null): string
    {
        $result = [];
        foreach ($this->slice($start, $length) as $char) {
            $char = \dechex($char);
            // $char = pack(self::FORMAT, $char);
            // $char = \mb_convert_encoding($char, 'utf-8');
            // $char = implode(':', array_map(function($byte) {
            //     // return sprintf("%08b", ord($byte));
            //     return mb_ord($byte);
            // }, str_split($char)));

            $result[] = $char;
        }

        return \implode(' ', $result);

        // $packed = pack(self::FORMAT, ...$chars);

        // return \mb_convert_encoding($packed, 'utf-8');
        // return \bin2hex($packed);
    }

    public function length(): int
    {
        return \mb_strlen($this->bin);
    }

    public function segment(int $start, int $length = null): string
    {
        return implode(':', $this->slice($start, $length));
    }

    public function slice(int $start = null, int $length = null): array
    {
        if ($start === null) {
            return $this->chars();
        }

        return array_slice($this->chars(), $start, $length);
    }

    public function encode()
    {
        return $this->pack($this->index, null);
    }

    public function data()
    {
        return $this->segment($this->index);
    }

    public function __toString(): string
    {
        return $this->bin;
    }
}
