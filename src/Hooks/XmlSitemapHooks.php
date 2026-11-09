<?php

namespace Drupal\town_listings_router\Hooks;

use Drupal\Core\Database\Connection;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\xmlsitemap\XmlSitemapLinkStorageInterface;

/**
 * Keeps the XML sitemap's town, tag and town + category links in step with
 * the content.
 *
 * XML Sitemap's own cron only indexes its entity links; it does not call
 * other modules' hook_xmlsitemap_index_links() (only drush xmlsitemap:index
 * does). So town_listings_router_cron() calls syncLinks() on every cron run.
 */
class XmlSitemapHooks {

  /**
   * The xmlsitemap link type for town + category pages.
   */
  private const LINK_TYPE = 'town_listing_page';

  /**
   * The xmlsitemap link type for town pages.
   */
  private const TOWN_LINK_TYPE = 'town_page';

  /**
   * The xmlsitemap link type for tag pages.
   */
  private const TAG_LINK_TYPE = 'tag_page';

  public function __construct(
    private readonly Connection $database,
    private readonly AliasManagerInterface $aliasManager,
    private readonly XmlSitemapLinkStorageInterface $linkStorage,
  ) {}

  /**
   * Adds, updates and removes the town, tag and town + category links.
   *
   * A town page, or a town + category page, is in the sitemap exactly when
   * it has published content, and a tag page when at least
   * TOWN_LISTINGS_ROUTER_TAG_MIN_ITEMS published items have the tag (the same
   * rules as the noindex checks in the .module). Each is dated by the latest
   * change to that content. XML Sitemap flags the sitemap for regeneration
   * whenever a link is changed or deleted.
   */
  public function syncLinks(): void {
    // Town + category pages, keyed by "town:category".
    $pages = [];
    foreach (town_listings_router_category_types() as $type) {
      foreach ($this->getCombinations($type['bundle'], $type['category_field']) as $combo) {
        $loc = $this->getLoc($combo['town_tid'], $combo['category_tid']);
        if ($loc !== NULL) {
          $pages[$combo['town_tid'] . ':' . $combo['category_tid']] = [
            'loc' => $loc,
            'lastmod' => $combo['lastmod'],
          ];
        }
      }
    }
    $this->syncType(self::LINK_TYPE, $pages);

    // Town pages, keyed by town term ID.
    $towns = [];
    foreach ($this->getTowns() as $town_tid => $lastmod) {
      $alias = $this->aliasManager->getAliasByPath('/taxonomy/term/' . $town_tid);
      if ($alias !== '/taxonomy/term/' . $town_tid) {
        $towns[(string) $town_tid] = ['loc' => $alias, 'lastmod' => $lastmod];
      }
    }
    $this->syncType(self::TOWN_LINK_TYPE, $towns);

    // Tag pages, keyed by tag term ID.
    $tags = [];
    foreach ($this->getTags() as $tag_tid => $lastmod) {
      $alias = $this->aliasManager->getAliasByPath('/taxonomy/term/' . $tag_tid);
      if ($alias !== '/taxonomy/term/' . $tag_tid) {
        $tags[(string) $tag_tid] = ['loc' => $alias, 'lastmod' => $lastmod];
      }
    }
    $this->syncType(self::TAG_LINK_TYPE, $tags);
  }

  /**
   * Implements hook_xmlsitemap_index_links(), for drush xmlsitemap:index.
   *
   * There are few town pages, so this syncs all of them rather than a
   * batch of $limit.
   */
  public function indexLinks(int $limit): void {
    $this->syncLinks();
  }

  /**
   * Makes the sitemap's links of one type match $wanted.
   *
   * @param string $link_type
   *   The xmlsitemap link type.
   * @param array $wanted
   *   The pages that should be in the sitemap, keyed by link ID, each with
   *   'loc' and 'lastmod'.
   */
  private function syncType(string $link_type, array $wanted): void {
    // The pages that are in the sitemap now.
    $existing = [];
    foreach ($this->linkStorage->loadMultiple(['type' => $link_type]) as $link) {
      $existing[$link['id']] = $link;
    }

    // Add new pages and update moved or changed ones.
    foreach ($wanted as $id => $page) {
      $old = $existing[$id] ?? NULL;
      if ($old && $old['loc'] === $page['loc'] && (int) $old['lastmod'] === $page['lastmod']) {
        continue;
      }
      $this->linkStorage->save([
        'type' => $link_type,
        'subtype' => $link_type,
        'id' => (string) $id,
        'loc' => $page['loc'],
        'status' => 1,
        'priority' => 0.5,
        'changefreq' => XMLSITEMAP_FREQUENCY_WEEKLY,
        'language' => 'en',
        'lastmod' => $page['lastmod'],
      ]);
    }

    // Remove pages that no longer qualify.
    foreach (array_keys(array_diff_key($existing, $wanted)) as $id) {
      $this->linkStorage->delete($link_type, (string) $id);
    }
  }

