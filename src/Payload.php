<?php

namespace Fingerscan;

class Payload implements \Stringable
{
    private const FORMAT = 'S*';

    public static ?string $raw = null;
    private ?array $chars = null;

    public function __construct(
        readonly public string $bin,
        protected int $index,
    ) {
        // .
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

    public function pack(int $start = null, ?int $length = 1): string
    {
        $chars = $start !== null
            ? array_slice($this->chars(), $start, $length)
            : $this->chars();

        $result = [];
        foreach ($chars as $char) {
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

    public function slice(int $start, int $length = null): string
    {
        return implode(':', array_slice($this->chars(), $start, $length));
    }

    public function encode()
    {
        return $this->pack($this->index, null);
    }

    public function data()
    {
        return $this->slice($this->index);
    }

    public function __toString(): string
    {
        return $this->bin;
    }
}
