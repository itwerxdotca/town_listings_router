<?php

namespace Drupal\town_listings_router\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Prevents the Redirect module from normalizing our rewritten routes.
 *
 * When TownListingsPathProcessor rewrites a combined town+category alias
 * to /town-listings-router/{town_tid}/{category_tid}, the Redirect module's
 * RouteNormalizerRequestSubscriber would otherwise detect that the request
 * path differs from the canonical alias of the resolved route and issue a
 * 301 redirect back to /british-columbia/gabriola (the town-only alias).
 *
 * Setting _disable_route_normalizer on the request attributes prevents this,
 * as long as this subscriber runs with a higher priority (earlier) than
 * the Redirect module's subscriber on the kernel.request event.
 */
final class PreventRedirectSubscriber implements EventSubscriberInterface {

  /**
   * The internal path prefix used by our path processor's rewritten routes.
   */
  private const REWRITTEN_PATH_PREFIX = '/town-listings-router/';

  /**
   * Marks rewritten requests to skip route normalization / redirects.
   */
  public function onKernelRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }

    $request = $event->getRequest();

    if (str_starts_with($request->getPathInfo(), self::REWRITTEN_PATH_PREFIX)) {
      $request->attributes->set('_disable_route_normalizer', TRUE);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Priority must be higher than the Redirect module's
    // RouteNormalizerRequestSubscriber (default priority 28) so this runs
    // first and sets the flag before it's checked.
    return [
      KernelEvents::REQUEST => ['onKernelRequest', 300],
    ];
  }

}
