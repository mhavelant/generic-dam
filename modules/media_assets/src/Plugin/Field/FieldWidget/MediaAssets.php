<?php

namespace Drupal\media_assets\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'media_assets' widget.
 *
 * @FieldWidget(
 *   id = "media_assets",
 *   label = @Translation("Media assets"),
 *   field_types = {
 *     "media_assets"
 *   }
 * )
 */
class MediaAssets extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element['value'] = $element;

    return $element;
  }

}
