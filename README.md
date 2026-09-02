Town Listings Router
Overview

A custom Drupal 11 module for TownsCanada that enables combined taxonomy URL routing for listings pages. It allows URLs that combine a Canadian town path with a listing category path into a single unified URL structure.

For example:

/british-columbia/gabriola/food-beverage/cafe-bakery

Is a combination of:

Town alias: /british-columbia/gabriola (canadian_towns vocabulary)
Category alias: /food-beverage/cafe-bakery (listing_category vocabulary)
How It Works
Inbound Path Processing

TownListingsPathProcessor intercepts incoming requests and resolves combined town + category URLs into an internal route handled by TownListingsController. It tries every possible split point in the URL to find a valid town alias and category alias combination, then rewrites the path to /town-listings-router/{town_tid}/{category_tid}.

Outbound Path Processing

TownListingsOutboundPathProcessor converts internal router URLs back to their aliased equivalents. This ensures canonical URLs, Metatag tokens, and any generated links use the clean aliased URL rather than the internal router path. Runs at priority 50, after core's alias processor at 100.

Controller

TownListingsController renders the listings View (page_2 display) filtered by the resolved town and category term IDs and generates the page title from the term names.

Breadcrumbs

TownListingsBreadcrumbBuilder builds the breadcrumb trail for combined URL pages, walking up through both the town and category hierarchies.

XML Sitemap Integration

Implements hook_xmlsitemap_link_info() to register a custom town_listing_page link type. During sitemap rebuild it queries all published listing nodes and generates sitemap entries for every unique town + category combination that has at least one published listing, ensuring the sitemap only contains URLs with real content.

Requirements
Drupal Core
Drupal 11.x
Contributed Modules
Path Alias (core) — URL alias resolution
XML Sitemap (xmlsitemap) 2.x — sitemap integration
Vocabularies

The following taxonomy vocabularies must exist with Pathauto patterns configured:

canadian_towns
Two-level hierarchy: Province → Town
Pathauto pattern: /province/town
Example: /british-columbia/gabriola
listing_category
Up to 4 levels of depth supported
Pathauto pattern: /category/subcategory/...
Example: /food-beverage/cafe-bakery
Content Type

listing content type must exist with the following fields:

field_canadian_towns — Entity reference to canadian_towns term
field_listing_category — Entity reference to listing_category term
Views

The listings View must exist with a page_2 display configured with two contextual filters:

field_canadian_towns_target_id — filters by canadian_towns term ID
field_listing_category_target_id — filters by listing_category term ID
Metatag

Global Metatag canonical URL must be set to [current-page:url] to correctly resolve combined URLs as canonical. Verify at Configuration → Search and metadata → Metatag → Global.

Path Processor Priorities
Inbound processor runs at priority 200 — higher than core alias processor (100) so combined paths are claimed before core attempts to resolve them
Outbound processor runs at priority 50 — lower than core alias processor (100) so aliases are resolved before outbound conversion runs
Installation
bash
drush en town_listings_router
drush cr
Sitemap Rebuild

After enabling or after adding new listings rebuild the XML sitemap:

bash
drush xmlsitemap:rebuild

The sitemap will include one entry per unique town + listing category combination that has at least one published listing node.

Extending for Points of Interest

This module is designed to be extended for the point_of_interest content type using field_poi_category and field_canadian_towns following the same pattern as listings.