<?php
/**
 * Category Configuration - Single source of truth for product type/category constants.
 *
 * This class centralizes all product type mappings, eliminating duplicate definitions
 * scattered across 13+ files. Use this class instead of defining category arrays inline.
 *
 * @package ERH
 */

declare(strict_types=1);

namespace ERH;

/**
 * Centralized product category configuration for all components.
 */
class CategoryConfig {

    /**
     * Canonical category definitions.
     *
     * Key is the canonical key used internally (escooter, ebike, etc.).
     * All lookups resolve to these canonical definitions.
     *
     * @var array<string, array<string, mixed>>
     */
    public const CATEGORIES = [
        'escooter' => [
            'key'           => 'escooter',
            'type'          => 'Electric Scooter',
            'name'          => 'E-Scooters',
            'name_plural'   => 'Electric Scooters',
            'name_short'    => 'E-Scooter',
            'slug'          => 'electric-scooters',
            'archive_slug'  => 'e-scooters',
            'finder_slug'   => 'electric-scooter-finder', // WP page slug.
            'finder_key'    => 'escooter', // JSON file naming: finder_escooter.json.
            'acf_wrapper'   => 'e-scooters',
            'icon'          => 'escooter',
            'icon_class'    => 'icon-filled',
        ],
        'ebike' => [
            'key'           => 'ebike',
            'type'          => 'Electric Bike',
            'name'          => 'E-Bikes',
            'name_plural'   => 'Electric Bikes',
            'name_short'    => 'E-Bike',
            'slug'          => 'e-bikes',
            'archive_slug'  => 'e-bikes',
            'finder_slug'   => 'electric-bike-finder', // WP page slug.
            'finder_key'    => 'ebike',
            'acf_wrapper'   => 'e-bikes',
            'icon'          => 'ebike',
            'icon_class'    => 'icon-filled',
        ],
        'eskateboard' => [
            'key'           => 'eskateboard',
            'type'          => 'Electric Skateboard',
            'name'          => 'E-Skateboards',
            'name_plural'   => 'Electric Skateboards',
            'name_short'    => 'E-Skateboard',
            'slug'          => 'e-skateboards',
            'archive_slug'  => 'e-skateboards',
            'finder_slug'   => 'electric-skateboard-finder',
            'finder_key'    => 'eskate',
            'acf_wrapper'   => 'e-skateboards',
            'icon'          => 'eskate',
            'icon_class'    => '',
        ],
        'euc' => [
            'key'           => 'euc',
            'type'          => 'Electric Unicycle',
            'name'          => 'Electric Unicycles',
            'name_plural'   => 'Electric Unicycles',
            'name_short'    => 'EUC',
            'slug'          => 'electric-unicycles',
            'archive_slug'  => 'eucs',
            'finder_slug'   => 'electric-unicycle-finder',
            'finder_key'    => 'euc',
            'acf_wrapper'   => 'eucs',
            'icon'          => 'euc',
            'icon_class'    => '',
        ],
        'hoverboard' => [
            'key'           => 'hoverboard',
            'type'          => 'Hoverboard',
            'name'          => 'Hoverboards',
            'name_plural'   => 'Hoverboards',
            'name_short'    => 'Hoverboard',
            'slug'          => 'hoverboards',
            'archive_slug'  => 'hoverboards',
            'finder_slug'   => 'hoverboard-finder',
            'finder_key'    => 'hoverboard',
            'acf_wrapper'   => 'hoverboards',
            'icon'          => 'hoverboard',
            'icon_class'    => 'icon-filled',
        ],
    ];

    /**
     * Legacy key aliases that map to canonical keys.
     *
     * Handles all the inconsistent keys used throughout the codebase.
     *
     * @var array<string, string>
     */
    private const KEY_ALIASES = [
        // Skateboard variations (the worst offender).
        'eskate'             => 'eskateboard',
        'skateboard'         => 'eskateboard',
        'electric-skateboard'=> 'eskateboard',
        'electric_skateboard'=> 'eskateboard',
        // Other aliases.
        'e-scooter'          => 'escooter',
        'electric-scooter'   => 'escooter',
        'electric_scooter'   => 'escooter',
        'scooter'            => 'escooter',
        'e-bike'             => 'ebike',
        'electric-bike'      => 'ebike',
        'electric_bike'      => 'ebike',
        'bike'               => 'ebike',
        'electric-unicycle'  => 'euc',
        'electric_unicycle'  => 'euc',
        'unicycle'           => 'euc',
    ];

