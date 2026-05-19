<?php

namespace App\Support\Qr;

class QrCodeSvg
{
    /**
     * @var array<int, array{modules:int,data:int,ecc:int,align:array<int, int>}>
     */
    private const VERSIONS = [
        3 => ['modules' => 29, 'data' => 55, 'ecc' => 15, 'align' => [6, 22]],
        4 => ['modules' => 33, 'data' => 80, 'ecc' => 20, 'align' => [6, 26]],
        5 => ['modules' => 37, 'data' => 108, 'ecc' => 26, 'align' => [6, 30]],
    ];

    /**
     * @var array<int, int>
     */
    private array $exp = [];

    /**
     * @var array<int, int>
     */
    private array $log = [];

    public function __construct()
    {
        $this->buildGaloisTables();
    }

    public function svg(string $text, int $scale = 4, int $margin = 4): string
    {
        $bytes = array_values(unpack('C*', $text) ?: []);
        $version = $this->versionForByteLength(count($bytes));
        $spec = self::VERSIONS[$version];
        $size = $spec['modules'];
        $matrix = array_fill(0, $size, array_fill(0, $size, false));
        $function = array_fill(0, $size, array_fill(0, $size, false));

        $this->drawFunctionPatterns($matrix, $function, $version);

        $dataCodewords = $this->makeDataCodewords($bytes, $spec['data']);
        $eccCodewords = $this->makeErrorCorrection($dataCodewords, $spec['ecc']);
        $this->drawCodewords($matrix, $function, array_merge($dataCodewords, $eccCodewords));
        $this->drawFormatBits($matrix, $function);

        $outerSize = ($size + ($margin * 2)) * $scale;
        $rects = [];

        for ($row = 0; $row < $size; $row++) {
            for ($col = 0; $col < $size; $col++) {
                if (! $matrix[$row][$col]) {
                    continue;
                }

                $x = ($col + $margin) * $scale;
                $y = ($row + $margin) * $scale;
                $rects[] = '<rect x="'.$x.'" y="'.$y.'" width="'.$scale.'" height="'.$scale.'"/>';
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$outerSize.'" height="'.$outerSize.'" viewBox="0 0 '.$outerSize.' '.$outerSize.'" shape-rendering="crispEdges"><rect width="100%" height="100%" fill="#fff"/><g fill="#000">'.implode('', $rects).'</g></svg>';
    }

    private function versionForByteLength(int $length): int
    {
        foreach (self::VERSIONS as $version => $spec) {
            $capacity = $spec['data'] - 2;

            if ($length <= $capacity) {
                return $version;
            }
        }

        return 5;
    }

    /**
     * @param  array<int, int>  $bytes
     * @return array<int, int>
     */
    private function makeDataCodewords(array $bytes, int $capacity): array
    {
        $bits = [0, 1, 0, 0];
        $bits = array_merge($bits, $this->bits(count($bytes), 8));

        foreach ($bytes as $byte) {
            $bits = array_merge($bits, $this->bits($byte, 8));
        }

        $maxBits = $capacity * 8;
        $terminator = min(4, $maxBits - count($bits));
        $bits = array_merge($bits, array_fill(0, max(0, $terminator), 0));

        while ((count($bits) % 8) !== 0) {
            $bits[] = 0;
        }

        $codewords = [];
        foreach (array_chunk($bits, 8) as $chunk) {
            $value = 0;
            foreach ($chunk as $bit) {
                $value = ($value << 1) | $bit;
            }

            $codewords[] = $value;
        }

        $pads = [0xec, 0x11];
        $padIndex = 0;
        while (count($codewords) < $capacity) {
            $codewords[] = $pads[$padIndex % 2];
            $padIndex++;
        }

        return array_slice($codewords, 0, $capacity);
    }

    /**
     * @param  array<int, int>  $data
     * @return array<int, int>
     */
    private function makeErrorCorrection(array $data, int $degree): array
    {
        $generator = $this->generatorPolynomial($degree);
        $remainder = array_fill(0, $degree, 0);

        foreach ($data as $byte) {
            $factor = $byte ^ $remainder[0];
            array_shift($remainder);
            $remainder[] = 0;

            for ($i = 0; $i < $degree; $i++) {
                $remainder[$i] ^= $this->multiply($generator[$i], $factor);
            }
        }

        return $remainder;
    }

    /**
     * @return array<int, int>
     */
    private function generatorPolynomial(int $degree): array
    {
        $result = [1];

        for ($i = 0; $i < $degree; $i++) {
            $result[] = 0;
            $root = $this->exp[$i];

            for ($j = 0; $j < count($result) - 1; $j++) {
                $result[$j] = $this->multiply($result[$j], $root) ^ $result[$j + 1];
            }

            $last = count($result) - 1;
            $result[$last] = $this->multiply($result[$last], $root);
        }

        return $result;
    }

    private function drawFunctionPatterns(array &$matrix, array &$function, int $version): void
    {
        $size = self::VERSIONS[$version]['modules'];
        $this->drawFinder($matrix, $function, 0, 0);
        $this->drawFinder($matrix, $function, $size - 7, 0);
        $this->drawFinder($matrix, $function, 0, $size - 7);

        for ($i = 8; $i < $size - 8; $i++) {
            $this->setFunction($matrix, $function, 6, $i, $i % 2 === 0);
            $this->setFunction($matrix, $function, $i, 6, $i % 2 === 0);
        }

        foreach (self::VERSIONS[$version]['align'] as $row) {
            foreach (self::VERSIONS[$version]['align'] as $col) {
                if ($function[$row][$col] ?? false) {
                    continue;
                }

                $this->drawAlignment($matrix, $function, $row, $col);
            }
        }

        for ($i = 0; $i < 9; $i++) {
            $this->reserve($function, 8, $i);
            $this->reserve($function, $i, 8);
            $this->reserve($function, $size - 1 - $i, 8);
            $this->reserve($function, 8, $size - 1 - $i);
        }

        $this->setFunction($matrix, $function, 8, $size - 8, true);
    }

    private function drawFinder(array &$matrix, array &$function, int $left, int $top): void
    {
        $size = count($matrix);

        for ($row = -1; $row <= 7; $row++) {
            for ($col = -1; $col <= 7; $col++) {
                $r = $top + $row;
                $c = $left + $col;

                if ($r < 0 || $c < 0 || $r >= $size || $c >= $size) {
                    continue;
                }

                $dark = ($row >= 0 && $row <= 6 && $col >= 0 && $col <= 6)
                    && ($row === 0 || $row === 6 || $col === 0 || $col === 6 || ($row >= 2 && $row <= 4 && $col >= 2 && $col <= 4));
                $this->setFunction($matrix, $function, $r, $c, $dark);
            }
        }
    }

    private function drawAlignment(array &$matrix, array &$function, int $centerRow, int $centerCol): void
    {
        for ($row = -2; $row <= 2; $row++) {
            for ($col = -2; $col <= 2; $col++) {
                $dark = max(abs($row), abs($col)) !== 1;
                $this->setFunction($matrix, $function, $centerRow + $row, $centerCol + $col, $dark);
            }
        }
    }

    /**
     * @param  array<int, int>  $codewords
     */
    private function drawCodewords(array &$matrix, array $function, array $codewords): void
    {
        $size = count($matrix);
        $bits = [];

        foreach ($codewords as $codeword) {
            $bits = array_merge($bits, $this->bits($codeword, 8));
        }

        $bitIndex = 0;
        $upward = true;

        for ($right = $size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right--;
            }

            for ($i = 0; $i < $size; $i++) {
                $row = $upward ? ($size - 1 - $i) : $i;

                for ($col = $right; $col >= $right - 1; $col--) {
                    if ($function[$row][$col]) {
                        continue;
                    }

                    if (! array_key_exists($bitIndex, $bits)) {
                        continue;
                    }

                    $dark = $bits[$bitIndex] === 1;

                    if ((($row + $col) % 2) === 0) {
                        $dark = ! $dark;
                    }

                    $matrix[$row][$col] = $dark;
                    $bitIndex++;
                }
            }

            $upward = ! $upward;
        }
    }

    private function drawFormatBits(array &$matrix, array &$function): void
    {
        $size = count($matrix);
        $bits = $this->formatBits(1, 0);

        for ($i = 0; $i <= 5; $i++) {
            $this->setFunction($matrix, $function, 8, $i, (($bits >> $i) & 1) !== 0);
        }

        $this->setFunction($matrix, $function, 8, 7, (($bits >> 6) & 1) !== 0);
        $this->setFunction($matrix, $function, 8, 8, (($bits >> 7) & 1) !== 0);
        $this->setFunction($matrix, $function, 7, 8, (($bits >> 8) & 1) !== 0);

        for ($i = 9; $i < 15; $i++) {
            $this->setFunction($matrix, $function, 14 - $i, 8, (($bits >> $i) & 1) !== 0);
        }

        for ($i = 0; $i < 8; $i++) {
            $this->setFunction($matrix, $function, $size - 1 - $i, 8, (($bits >> $i) & 1) !== 0);
        }

        for ($i = 8; $i < 15; $i++) {
            $this->setFunction($matrix, $function, 8, $size - 15 + $i, (($bits >> $i) & 1) !== 0);
        }
    }

    private function formatBits(int $errorCorrectionLevel, int $mask): int
    {
        $data = ($errorCorrectionLevel << 3) | $mask;
        $rem = $data;

        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ ((($rem >> 9) & 1) * 0x537);
        }

        return (($data << 10) | $rem) ^ 0x5412;
    }

