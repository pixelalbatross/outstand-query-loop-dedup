# Changelog

All notable changes to this project will be documented in this file, per [the Keep a Changelog standard](http://keepachangelog.com/).

## [Unreleased]

## [1.2.0] - 2026-07-10

- Added a `languages/` directory with `outstand-query-loop-dedup.pot` and `translate:*` npm scripts for i18n string extraction and compilation.
- Guarded the update checker with a `class_exists( PucFactory::class )` check.
- Moved the private constructors below the public members in `Plugin` and `Deduplicator` to follow the public → private ordering convention.
- Documented in the README that inherited-query pagination counts are recomputed only for duplicates known at render time.

## [1.1.0] - 2026-07-05

- Renamed the public filters to match the plugin slug: `outstand_query_loop_deduplication_resolvers` → `outstand_query_loop_dedup_resolvers`, `outstand_query_loop_deduplication_should_track` → `outstand_query_loop_dedup_should_track`, and `outstand_query_loop_deduplication_should_exclude` → `outstand_query_loop_dedup_should_exclude`.
- Renamed the internal `WP_Query` capture flag `outstand_dedup_capture` → `outstand_query_loop_dedup_capture`.
- **Breaking:** integrations using the old filter names must update to the new names.

## [1.0.2] - 2026-06-29

- Fixed `OUTSTAND_QUERY_LOOP_DEDUP_VERSION` constant being out of sync with the plugin version.
- Simplified the Composer autoload guard to bail early when `vendor/autoload.php` is missing.

## [1.0.1] - 2026-05-08

- Added automated GitHub Release packaging via reusable release workflow; installation now points to the latest release ZIP.

## [1.0.0] - 2026-04-19

- Initial release.
