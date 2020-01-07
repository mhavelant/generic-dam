<?php

namespace Drupal\media_assets_library\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Session\AccountInterface;
use Drupal\image\Entity\ImageStyle;
use function strpos;

/**
 * Field definition to provide relative image style url for file entities.
 *
 * For 'image/*' filemime return relative image style url of the uri.
 */
class ImageStyleDownloadUrl extends FieldItemList {

  public const IMAGE_STYLE = 'medium';

  /**
   * Creates a relative thumbnail image style URL from file's URI.
   *
   * @param string $uri
   *   The URI to transform.
   *
   * @return string
   *   The transformed relative URL.
   */
  protected function fileCreateThumbnailUrl($uri): string {
    /** @var \Drupal\image\ImageStyleInterface $style */
    $style = ImageStyle::load(self::IMAGE_STYLE);
    $url = $style->buildUrl($uri);
    return file_url_transform_relative(file_create_url($url));
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    $this->initList();
    return parent::getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function access($operation = 'view', AccountInterface $account = NULL, $return_as_object = FALSE) {
    return $this->getEntity()
      ->get('uri')
      ->access($operation, $account, $return_as_object);
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    return $this->getEntity()->get('uri')->isEmpty();
  }

  /**
   * {@inheritdoc}
   */
  public function getIterator() {
    $this->initList();

    return parent::getIterator();
  }

  /**
   * {@inheritdoc}
   */
  public function get($index) {
    $this->initList();

    return parent::get($index);
  }

  /**
   * Initialize the internal field list with the modified items.
   */
  protected function initList() {
    if ($this->list) {
      return;
    }
    $url_list = [];
    foreach ($this->getEntity()->get('uri') as $delta => $uri_item) {
      if (FALSE !== strpos($this->getEntity()->get('filemime')[$delta]->value, 'image')) {
        $path = $this->fileCreateThumbnailUrl($uri_item->value);
        $url_list[$delta] = $this->createItem($delta, $path);
      }
    }
    $this->list = $url_list;
  }

}