    private function setFunction(array &$matrix, array &$function, int $row, int $col, bool $dark): void
    {
        if (! isset($matrix[$row][$col])) {
            return;
        }

        $matrix[$row][$col] = $dark;
        $function[$row][$col] = true;
    }

    private function reserve(array &$function, int $row, int $col): void
    {
        if (isset($function[$row][$col])) {
            $function[$row][$col] = true;
        }
    }

    /**
     * @return array<int, int>
     */
    private function bits(int $value, int $length): array
    {
        $bits = [];

        for ($i = $length - 1; $i >= 0; $i--) {
            $bits[] = ($value >> $i) & 1;
        }

        return $bits;
    }

    private function buildGaloisTables(): void
    {
        $x = 1;

        for ($i = 0; $i < 255; $i++) {
            $this->exp[$i] = $x;
            $this->log[$x] = $i;
            $x <<= 1;

            if (($x & 0x100) !== 0) {
                $x ^= 0x11d;
            }
        }

        for ($i = 255; $i < 512; $i++) {
            $this->exp[$i] = $this->exp[$i - 255];
        }
    }

    private function multiply(int $x, int $y): int
    {
        if ($x === 0 || $y === 0) {
            return 0;
        }

        return $this->exp[$this->log[$x] + $this->log[$y]];
    }
}
