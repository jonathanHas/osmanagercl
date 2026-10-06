<?php

namespace App\Support;

/**
 * Reads and writes uniCenta PRODUCTS.ATTRIBUTES, a java.util.Properties XML
 * blob (Properties.storeToXML format). The till copies these properties onto
 * every ticket line of the product; the deposit scripts read deposit.id,
 * deposit.name and deposit.price from them.
 */
final class PosProductAttributes
{
    public const DEPOSIT_KEYS = ['deposit.id', 'deposit.name', 'deposit.price'];

    /**
     * @return array<string, string> key => value; empty for null, blank or unparseable XML
     */
    public static function parse(?string $xml): array
    {
        if ($xml === null || trim($xml) === '') {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $doc = simplexml_load_string($xml);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if ($doc === false || $doc->getName() !== 'properties') {
            return [];
        }

        $entries = [];
        foreach ($doc->entry as $entry) {
            $key = (string) $entry['key'];
            if ($key !== '') {
                $entries[$key] = (string) $entry;
            }
        }

        return $entries;
    }

    /**
     * @param  array<string, string>  $entries
     */
    public static function serialize(array $entries, string $comment = 'osmanager deposit'): ?string
    {
        if ($entries === []) {
            return null;
        }

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>'."\n"
            .'<!DOCTYPE properties SYSTEM "http://java.sun.com/dtd/properties.dtd">'."\n"
            ."<properties>\n"
            .'<comment>'.self::escape($comment)."</comment>\n";

        foreach ($entries as $key => $value) {
            $xml .= '<entry key="'.self::escape((string) $key).'">'.self::escape((string) $value)."</entry>\n";
        }

        return $xml."</properties>\n";
    }

    public static function withDeposit(?string $xml, string $id, string $name, string $price): string
    {
        $entries = array_merge(self::parse($xml), [
            'deposit.id' => $id,
            'deposit.name' => $name,
            'deposit.price' => $price,
        ]);

        return self::serialize($entries);
    }

    public static function withoutDeposit(?string $xml): ?string
    {
        $entries = array_diff_key(self::parse($xml), array_flip(self::DEPOSIT_KEYS));

        return self::serialize($entries);
    }

    public static function hasDeposit(?string $xml): bool
    {
        return self::depositId($xml) !== null;
    }

    public static function depositId(?string $xml): ?string
    {
        $id = self::parse($xml)['deposit.id'] ?? null;

        return $id === '' ? null : $id;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
