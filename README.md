# Outstand Query Loop Dedup

> Prevents duplicate posts across multiple Query Loop blocks on the same page.

Opt-in per block via a toggle in the block editor. Supports custom queries, inherited queries, and arbitrary custom blocks via a [resolver filter](docs/deduplication.md#outstand_query_loop_deduplication_resolvers). Includes editor-side preview, PHP filters for per-block overrides, and an `exclude_duplicates` `WP_Query` arg for ad-hoc lists.

See [docs/deduplication.md](docs/deduplication.md) for the full feature guide.

## Installation

### Manual Installation

1. Download the plugin ZIP file from the GitHub repository.
2. Go to Plugins > Add New > Upload Plugin in your WordPress admin area.
3. Upload the ZIP file and click Install Now.
4. Activate the plugin.

### Install with Composer

To include this plugin as a dependency in your Composer-managed WordPress project:

1. Add the plugin to your project using the following command:

```bash
composer require outstand/query-loop-dedup
```

2. Run `composer install` to install the plugin.
3. Activate the plugin from your WordPress admin area or using WP-CLI.

## Quick start

1. Add multiple Query Loop blocks to a page.
2. Enable **Exclude duplicate posts** in the block sidebar under the **Deduplication** panel.
3. Each subsequent block will skip posts already shown by previous blocks.

## Requirements

- WordPress 6.7 or higher
- PHP 8.2 or higher

### Tests

JS tests run locally via Jest:

```bash
npm run test:js
```

PHP tests run inside a `wp-env` container:

```bash
npm run test:setup   # first time only — starts Docker WP + test DB
npm run test:unit
```

## Changelog

All notable changes to this project are documented in [CHANGELOG.md](https://github.com/pixelalbatross/outstand-query-loop-dedup/blob/main/CHANGELOG.md).

## License

This project is licensed under the [GPL-3.0-or-later](https://spdx.org/licenses/GPL-3.0-or-later.html).
