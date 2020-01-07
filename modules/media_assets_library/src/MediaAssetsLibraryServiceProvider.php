<?php

namespace Drupal\media_assets_library;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Service alter.
 */
class MediaAssetsLibraryServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Change basic auth class to our custom one.
    if ($container->hasDefinition('basic_auth.authentication.basic_auth')) {
      $container->getDefinition('basic_auth.authentication.basic_auth')
        ->setClass(BasicAuthWithExclude::class);
    }
  }

}