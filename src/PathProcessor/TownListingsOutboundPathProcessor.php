<?php
namespace Drupal\town_listings_router\PathProcessor;

use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Converts /town-listings-router/{town_tid}/{category_tid} back to its alias.
 */
final class TownListingsOutboundPathProcessor implements OutboundPathProcessorInterface {

  public function __construct(
	private readonly EntityTypeManagerInterface $entityTypeManager,
	private readonly AliasManagerInterface $aliasManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function processOutbound($path, &$options = [], ?Request $request = NULL, ?BubbleableMetadata $bubbleable_metadata = NULL): string {
	if (!preg_match('#^/town-listings-router/(\d+)/(\d+)$#', $path, $matches)) {
	  return $path;
	}

	$town_tid = (int) $matches[1];
	$category_tid = (int) $matches[2];

	$town_alias = $this->aliasManager->getAliasByPath('/taxonomy/term/' . $town_tid);
	$category_alias = $this->aliasManager->getAliasByPath('/taxonomy/term/' . $category_tid);

	// If either alias is just the system path, bail out.
	if ($town_alias === '/taxonomy/term/' . $town_tid || $category_alias === '/taxonomy/term/' . $category_tid) {
	  return $path;
	}

	// Strip leading slash from town alias and combine with category alias.
	// e.g. /quebec/lacolle + /food-beverage/cafe-bakery
	return rtrim($town_alias, '/') . $category_alias;
  }

}