    /**
     * Get all canonical category keys.
     *
     * @return array<string>
     */
    public static function get_keys(): array {
        return array_keys( self::CATEGORIES );
    }

    /**
     * Get all category definitions.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function get_all(): array {
        return self::CATEGORIES;
    }

    /**
     * Get category by canonical key.
     *
     * @param string $key Category key (canonical or alias).
     * @return array<string, mixed>|null Category definition or null if not found.
     */
    public static function get_by_key( string $key ): ?array {
        $key = self::normalize_key( $key );
        return self::CATEGORIES[ $key ] ?? null;
    }

    /**
     * Get category by product type label.
     *
     * @param string $product_type Product type (e.g., 'Electric Scooter').
     * @return array<string, mixed>|null Category definition or null if not found.
     */
    public static function get_by_type( string $product_type ): ?array {
        foreach ( self::CATEGORIES as $category ) {
            if ( $category['type'] === $product_type ) {
                return $category;
            }
        }
        return null;
    }

    /**
     * Get category by URL slug.
     *
     * @param string $slug URL slug (e.g., 'electric-scooters', 'e-bikes').
     * @return array<string, mixed>|null Category definition or null if not found.
     */
    public static function get_by_slug( string $slug ): ?array {
        foreach ( self::CATEGORIES as $category ) {
            if ( $category['slug'] === $slug || $category['archive_slug'] === $slug ) {
                return $category;
            }
        }
        return null;
    }

    /**
     * Convert product type label to canonical key.
     *
     * @param string $product_type Product type (e.g., 'Electric Scooter').
     * @return string Canonical key (e.g., 'escooter') or empty string if not found.
     */
    public static function type_to_key( string $product_type ): string {
        $category = self::get_by_type( $product_type );
        return $category ? $category['key'] : '';
    }

    /**
     * Convert canonical key to product type label.
     *
     * @param string $key Category key (canonical or alias).
     * @return string Product type label (e.g., 'Electric Scooter') or empty string if not found.
     */
    public static function key_to_type( string $key ): string {
        $category = self::get_by_key( $key );
        return $category ? $category['type'] : '';
    }

    /**
     * Convert URL slug to canonical key.
     *
     * @param string $slug URL slug (e.g., 'electric-scooters').
     * @return string Canonical key (e.g., 'escooter') or empty string if not found.
     */
    public static function slug_to_key( string $slug ): string {
        $category = self::get_by_slug( $slug );
        return $category ? $category['key'] : '';
    }

    /**
     * Convert canonical key to URL slug.
     *
     * @param string $key Category key (canonical or alias).
     * @return string URL slug (e.g., 'electric-scooters') or empty string if not found.
     */
    public static function key_to_slug( string $key ): string {
        $category = self::get_by_key( $key );
        return $category ? $category['slug'] : '';
    }

    /**
     * Get ACF wrapper key for a category.
     *
     * @param string $key Category key (canonical or alias).
     * @return string ACF wrapper key (e.g., 'e-scooters') or empty string if not found.
     */
    public static function get_acf_wrapper( string $key ): string {
        $category = self::get_by_key( $key );
        return $category ? $category['acf_wrapper'] : '';
    }

    /**
     * Get display name for a category.
     *
     * @param string $key Category key (canonical or alias).
     * @param string $format 'name', 'name_plural', or 'name_short'.
     * @return string Display name or empty string if not found.
     */
    public static function get_name( string $key, string $format = 'name' ): string {
        $category = self::get_by_key( $key );
        if ( ! $category ) {
            return '';
        }
        return $category[ $format ] ?? $category['name'];
    }

    /**
     * Check if a key is valid (canonical or alias).
     *
     * @param string $key Key to check.
     * @return bool True if valid.
     */
    public static function is_valid_key( string $key ): bool {
        $key = strtolower( trim( $key ) );
        return isset( self::CATEGORIES[ $key ] ) || isset( self::KEY_ALIASES[ $key ] );
    }

