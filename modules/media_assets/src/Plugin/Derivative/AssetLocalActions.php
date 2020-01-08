<?php

namespace Drupal\media_assets\Plugin\Derivative;

use Drupal;
use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use function strtolower;

/**
 * Class AssetLocalActions.
 *
 * @package Drupal\media_assets\Plugin\Derivative
 */
class AssetLocalActions extends DeriverBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {

    /** @var \Drupal\media\MediaTypeInterface $definition */
    foreach ($this->getMediaTypes() as $typeId => $definition) {
      $actionId = "add.$typeId";

      $this->derivatives[$actionId] = $base_plugin_definition;
      $this->derivatives[$actionId]['parent_id'] = "media_assets.asset_local_actions:$actionId";
      $this->derivatives[$actionId]['title'] = $this->t('Add @type', [
        '@type' => strtolower($definition->label()),
      ]);
      $this->derivatives[$actionId]['route_name'] = 'entity.media.add_form';
      $this->derivatives[$actionId]['route_parameters']['media_bundle'] = $typeId;
      $this->derivatives[$actionId]['weight'] = 0;
      $this->derivatives[$actionId]['appears_on'] = [
        'view.asset_search.asset_search',
      ];

      $baseClass = 'media-asset-action';
      $typeClass = $baseClass . "-$typeId";

      if (!isset($this->derivatives[$actionId]['options']['attributes']['class'])) {
        $this->derivatives[$actionId]['options']['attributes']['class'] = [
          $baseClass,
          $typeClass,
        ];
      }
      else {
        $this->derivatives[$actionId]['options']['attributes']['class'][] = $baseClass;
        $this->derivatives[$actionId]['options']['attributes']['class'][] = $typeClass;
      }
    }

    return $this->derivatives;
  }

  /**
   * Get the media bundles.
   *
   * @return array
   *   The bundles keyed by bundle ID.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getMediaTypes() {
    $storage = Drupal::entityTypeManager()->getStorage('media_type');
    return $storage->loadMultiple();
  }

}
