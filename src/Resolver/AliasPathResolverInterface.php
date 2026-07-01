<?php

namespace Drupal\town_listings_router\Resolver;

/**
 * Resolves a path alias to its underlying system path.
 */
interface AliasPathResolverInterface {

  /**
   * Returns the system path for a given alias.
   *
   * @param string $alias
   *   The path alias, e.g. '/british-columbia/gabriola'.
   * @param string|null $langcode
   *   Optional language code.
   *
   * @return string
   *   The system path, e.g. '/taxonomy/term/147908', or the original
   *   alias unchanged if no match was found.
   */
  public function getSystemPath(string $alias, ?string $langcode = NULL): string;

}
