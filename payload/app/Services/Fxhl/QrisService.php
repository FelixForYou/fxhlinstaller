<?php

namespace Pterodactyl\Services\Fxhl;

use RuntimeException;

class QrisService
{
    public function withAmount(string $payload, int $amount): string
    {
        $payload = preg_replace('/\s+/', '', trim($payload));
        if (!$payload || strlen($payload) < 16) {
            throw new RuntimeException('Payload QRIS belum diatur atau tidak valid.');
        }

        $fields = $this->parse($payload);
        $output = [];
        $hasPoi = false;

        foreach ($fields as [$tag, $value]) {
            if ($tag === '63' || $tag === '54') {
                continue;
            }
            if ($tag === '01') {
                $value = '12';
                $hasPoi = true;
            }
            $output[] = [$tag, $value];
        }

        if (!$hasPoi) {
            $position = 1;
            array_splice($output, $position, 0, [["01", "12"]]);
        }

        $amountField = ['54', (string) $amount];
        $countryIndex = null;
        foreach ($output as $index => [$tag]) {
            if ($tag === '58') {
                $countryIndex = $index;
                break;
            }
        }
        if (is_null($countryIndex)) {
            $output[] = $amountField;
        } else {
            array_splice($output, $countryIndex, 0, [$amountField]);
        }

        $encoded = '';
        foreach ($output as [$tag, $value]) {
            $encoded .= $tag . str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT) . $value;
        }

        $encoded .= '6304';
        return $encoded . strtoupper(str_pad(dechex($this->crc16($encoded)), 4, '0', STR_PAD_LEFT));
    }

    private function parse(string $payload): array
    {
        $fields = [];
        $offset = 0;
        $length = strlen($payload);

        while ($offset + 4 <= $length) {
            $tag = substr($payload, $offset, 2);
            $fieldLengthRaw = substr($payload, $offset + 2, 2);
            if (!ctype_digit($fieldLengthRaw)) {
                throw new RuntimeException('Format payload QRIS tidak valid.');
            }
            $fieldLength = (int) $fieldLengthRaw;
            $value = substr($payload, $offset + 4, $fieldLength);
            if (strlen($value) !== $fieldLength) {
                throw new RuntimeException('Payload QRIS terpotong.');
            }
            $fields[] = [$tag, $value];
            $offset += 4 + $fieldLength;
            if ($tag === '63') {
                break;
            }
        }

        if (empty($fields) || $fields[0][0] !== '00') {
            throw new RuntimeException('Payload bukan format EMV QRIS yang dikenali.');
        }

        return $fields;
    }

    private function crc16(string $value): int
    {
        $crc = 0xFFFF;
        $length = strlen($value);
        for ($i = 0; $i < $length; ++$i) {
            $crc ^= ord($value[$i]) << 8;
            for ($bit = 0; $bit < 8; ++$bit) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }

        return $crc;
    }
}