  /**
   * Returns the town + category pairs that have published content.
   *
   * @param string $bundle
   *   The content type, e.g. 'listing'.
   * @param string $category_field
   *   Its category field, e.g. 'field_listing_category'.
   *
   * @return array
   *   Each with 'town_tid', 'category_tid' and 'lastmod' (the latest
   *   change to a published node in that town and category).
   */
  private function getCombinations(string $bundle, string $category_field): array {
    $query = $this->database->select('node__field_canadian_towns', 't');
    $query->join('node__' . $category_field, 'c', 't.entity_id = c.entity_id');
    $query->join('node_field_data', 'n', 't.entity_id = n.nid');
    $query->condition('n.status', 1);
    $query->condition('n.type', $bundle);
    $query->addField('t', 'field_canadian_towns_target_id', 'town_tid');
    $query->addField('c', $category_field . '_target_id', 'category_tid');
    $query->addExpression('MAX(n.changed)', 'lastmod');
    $query->groupBy('t.field_canadian_towns_target_id');
    $query->groupBy('c.' . $category_field . '_target_id');

    $results = [];
    foreach ($query->execute() as $row) {
      $results[] = [
        'town_tid' => (int) $row->town_tid,
        'category_tid' => (int) $row->category_tid,
        'lastmod' => (int) $row->lastmod,
      ];
    }
    return $results;
  }

  /**
   * Returns the towns that have published content.
   *
   * Counts the same content types as town_listings_router_town_has_content().
   *
   * @return int[]
   *   The latest change to a published node in each town, keyed by town
   *   term ID.
   */
  private function getTowns(): array {
    $query = $this->database->select('node__field_canadian_towns', 't');
    $query->join('node_field_data', 'n', 't.entity_id = n.nid');
    $query->condition('n.status', 1);
    $query->condition('n.type', array_column(town_listings_router_category_types(), 'bundle'), 'IN');
    $query->addField('t', 'field_canadian_towns_target_id', 'town_tid');
    $query->addExpression('MAX(n.changed)', 'lastmod');
    $query->groupBy('t.field_canadian_towns_target_id');

    $towns = [];
    foreach ($query->execute() as $row) {
      $towns[(int) $row->town_tid] = (int) $row->lastmod;
    }
    return $towns;
  }

  /**
   * Returns the tags that have enough published items for their own page.
   *
   * Counts from the taxonomy index, as town_listings_router_tag_item_count()
   * does.
   *
   * @return int[]
   *   The latest change to a published node with each tag, keyed by tag
   *   term ID.
   */
  private function getTags(): array {
    $query = $this->database->select('taxonomy_index', 'ti');
    $query->join('taxonomy_term_field_data', 't', 'ti.tid = t.tid');
    $query->join('node_field_data', 'n', 'ti.nid = n.nid');
    $query->condition('t.vid', 'tags');
    $query->condition('n.status', 1);
    $query->addField('ti', 'tid');
    $query->addExpression('MAX(n.changed)', 'lastmod');
    $query->groupBy('ti.tid');
    $query->having('COUNT(DISTINCT ti.nid) >= :min', [':min' => \TOWN_LISTINGS_ROUTER_TAG_MIN_ITEMS]);

    $tags = [];
    foreach ($query->execute() as $row) {
      $tags[(int) $row->tid] = (int) $row->lastmod;
    }
    return $tags;
  }

  /**
   * Returns the address of a town + category page, e.g.
   * /ontario/cambridge/landmarks-structures/dams, or NULL when the town or
   * the category has no alias.
   */
  private function getLoc(int $town_tid, int $category_tid): ?string {
    $town_alias = $this->aliasManager->getAliasByPath('/taxonomy/term/' . $town_tid);
    $category_alias = $this->aliasManager->getAliasByPath('/taxonomy/term/' . $category_tid);
    if ($town_alias === '/taxonomy/term/' . $town_tid || $category_alias === '/taxonomy/term/' . $category_tid) {
      return NULL;
    }
    return rtrim($town_alias, '/') . $category_alias;
  }

}
