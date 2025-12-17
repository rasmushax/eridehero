# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Housefresh Tools is a WordPress plugin for managing affiliate products with automated price tracking, geo-targeted affiliate links, and schema markup generation.

## Key Commands

```bash
# Install dependencies
composer install

# Install for production with optimized autoloader
composer install --optimize-autoloader

# Run tests
./vendor/bin/phpunit
```

## Architecture

### Core Components

1. **Product Management System**
   - Custom Post Type: `hf_product` (non-public CPT)
   - Meta boxes for affiliate link management
   - ACF blocks for frontend display

2. **Price Scraping System**
   - **Scraper Manager** (`/includes/class-hft-scraper-manager.php`): Orchestrates scraping operations
   - **Parser Factory** (`/includes/parsers/class-hft-parser-factory.php`): Creates site-specific parsers
   - **Parser Registry** (`/includes/class-hft-scraper-registry.php`): Manages available parsers
   - Parsers in `/includes/parsers/` implement scraping logic for different retailers
   - Cron job runs every 5 minutes to update prices

3. **Database Tables** (6 custom tables)
   - `hft_tracked_links`: Affiliate links with current prices
   - `hft_price_history`: Historical price data
   - `hft_parser_rules`: Site-specific parsing configurations
   - `hft_scrapers`: Scraper settings
   - `hft_scraper_rules`: XPath/CSS selectors
   - `hft_scraper_logs`: Scraping operation logs

### Key Design Patterns

- **Singleton**: Main loader class (`class-hft-loader.php`)
- **Factory**: Parser creation (`class-hft-parser-factory.php`)
- **Strategy**: Different parsers implement common interface
- **Repository**: Data access layer (`class-hft-scraper-repository.php`)

### Critical Files

- `/housefresh-tools.php`: Plugin entry point
- `/includes/class-hft-loader.php`: Main orchestrator using Singleton pattern
- `/includes/class-hft-cron.php`: Schedules price update tasks
- `/includes/class-hft-schema-output.php`: SEO structured data generation

### Frontend Integration

- ACF blocks in `/blocks/` directory (affiliate-link, price-history)
- REST API endpoints via `class-hft-rest-controller.php`
- AJAX handlers in `class-hft-ajax.php`
- Schema.org structured data output via `class-hft-schema-output.php`

## Key Workflows

### Price Tracking Workflow
1. Products created as `hf_product` CPT
2. Tracking links added via meta boxes
3. Cron job triggers scraper every 5 minutes
4. Parser extracts price/status/shipping info
5. Data stored in `hft_tracked_links` and `hft_price_history`

### Affiliate Link Generation
1. Geo-detection via IPInfo API
2. Links selected based on user location
3. Affiliate parameters appended dynamically
4. Caching layer for performance

### Frontend Display
1. ACF blocks inserted in content
2. REST API fetches product data
3. JavaScript handles dynamic updates
4. Schema markup added to head

## Admin Interface

- **Main Menu**: "Housefresh Tools"
- **Submenus**:
  - Products (CPT management)
  - Settings (General plugin settings)
  - Scraper Settings (Parser configuration)
  - Scraper Logs (Operation history)

## Development Guidelines

1. **Adding New Parsers**: 
   - Create new parser class extending `HFT_Base_Parser` in `/includes/parsers/`
   - Register in parser factory
   - Add parsing rules via admin interface

2. **Database Operations**: 
   - Use prepared statements
   - Access tables via global `$wpdb` with proper prefixing

3. **Error Handling**: 
   - Log scraping errors to `hft_scraper_logs` table
   - Use WordPress error logging for development

4. **Testing Price Scraping**:
   - Use admin interface at Products > Scraper Settings
   - Manual scrape button available on product edit screens
   - Check logs at Products > Scraper Logs

## Testing Commands

```bash
# Run all tests
./vendor/bin/phpunit

# Run specific test file
./vendor/bin/phpunit tests/TestClassName.php

# Run with code coverage
./vendor/bin/phpunit --coverage-html coverage/
```

## External Dependencies

- **Guzzle**: HTTP client for web scraping
- **IPInfo**: Geo-detection for affiliate links
- **Masterminds HTML5**: HTML parsing and DOM manipulation
- **PHPUnit**: Testing framework
- **Advanced Custom Fields (ACF)**: Required WordPress plugin for blocks/fields

## Requirements

- PHP 7.4+
- WordPress 5.0+
- Advanced Custom Fields (ACF) plugin
- Composer for dependency management