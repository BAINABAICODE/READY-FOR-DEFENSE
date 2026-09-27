<?php

namespace App\Support;

/**
 * Canonical locus and allele keys shared by base colors, visual mutations,
 * and split/hidden genes. A bird has two copies of an autosomal gene and
 * one copy of a sex-linked gene on a hen.
 */
class GeneticLocus
{
    public static function normalize(?string $allele): ?string
    {
        if ($allele === null) {
            return null;
        }

        $value = strtolower(trim($allele));
        if ($value === '') {
            return null;
        }

        $value = str_replace('*', '', $value);
        if (preg_match('/^([a-z0-9]+)(\+)?/', $value, $match) !== 1) {
            return $value;
        }

        return $match[1].($match[2] ?? '');
    }

    public static function tokenFromAlleleText(?string $allele): ?string
    {
        if ($allele === null || trim($allele) === '') {
            return null;
        }

        if (preg_match('/^(?:one|two)\s+([A-Za-z0-9*]+)/i', $allele, $match) === 1) {
            return self::normalize($match[1]);
        }

        if (preg_match('/^([A-Za-z0-9*]+)/', $allele, $match) === 1) {
            return self::normalize($match[1]);
        }

        return null;
    }

    public static function fromSeriesAndAllele(?string $series, ?string $allele): ?string
    {
        $seriesKey = strtolower(trim((string) $series));

        if (preg_match('/\ba locus\b/', $seriesKey) === 1) {
            return 'locus:a';
        }

        if (str_contains($seriesKey, 'ino locus')) {
            return 'locus:sl-ino';
        }

        if (preg_match('/base-color locus|locus bl|\bbl locus\b/', $seriesKey) === 1) {
            return 'locus:bl';
        }

        if ($allele !== null && preg_match('/Allele of ([^.]+)/i', $allele, $match) === 1) {
            $of = strtolower(trim($match[1]));
            if ($of === 'a') {
                return 'locus:a';
            }
            if ($of === 'ino') {
                return 'locus:sl-ino';
            }

            $ofKey = self::normalize($of);

            return $ofKey ? 'allele:'.$ofKey : null;
        }

        $token = self::tokenFromAlleleText($allele);

        return $token ? 'allele:'.$token : null;
    }

    /**
     * @return list<string>
     */
    public static function blAlleles(?string $geneticCode): array
    {
        if ($geneticCode === null || trim($geneticCode) === '') {
            return [];
        }

        $blPart = explode('|', $geneticCode)[0] ?? '';
        $parts = preg_split('/\s*\/\s*/', trim($blPart)) ?: [];
        $alleles = [];

        foreach ($parts as $part) {
            $normalized = self::normalize($part);
            if ($normalized !== null) {
                $alleles[] = $normalized;
            }
        }

        return $alleles;
    }

    public static function canHideBlSplit(?string $geneticCode): bool
    {
        $alleles = self::blAlleles($geneticCode);
        if ($alleles === []) {
            return true;
        }

        foreach ($alleles as $allele) {
            if ($allele !== 'bl+') {
                return false;
            }
        }

        return true;
    }

    public static function isBlLocus(?string $locusKey): bool
    {
        return $locusKey === 'locus:bl';
    }
}
