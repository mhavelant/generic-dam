<?php

namespace Drupal\media_assets\Render;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\media\MediaInterface;

/**
 * Class MediaDownloadButton.
 *
 * @package Drupal\media_assets\Render
 */
class MediaDownloadButton {

  /**
   * Returns the render array for the button.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media.
   *
   * @return array
   *   The render array.
   */
  public static function build(MediaInterface $media): array {
    return [
      '#type' => 'link',
      '#title' => new TranslatableMarkup('Download'),
      '#url' => Url::fromRoute(
        'media_assets.asset_download',
        [
          'media' => $media->id(),
        ]
      ),
      '#attributes' => [
        'download' => '',
        'class' => [
          'button',
          'button--green',
          'download-btn',
        ],
      ],
      '#weight' => 0,
    ];
  }

}
