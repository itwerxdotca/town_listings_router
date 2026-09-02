# Town Listings Router

## Overview

A custom Drupal 11 module for TownsCanada that enables combined taxonomy URL routing
for listings and points of interest pages. It allows URLs that combine a Canadian town
path with a listing category or POI category path into a single unified URL structure.

For example:
/british-columbia/gabriola/food-beverage/cafe-bakery
/british-columbia/gabriola/attractions/parks/provincial-park

Is a combination of:

- Town alias: `/british-columbia/gabriola` (canadian_towns vocabulary)
- Category alias: `/food-beverage/cafe-bakery` (listing_category vocabulary)

## How It Works

### Inbound Path Processing

`TownListingsPathProcessor` intercepts incoming requests and resolves combined town +
category URLs into an internal route handled by `TownListingsController`. It tries every
possible split point in the URL to find a valid town alias and category alias combination,
then rewrites the path to `/town-listings-router/{town_tid}/{category_tid}`.

### Outbound Path Processing

`TownListingsOutboundPathProcessor` converts internal router URLs back to their aliased
equivalents. This ensures canonical URLs, Metatag tokens, and any generated links use the
clean aliased URL rather than the internal router path. Runs at priority 50, after core's
alias processor at 100.

### Controller

`TownListingsController` renders the `listings` View (`page_2` display) filtered by the
resolved town and category term IDs and generates the page title from the term names.

### Breadcrumbs

`TownListingsBreadcrumbBuilder` builds the breadcrumb trail for combined URL pages,
walking up through both the town and category hierarchies.

### XML Sitemap Integration

Implements `hook_xmlsitemap_index_links()` to register combined town + category URLs in
the sitemap. Only generates URLs where at least one published listing or point of interest
exists for that town + category combination, ensuring the sitemap only contains URLs with
real content.

Implements `hook_xmlsitemap_rebuild_clear()` intentionally empty to prevent xmlsitemap
from deleting combined URL links during rebuild, since they are non-entity based and are
re-indexed via `hook_xmlsitemap_index_links` during cron.

## Requirements

### Drupal Core

- Drupal 11.x

### Contributed Modules

- **Path Alias** (core) — URL alias resolution
- **XML Sitemap** (`xmlsitemap`) 2.x — sitemap integration

### Vocabularies

The following taxonomy vocabularies must exist with Pathauto patterns configured:

#### `canadian_towns`

- Two-level hierarchy: Province → Town
- Pathauto pattern: `/province/town`
- Example: `/british-columbia/gabriola`

#### `listing_category`

- Up to 4 levels of depth supported
- Pathauto pattern: `/category/subcategory/...`
- Example: `/food-beverage/cafe-bakery`

#### `poi_category`

- Up to 4 levels of depth supported
- Pathauto pattern: `/category/subcategory/...`
- Example: `/attractions/parks/provincial-park`

### Content Types

#### `listing`

- `field_canadian_towns` — Entity reference to `canadian_towns` term
- `field_listing_category` — Entity reference to `listing_category` term

#### `point_of_interest`

- `field_canadian_towns` — Entity reference to `canadian_towns` term
- `field_poi_category` — Entity reference to `poi_category` term

### Views

The `listings` View must exist with a `page_2` display configured with two contextual
filters:

1. `field_canadian_towns_target_id` — filters by canadian_towns term ID
2. `field_listing_category_target_id` — filters by listing_category term ID

### Metatag

Global Metatag canonical URL must be set to `[current-page:url]` to correctly resolve
combined URLs as canonical. Verify at **Configuration → Search and metadata → Metatag →
Global**.

### Path Processor Priorities

- Inbound processor runs at priority 200 — higher than core alias processor (100) so
  combined paths are claimed before core attempts to resolve them
- Outbound processor runs at priority 50 — lower than core alias processor (100) so
  aliases are resolved before outbound conversion runs

## Installation

```bash
drush en town_listings_router
drush cr
```

## Sitemap

### Automatic (Recommended)

Cron handles everything automatically. During each cron run xmlsitemap calls
`hook_xmlsitemap_index_links` which adds any new town + category combinations that have
published listings or POIs.

### Manual Rebuild

If a full rebuild is needed:

```bash
drush xmlsitemap:rebuild
drush xmlsitemap:index
drush xmlsitemap:regenerate
```

**Important:** Always run all three commands in sequence. `xmlsitemap:rebuild` alone will
clear the combined URLs — `xmlsitemap:index` re-adds them and `xmlsitemap:regenerate`
writes the XML files.

## Extending for Points of Interest

POI support follows the same pattern as listings using the `point_of_interest` content
type with `field_poi_category` and `field_canadian_towns` fields. The `XmlSitemapHooks`
class handles both content types via `hook_xmlsitemap_index_links`.