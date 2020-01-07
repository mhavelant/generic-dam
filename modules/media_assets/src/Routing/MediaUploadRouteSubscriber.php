<?php

namespace Drupal\media_assets\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\media_assets\Form\BulkMediaUploadForm;
use Symfony\Component\Routing\RouteCollection;

/**
 * Subscribe to media bulk upload paths.
 *
 * @package Drupal\media_assets\Routing
 */
class MediaUploadRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('media_upload.bulk_media_upload')) {
      $route->setDefault('_form', BulkMediaUploadForm::class);
    }
  }

}
