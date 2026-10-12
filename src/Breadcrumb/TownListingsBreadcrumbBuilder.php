<?php

namespace Drupal\town_listings_router\Breadcrumb;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;

/**
 * Builds breadcrumbs for /town-listings-router/{town_tid}/{category_tid}.
 *
 * Produces:
 *   Home > British Columbia > Gabriola > Services > Contractor > Carpenter
 *
 * Where:
 *  - Home, British Columbia, Gabriola come from the town term's own
 *    alias hierarchy (province > town).
 *  - Services, Contractor, Carpenter are each linked to the COMBINED
 *    town+category alias at that depth, e.g.:
 *      /british-columbia/gabriola/services
 *      /british-columbia/gabriola/services/contractor
 *      /british-columbia/gabriola/services/contractor/carpenter
 */
final class TownListingsBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match): bool {
    return $route_match->getRouteName() === 'town_listings_router.listing_page';
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match): Breadcrumb {

    $breadcrumb = new Breadcrumb();
    $links = [];

    $links[] = Link::createFromRoute($this->t('Home'), '<front>');

    $townTid = (int) $route_match->getRawParameter('town_tid');
    $categoryTid = (int) $route_match->getRawParameter('category_tid');

    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $townTerm = $storage->load($townTid);
    $categoryTerm = $storage->load($categoryTid);

    if (!$townTerm || !$categoryTerm) {
      $breadcrumb->setLinks($links);
      // Breadcrumb has no setCacheMaxAge(); it only offers the "refinable"
      // methods (addCacheContexts, addCacheTags, mergeCacheMaxAge). Vary by
      // route and invalidate when any term is saved, so the trail rebuilds
      // once the missing term exists, without disabling caching entirely.
      $breadcrumb->addCacheContexts(['route']);
      $breadcrumb->addCacheTags(['taxonomy_term_list']);
      return $breadcrumb;
    }

    // --- Town hierarchy: province > town ---
    // loadAllParents() includes the term itself, so filter it out before
    // merging with $townTerm to avoid a duplicate entry.
    $townAncestors = array_filter(
      array_reverse($storage->loadAllParents($townTid)),
      fn ($term) => (int) $term->id() !== $townTid
    );
    $townChain = array_merge($townAncestors, [$townTerm]);

    $townPathSegments = [];
    foreach ($townChain as $term) {
      $alias = $this->getAlias((int) $term->id());
      if ($alias === NULL) {
        continue;
      }
      $townPathSegments = $this->pathSegments($alias);
      $links[] = Link::fromTextAndUrl($term->label(), Url::fromUri('base:' . ltrim($alias, '/')));
    }

    // --- Category hierarchy: top-level category > ... > selected category ---
    // loadAllParents() includes the term itself, so filter it out before
    // merging with $categoryTerm to avoid a duplicate entry.
    $categoryAncestors = array_filter(
      array_reverse($storage->loadAllParents($categoryTid)),
      fn ($term) => (int) $term->id() !== $categoryTid
    );
    $categoryChain = array_merge($categoryAncestors, [$categoryTerm]);

    // Combined alias accumulates: starts at the town's own alias path,
    // then appends each category segment in turn.
    $combinedSegments = $townPathSegments;
    foreach ($categoryChain as $term) {
      $categoryAlias = $this->getAlias((int) $term->id());
      if ($categoryAlias === NULL) {
        continue;
      }
      $categorySegments = $this->pathSegments($categoryAlias);
      $lastSegment = end($categorySegments);
      $combinedSegments[] = $lastSegment;

      // Don't link the current page itself in its own breadcrumb trail.
      if ((int) $term->id() === $categoryTid) {
        continue;
      }

      $combinedAlias = '/' . implode('/', $combinedSegments);
      $links[] = Link::fromTextAndUrl($term->label(), Url::fromUri('base:' . ltrim($combinedAlias, '/')));
    }

    $breadcrumb->setLinks($links);
    $breadcrumb->addCacheContexts(['route']);
    $breadcrumb->addCacheTags(['taxonomy_term:' . $townTid, 'taxonomy_term:' . $categoryTid]);

    return $breadcrumb;
  }

  /**
   * Returns the alias for a taxonomy term, or NULL if none exists.
   */
  private function getAlias(int $tid): ?string {
    $systemPath = '/taxonomy/term/' . $tid;
    $alias = \Drupal::service('path_alias.manager')->getAliasByPath($systemPath);
    return $alias === $systemPath ? NULL : $alias;
  }

  /**
   * Splits an alias into its path segments, e.g. '/a/b' => ['a', 'b'].
   *
   * @return string[]
   */
  private function pathSegments(string $alias): array {
    return explode('/', trim($alias, '/'));
  }

  /**
   * Translates a string. Required since this class doesn't extend a base.
   */
  private function t(string $string): \Drupal\Core\StringTranslation\TranslatableMarkup {
    return new \Drupal\Core\StringTranslation\TranslatableMarkup($string);
  }

}
