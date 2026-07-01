<?php

namespace Drupal\town_listings_router\PathProcessor;

use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\town_listings_router\Resolver\AliasPathResolverInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Processes incoming paths to match town + listing category combinations.
 *
 * Given a URL like /british-columbia/gabriola/food-beverage/restaurants,
 * this processor:
 *  1. Tries every split point to find a valid town alias + category alias.
 *  2. Resolves each alias to its taxonomy term ID.
 *  3. Rewrites the path to /town-listings-router/{town_tid}/{category_tid}
 *     so the TownListingsController can render the correct View.
 */
final class TownListingsPathProcessor implements InboundPathProcessorInterface {

  /**
   * Static cache of resolved aliases to term IDs, keyed by "alias:vocab".
   *
   * @var array<string, int|null>
   */
  private array $cache = [];

  public function __construct(
    private readonly AliasPathResolverInterface $aliasResolver,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

 /**
  * {@inheritdoc}
  */
 public function processInbound($path, Request $request) {
   // Only process the actual requested path, not partial paths generated
   // during breadcrumb building (which calls processInbound on truncated
   // sub-paths like /a/b when the real request is /a/b/c).
   if ($path !== $request->getPathInfo()) {
     return $path;
   }

   // Must have at least 3 segments: /province/town/category.
   $parts = explode('/', ltrim($path, '/'));
   $count = count($parts);
   if ($count < 3) {
     return $path;
   }
   // Try every possible split point between town and category segments.
   // e.g. for /british-columbia/gabriola/food-beverage/restaurants:
   //   town=/british-columbia/gabriola  category=/food-beverage/restaurants
   // Town must have at least 2 segments (province/town), so start the
   // split at index 2, leaving at least 1 segment for the category.
   for ($split = 2; $split < $count; $split++) {
     $townAlias = '/' . implode('/', array_slice($parts, 0, $split));
     $categoryAlias = '/' . implode('/', array_slice($parts, $split));
     $townTid = $this->resolveAlias($townAlias, 'canadian_towns');
     $categoryTid = $this->resolveAlias($categoryAlias, 'listing_category');
     if ($townTid && $categoryTid) {
       $request->attributes->set('_disable_route_normalizer', TRUE);
       return "/town-listings-router/{$townTid}/{$categoryTid}";
     }
   }
   return $path;
 }

  /**
   * Resolves a path alias to a taxonomy term ID within a specific vocabulary.
   *
   * @param string $alias
   *   The path alias, e.g. /british-columbia/gabriola.
   * @param string $vocabulary
   *   The vocabulary machine name to validate against.
   *
   * @return int|null
   *   The term ID, or NULL if not found or wrong vocabulary.
   */
  private function resolveAlias(string $alias, string $vocabulary): ?int {
    $cacheKey = "{$alias}:{$vocabulary}";
    if (array_key_exists($cacheKey, $this->cache)) {
      return $this->cache[$cacheKey];
    }

    $systemPath = $this->aliasResolver->getSystemPath($alias);

    // No alias found — resolver returns the input unchanged.
    if ($systemPath === $alias) {
      return $this->cache[$cacheKey] = NULL;
    }

    if (!preg_match('#^/taxonomy/term/(\d+)$#', $systemPath, $matches)) {
      return $this->cache[$cacheKey] = NULL;
    }

    $tid = (int) $matches[1];

    $term = $this->entityTypeManager
      ->getStorage('taxonomy_term')
      ->load($tid);

    if (!$term || $term->bundle() !== $vocabulary) {
      return $this->cache[$cacheKey] = NULL;
    }

    return $this->cache[$cacheKey] = $tid;
  }

}
