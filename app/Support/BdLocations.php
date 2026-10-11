<?php

namespace App\Support;

/**
 * Bangladesh districts and upazilas (data: app/Support/data/bd-locations.json).
 * Dhaka district also lists the Dhaka city thanas.
 */
class BdLocations
{
    private static ?array $cache = null;

    /** @return array<int, array{n:string,b:string,u:array}> */
    public static function all(): array
    {
        if (self::$cache === null) {
            $json = @file_get_contents(__DIR__ . '/data/bd-locations.json');
            $data = $json ? json_decode($json, true) : null;
            self::$cache = is_array($data) ? ($data['districts'] ?? []) : [];
        }

        return self::$cache;
    }

    public static function districtNames(): array
    {
        return array_column(self::all(), 'n');
    }

    public static function hasUpazila(string $district, string $upazila): bool
    {
        foreach (self::all() as $d) {
            if ($d['n'] === $district) {
                return in_array($upazila, array_column($d['u'], 0), true);
            }
        }

        return false;
    }
}