    /**
     * Normalize a key to its canonical form.
     *
     * Handles legacy aliases and case normalization.
     *
     * @param string $key Key to normalize (canonical or alias).
     * @return string Canonical key.
     */
    public static function normalize_key( string $key ): string {
        $key = strtolower( trim( $key ) );

        // Check if it's already canonical.
        if ( isset( self::CATEGORIES[ $key ] ) ) {
            return $key;
        }

        // Check aliases with original form.
        if ( isset( self::KEY_ALIASES[ $key ] ) ) {
            return self::KEY_ALIASES[ $key ];
        }

        // Try with spaces replaced by hyphens (handles "Electric Scooter" → "electric-scooter").
        $hyphenated = str_replace( ' ', '-', $key );
        if ( isset( self::KEY_ALIASES[ $hyphenated ] ) ) {
            return self::KEY_ALIASES[ $hyphenated ];
        }

        return $key;
    }

    /**
     * Get compare routes category map.
     *
     * Returns format compatible with erh_compare_category_map().
     *
     * @return array<string, array<string, string>>
     */
    public static function get_compare_map(): array {
        $map = [];
        foreach ( self::CATEGORIES as $category ) {
            $map[ $category['slug'] ] = [
                'key'         => $category['key'],
                'name'        => $category['name'],
                'name_plural' => $category['name_plural'],
                'type'        => $category['type'],
            ];
        }
        return $map;
    }

    /**
     * Get finder page config.
     *
     * Returns format compatible with erh_get_finder_page_config().
     *
     * @return array<string, array<string, string>>
     */
    public static function get_finder_config(): array {
        $config = [];
        foreach ( self::CATEGORIES as $category ) {
            $config[ $category['key'] ] = [
                'title'        => $category['name_plural'] . ' Database',
                'subtitle'     => 'Find your perfect ' . strtolower( $category['name_short'] ),
                'short'        => $category['name_short'],
                'product_type' => $category['type'],
            ];
        }
        return $config;
    }

    /**
     * Get hub categories data for template.
     *
     * Returns format compatible with hub-categories.php inline array.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function get_hub_categories(): array {
        $categories = [];
        foreach ( self::CATEGORIES as $category ) {
            $categories[] = [
                'key'          => $category['key'],
                'slug'         => $category['slug'],
                'name'         => $category['name_plural'],
                'icon'         => $category['icon'],
                'icon_class'   => $category['icon_class'],
                'product_type' => $category['type'],
            ];
        }
        return $categories;
    }

    /**
     * Export configuration for JavaScript.
     *
     * Returns a JSON-serializable array for use in JS.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function export_for_js(): array {
        $export = [];
        foreach ( self::CATEGORIES as $key => $category ) {
            $export[ $key ] = [
                'key'         => $category['key'],
                'type'        => $category['type'],
                'name'        => $category['name'],
                'namePlural'  => $category['name_plural'],
                'nameShort'   => $category['name_short'],
                'slug'        => $category['slug'],
                'archiveSlug' => $category['archive_slug'],
                'finderKey'   => $category['finder_key'],
                'icon'        => $category['icon'],
            ];
        }
        return $export;
    }

    /**
     * Get product type to finder key mapping.
     *
     * Used by cron jobs for JSON file naming (e.g., finder_escooter.json).
     *
     * @return array<string, string>
     */
    public static function get_type_to_finder_key(): array {
        $map = [];
        foreach ( self::CATEGORIES as $category ) {
            $map[ $category['type'] ] = $category['finder_key'];
        }
        return $map;
    }

    /**
     * Get finder key to archive slug mapping.
     *
     * Used for mapping JSON file key to URL slug.
     *
     * @return array<string, string>
     */
    public static function get_finder_key_to_slug(): array {
        $map = [];
        foreach ( self::CATEGORIES as $category ) {
            $map[ $category['finder_key'] ] = $category['archive_slug'];
        }
        return $map;
    }

    /**
     * Get finder key from product type.
     *
     * @param string $product_type Product type (e.g., 'Electric Scooter').
     * @return string Finder key for JSON files (e.g., 'escooter').
     */
    public static function type_to_finder_key( string $product_type ): string {
        $category = self::get_by_type( $product_type );
        return $category ? $category['finder_key'] : '';
    }

    /**
     * Get finder slug to finder key mapping.
     *
     * Used by page-finder.php to determine product type from WP page slug.
     * Single source of truth - eliminates duplicate slug maps.
     *
     * @return array<string, string> Slug => finder_key mapping.
     */
    public static function get_finder_slug_map(): array {
        $map = [];
        foreach ( self::CATEGORIES as $category ) {
            $map[ $category['finder_slug'] ] = $category['finder_key'];
        }
        return $map;
    }
}
