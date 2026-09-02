<?php
namespace Drupal\town_listings_router\Hooks;

use Drupal\Core\Database\Connection;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\xmlsitemap\XmlSitemapLinkStorageInterface;

class XmlSitemapHooks {

  public function __construct(
	private readonly Connection $database,
	private readonly AliasManagerInterface $aliasManager,
	private readonly XmlSitemapLinkStorageInterface $linkStorage,
  ) {}

  public function indexLinks(int $limit): void {
	$combinations = $this->getCombinations($limit, 0);
	foreach ($combinations as $combo) {
	  $this->saveLink($combo);
	}
  }

  private function countCombinations(): int {
	$query = $this->database->select('node__field_canadian_towns', 't');
	$query->join('node__field_listing_category', 'c', 't.entity_id = c.entity_id');
	$query->join('node_field_data', 'n', 't.entity_id = n.nid');
	$query->condition('n.status', 1);
	$query->condition('n.type', 'listing');
	$query->fields('t', ['field_canadian_towns_target_id']);
	$query->fields('c', ['field_listing_category_target_id']);
	$query->distinct();
	return (int) $query->countQuery()->execute()->fetchField();
  }

  private function getCombinations(int $limit, int $offset): array {
	$query = $this->database->select('node__field_canadian_towns', 't');
	$query->join('node__field_listing_category', 'c', 't.entity_id = c.entity_id');
	$query->join('node_field_data', 'n', 't.entity_id = n.nid');
	$query->condition('n.status', 1);
	$query->condition('n.type', 'listing');
	$query->fields('t', ['field_canadian_towns_target_id']);
	$query->fields('c', ['field_listing_category_target_id']);
	$query->distinct();
	$query->range($offset, $limit);
	$results = [];
	foreach ($query->execute() as $row) {
	  $results[] = [
		'town_tid' => (int) $row->field_canadian_towns_target_id,
		'category_tid' => (int) $row->field_listing_category_target_id,
	  ];
	}
	return $results;
  }

  private function saveLink(array $combo): void {
	$town_alias = $this->aliasManager->getAliasByPath('/taxonomy/term/' . $combo['town_tid']);
	$category_alias = $this->aliasManager->getAliasByPath('/taxonomy/term/' . $combo['category_tid']);
	if ($town_alias === '/taxonomy/term/' . $combo['town_tid'] ||
		$category_alias === '/taxonomy/term/' . $combo['category_tid']) {
	  return;
	}
	$loc = rtrim($town_alias, '/') . $category_alias;
	$this->linkStorage->save([
	  'type' => 'town_listing_page',
	  'subtype' => 'town_listing_page',
	  'id' => $combo['town_tid'] . ':' . $combo['category_tid'],
	  'loc' => $loc,
	  'status' => 1,
	  'priority' => 0.5,
	  'changefreq' => XMLSITEMAP_FREQUENCY_WEEKLY,
	  'language' => 'en',
	  'lastmod' => \Drupal::time()->getRequestTime(),
	]);
  }

}