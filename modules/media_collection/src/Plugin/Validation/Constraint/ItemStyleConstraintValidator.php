<?php

namespace Drupal\media_collection\Plugin\Validation\Constraint;

use function array_keys;
use Drupal\media_assets\Render\AssetPreviewListMarkup;
use function in_array;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the ItemStyleConstraint constraint.
 */
class ItemStyleConstraintValidator extends ConstraintValidator {

  /**
   * Return the allowed style IDs.
   *
   * @return array
   *   List of allowed style IDs.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function allowedStyles(): array {
    return array_keys((new AssetPreviewListMarkup())->getImageStyleList());
  }

  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint) {
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $items */
    /** @var \Drupal\media_collection\Entity\MediaCollectionInterface $parent */
    $collectionItem = $items->getEntity();
    /** @var \Drupal\media\MediaInterface $media */
    $media = $collectionItem->get('media')->entity;
    $mediaType = $media === NULL ? NULL : $media->bundle();

    if ($mediaType !== NULL && $mediaType !== 'image' && !$items->isEmpty()) {
      $this->context->addViolation($constraint->isNotAnImage, ['%type' => $mediaType]);
      return;
    }

    foreach ($items as $item) {
      /** @var \Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem $item */
      if (!$item->isEmpty() && !in_array($item->target_id, $this->allowedStyles(), FALSE)) {
        $this->context->addViolation($constraint->isInvalid, ['%value' => $item->target_id]);
      }
    }
  }

}
