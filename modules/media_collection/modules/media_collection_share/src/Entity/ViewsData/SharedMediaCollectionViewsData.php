<?php

namespace Drupal\media_collection_share\Entity\ViewsData;

use Drupal\views\EntityViewsData;

/**
 * Provides Views data for Media collection (shared) entities.
 */
class SharedMediaCollectionViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    // Additional information for Views integration, such as table joins, can be
    // put here.
    return $data;
  }

}
