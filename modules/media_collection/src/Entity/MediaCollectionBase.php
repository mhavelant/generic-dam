<?php

namespace Drupal\media_collection\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\EntityOwnerTrait;

/**
 * Class MediaCollectionBase.
 *
 * @package Drupal\media_collection\Entity
 */
abstract class MediaCollectionBase extends ContentEntityBase implements MediaCollectionInterface {

  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * Returns the "items" field.
   *
   * @return \Drupal\Core\Field\EntityReferenceFieldItemListInterface
   *   The "items" field.
   */
  private function itemsField(): EntityReferenceFieldItemListInterface {
    return $this->get($this->getEntityType()->getKey('items'));
  }

  /**
   * {@inheritdoc}
   */
  public function items(): array {
    $items = [];

    foreach ($this->itemsField() as $field) {
      /** @var \Drupal\media_collection\Entity\MediaCollectionItemInterface|null $item */
      if ($item = $field->entity) {
        $items[] = $item;
      }
    }

    return $items;
  }

  /**
   * {@inheritdoc}
   */
  public function itemCount(): int {
    return $this->itemsField()->count();
  }

  /**
   * {@inheritdoc}
   */
  public function hasItem(MediaCollectionItemInterface $item): bool {
    return $this->itemIndex($item) !== NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function itemIndex(MediaCollectionItemInterface $item): ?int {
    // @todo: Maybe there's a more elegant solution, maybe $this->items()->filter($callback).
    // @todo: Maybe a deeper check is needed (media + style).
    $indexes = array_keys($this->itemsField()->getValue() ?? [], ['target_id' => $item->id()]);
    // We shouldn't return multiple values as e.g. remove re-indexes the items.
    $index = reset($indexes);
    return $index === FALSE ? NULL : $index;
  }

  /**
   * {@inheritdoc}
   */
  public function setItems(array $items): MediaCollectionInterface {
    $this->itemsField()->setValue($items);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addItem(MediaCollectionItemInterface $item): MediaCollectionInterface {
    $this->itemsField()->appendItem($item);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeItem(MediaCollectionItemInterface $item): MediaCollectionInterface {
    if ($this->itemCount() <= 0) {
      return $this;
    }

    // Protect against the edge-case where the same item is
    // added multiple times. Remove re-indexes the items array, so we have
    // to get indexes one-by-one.
    $index = $this->itemIndex($item);
    while ($index !== NULL) {
      $this->itemsField()->removeItem($index);
      $index = $this->itemIndex($item);
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entityType) {
    $fields = parent::baseFieldDefinitions($entityType);
    $fields += static::ownerBaseFieldDefinitions($entityType);

    $fields[$entityType->getKey('owner')]
      ->setLabel(new TranslatableMarkup('User'))
      ->setSetting('handler', 'default')
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Created'))
      ->setDescription(new TranslatableMarkup('The time that the entity was created.'))
      ->setRevisionable(TRUE);

    $fields[$entityType->getKey('items')] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Items'))
      ->setDescription(new TranslatableMarkup('Items that belong to this collection.'))
      ->setSetting('target_type', 'media_collection_item')
      ->setSetting('handler', 'default')
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setCardinality(128)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
