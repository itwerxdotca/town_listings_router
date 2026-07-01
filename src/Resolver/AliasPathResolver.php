<?php

namespace Drupal\town_listings_router\Resolver;

use Drupal\path_alias\AliasManagerInterface;

/**
 * Resolves path aliases to system paths.
 *
 * Isolates the use of the path_alias.manager service, which is deprecated
 * in drupal:11.1.0 in favour of 'router.prefix_list' (see
 * https://www.drupal.org/node/3467559). As of 11.3 there is no drop-in
 * replacement for getPathByAlias(); this wrapper means that when core
 * provides one, only this class needs to change.
 */
final class AliasPathResolver implements AliasPathResolverInterface {

  public function __construct(
    private readonly AliasManagerInterface $aliasManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getSystemPath(string $alias, ?string $langcode = NULL): string {
    return $this->aliasManager->getPathByAlias($alias, $langcode);
  }

}
