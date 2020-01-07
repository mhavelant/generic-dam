<?php

namespace Drupal\media_assets_library\Normalizer;

use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\media\MediaInterface;
use Drupal\serialization\Normalizer\ContentEntityNormalizer;
use Drupal\media_assets\Render\AssetPreviewListMarkup;
use function count;
use function in_array;

/**
 * Converts the Drupal entity object structures to a normalized array.
 */
class MediaEntityNormalizer extends ContentEntityNormalizer {

  /**
   * File storage.
   *
   * @var \Drupal\file\FileStorageInterface
   */
  private $fileStorage;

  /**
   * Image style storage.
   *
   * @var \Drupal\image\ImageStyleStorageInterface
   */
  private $imageStyleStorage;

  /**
   * Taxonomy term storage.
   *
   * @var \Drupal\taxonomy\TermStorageInterface
   */
  private $termStorage;

  /**
   * Array of image styles.
   *
   * @var \Drupal\image\ImageStyleInterface[]
   */
  private $imageStyleList;

  /**
   * MediaEntityNormalizer constructor.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   Entity manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityManagerInterface $entity_manager) {
    parent::__construct($entity_manager);

    // @todo Use $this->entityTypeManager only with Drupal 8.7.
    $entityTypeManager = $this->entityTypeManager ?? $this->entityManager;

    $this->fileStorage = $entityTypeManager->getStorage('file');
    $this->imageStyleStorage = $entityTypeManager->getStorage('image_style');
    $this->termStorage = $entityTypeManager->getStorage('taxonomy_term');

    $this->supportedInterfaceOrClass = MediaInterface::class;
    $this->imageStyleList = (new AssetPreviewListMarkup())->getImageStyleList();
  }

  /**
   * The white listed field list.
   *
   * @var array
   */
  private const FIELD_WHITELIST = [
    'mid',
    'name',
    'thumbnail',
    'field_category',
    'field_keywords',
  ];

  /**
   * {@inheritdoc}
   */
  public function normalize($entity, $format = NULL, array $context = []) {
    $attributes = [];
    /** @var \Drupal\Core\Field\FieldItemListInterface $field */
    foreach ($entity as $name => $field) {
      if (!$field->access('view', $context['account'] ?? NULL)) {
        continue;
      }

      if (!in_array($field->getName(), static::FIELD_WHITELIST, FALSE)) {
        continue;
      }

      if ($name === 'thumbnail') {
        $attributes[$name] = $this->normalizeThumbnailFieldItemList($field);
        /** @var \Drupal\file\FileInterface $file */
        $file = $this->fileStorage->load($field->first()->getEntity()->get('thumbnail')->target_id);

        foreach ($this->imageStyleList as $key => $style) {
          $attributes['assets'][$key] = $style->buildUrl($file->getFileUri());
        }
      }
      elseif (in_array($name, ['field_category', 'field_keywords'])) {
        $attributes[$name] = $this->normalizeTaxonomyFieldItemList($field);
      }
      else {
        if ($name === 'mid') {
          $name = 'media_id';
        }
        $attributes[$name] = $this->serializer->normalize($field, $format, $context);
      }

      // Override/exclude normalized data.
      if (isset($attributes[$name][0]['value']) && count($attributes[$name][0]) === 1) {
        $attributes[$name] = $attributes[$name][0]['value'];
      }
    }

    return $attributes;
  }

  /**
   * Custom normalization.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   Field items.
   *
   * @return array
   *   Normalized field.
   */
  private function normalizeThumbnailFieldItemList(FieldItemListInterface $items): array {
    $normalized = [];

    foreach ($items as $item) {
      /** @var \Drupal\file\FileInterface $file */
      $file = $this->fileStorage->load($item->getEntity()->get('thumbnail')->target_id);
      if ($file) {
        /** @var \Drupal\image\ImageStyleInterface $thumbnail */
        $thumbnail = $this->imageStyleStorage->load('thumbnail');
        $normalized['alt'] = $item->alt;
        $normalized['title'] = $item->title;
        $normalized['url'] = $thumbnail->buildUrl($file->getFileUri());
      }
    }

    return $normalized;
  }

  /**
   * Custom normalization.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   Field items.
   *
   * @return array
   *   Normalized field.
   */
  private function normalizeTaxonomyFieldItemList(FieldItemListInterface $items): array {
    $normalized = [];

    foreach ($items as $key => $item) {
      /** @var \Drupal\taxonomy\TermInterface $term */
      $term = $this->termStorage->load($item->getValue()['target_id']);
      if ($term) {
        $normalized[$key] = [$term->getName()];
      }
    }

    return $normalized;
  }

}