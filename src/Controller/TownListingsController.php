<?php

namespace Drupal\town_listings_router\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Renders the listings View filtered by town and listing category term IDs.
 */
final class TownListingsController extends ControllerBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $termStorage,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Renders the listings page for a town + category combination.
   *
   * @param int $town_tid
   *   The canadian_towns term ID.
   * @param int $category_tid
   *   The listing_category term ID.
   *
   * @return array
   *   A render array.
   */
  public function page(int $town_tid, int $category_tid): array {
    // page_2 argument order:
    //   1. field_canadian_towns_target_id
    //   2. field_listing_category_target_id
    $view = views_embed_view(
      'listings',
      'page_2',
      (string) $town_tid,
      (string) $category_tid,
    );

    if (empty($view)) {
      throw new NotFoundHttpException();
    }

    return $view;
  }

  /**
   * Returns the page title based on the town and category terms.
   *
   * @param int $town_tid
   *   The canadian_towns term ID.
   * @param int $category_tid
   *   The listing_category term ID.
   *
   * @return string
   *   The page title.
   */
   public function title(int $town_tid, int $category_tid): string {
     $storage = $this->termStorage->getStorage('taxonomy_term');
     $town = $storage->load($town_tid);
     $category = $storage->load($category_tid);

     if ($town && $category) {
       return $category->label() . ' in ' . $town->label();
     }

     return (string) $this->t('Listings');
   }
}
