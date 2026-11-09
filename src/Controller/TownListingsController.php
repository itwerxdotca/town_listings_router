<?php

namespace Drupal\town_listings_router\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Renders a town + category page: the view for the category's content type
 * (see town_listings_router_category_types()), filtered by town and category.
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
   * Renders the page for a town + category combination.
   *
   * @param int $town_tid
   *   The canadian_towns term ID.
   * @param int $category_tid
   *   The listing_category or poi_category term ID.
   *
   * @return array
   *   A render array.
   */
  public function page(int $town_tid, int $category_tid): array {
    // listings for listing categories, points_of_interest for POI
    // categories. page_2 argument order in both views:
    //   1. field_canadian_towns_target_id
    //   2. the category field's target_id
    $type = town_listings_router_category_type($category_tid);
    if (!$type) {
      throw new NotFoundHttpException();
    }
    $view = views_embed_view(
      $type['view'],
      'page_2',
      (string) $town_tid,
      (string) $category_tid,
    );

    if (empty($view)) {
      throw new NotFoundHttpException();
    }

    return [
      // Main heading, e.g. "Bars & Pubs in Fort McMurray". Uses the same
      // title() as the browser tab, so the two always match.
      'title' => [
        '#prefix' => '<h1 class="tc-page-title">',
        '#plain_text' => $this->title($town_tid, $category_tid),
        '#suffix' => '</h1>',
        '#cache' => [
          'tags' => ['taxonomy_term:' . $town_tid, 'taxonomy_term:' . $category_tid],
        ],
      ],
      'view' => $view,
    ];
  }

  /**
   * Returns the page title based on the town and category terms.
   *
   * @param int $town_tid
   *   The canadian_towns term ID.
   * @param int $category_tid
   *   The listing_category or poi_category term ID.
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
