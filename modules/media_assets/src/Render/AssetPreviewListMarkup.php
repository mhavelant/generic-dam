<?php

namespace Drupal\media_assets\Render;

use Drupal;
use Drupal\Core\Link;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\image\Plugin\Field\FieldType\ImageItem;
use Drupal\media\MediaInterface;
use Drupal\media_assets\Form\MediaAssetFilterForm;
use InvalidArgumentException;
use function array_shift;
use function array_values;
use function drupal_get_path;
use function explode;
use function file_create_url;
use function file_exists;
use function file_get_contents;
use function getimagesize;
use function in_array;
use function is_array;
use function render;
use function str_replace;
use function strpos;
use function strtolower;

/**
 * Class AssetPreviewListMarkup.
 *
 * @package Drupal\media_assets\Render
 */
class AssetPreviewListMarkup {

  use MessengerTrait;
  use StringTranslationTrait;

  /**
   * Form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Image factory.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected $imageFactory;

  /**
   * Focal point manager.
   *
   * @var \Drupal\focal_point\FocalPointManagerInterface
   */
  protected $focalPointManager;

  /**
   * The current collection, if it exists.
   *
   * @var \Drupal\media_collection\Entity\MediaCollectionInterface|null
   */
  protected $currentCollection;

  /**
   * The collection handler, if it exists.
   *
   * @var \Drupal\media_collection\Service\CollectionHandler|null
   */
  protected $collectionHandler;

  /**
   * Render array for the "Added to collection" icon.
   *
   * @var array
   */
  protected $itemInCollectionIcon;

  /**
   * Render array for the "Add to collection" icon.
   *
   * @var array
   */
  protected $addToCollectionIcon;

  /**
   * AssetPreviewListMarkup constructor.
   */
  public function __construct() {
    // @todo: Proper Dep.Inj.
    $this->formBuilder = Drupal::formBuilder();
    $this->entityTypeManager = Drupal::entityTypeManager();
    $this->imageFactory = Drupal::service('image.factory');
    $this->focalPointManager = Drupal::service('focal_point.manager');

    if (Drupal::moduleHandler()->moduleExists('media_collection')) {
      /** @var \Drupal\media_collection\Service\CollectionHandler $handler */
      $this->collectionHandler = Drupal::service('media_collection.collection_handler');
      $this->currentCollection = $this->collectionHandler->loadCollectionForUser(Drupal::currentUser()->id());

      $modulePath = drupal_get_path('module', 'media_collection');
      $this->itemInCollectionIcon = [
        '#type' => 'html_tag',
        '#tag' => 'img',
        '#attributes' => [
          'src' => Url::fromUri(file_create_url("{$modulePath}/assets/added-to-collection.png"))->getUri(),
          'class' => [
            'icon--item-in-collection',
          ],
        ],
      ];
      $this->addToCollectionIcon = [
        '#type' => 'html_tag',
        '#tag' => 'img',
        '#attributes' => [
          'src' => Url::fromUri(file_create_url("{$modulePath}/assets/plus-icon.svg"))->getUri(),
          'class' => [
            'plus',
          ],
        ],
      ];
    }
  }

  /**
   * Return the table render array.
   *
   * @param \Drupal\media\MediaInterface $media
   *   Media entity.
   *
   * @return array
   *   The render array.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function render(MediaInterface $media): array {
    /** @var \Drupal\media\MediaInterface $media */
    $fieldName = $this->getFieldName($media->bundle());
    /** @var \Drupal\image\Plugin\Field\FieldType\ImageItem $image */
    $image = $media->{$fieldName}->first();
    /** @var \Drupal\file\Entity\File $file */
    $file = $this->entityTypeManager->getStorage('file')->load($image->target_id);

    if (NULL === $file) {
      $this->messenger->addMessage('The image "' . $image->getName() . '" is not found.', 'error');
      return [];
    }

    try {
      $derivativeImages = $this->getTableRows(
        $this->getImageUri($file),
        $this->getImageStyleList(),
        $this->getFocalPointValue($file, $image),
        $media
      );
    }
    catch (InvalidArgumentException $exception) {
      $this->messenger->addMessage($exception->getMessage(), 'error');
      return [];
    }

    $form = $this->formBuilder->getForm(MediaAssetFilterForm::class);

    $build = [
      '#prefix' => render($form),
      '#theme' => 'media_display_page',
      '#rows' => $derivativeImages,
      '#title' => $media->getName(),
      '#caption' => $this->t('Social media versions of the selected asset'),
      '#attributes' => ['class' => ['social-media-assets']],
      '#attached' => [
        'library' => [
          'media_assets/lister',
        ],
      ],
      '#metadata' => [],
    ];

    // @todo: Debug why this is not added.
    if ($this->itemInCollectionIcon) {
      $build['#metadata']['media_collection']['added_to_collection_icon'] = $this->itemInCollectionIcon;
      $build['#metadata']['media_collection']['remove_from_collection_text'] = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $this->t('(Remove from your collection)'),
        '#attributes' => [
          'class' => 'button--remove-style-from-collection',
        ],
      ];
    }

    return $build;
  }

  /**
   * Returns the name of the field which contains the media file.
   *
   * @param string $type
   *   Entity bundle in which we search for the field.
   *
   * @return string
   *   The field name.
   */
  protected function getFieldName($type): string {
    return 'video' === $type
      ? 'thumbnail'
      : 'field_image';
  }

  /**
   * Generate derivative images and build table rows.
   *
   * @param string $imageUri
   *   The image source url.
   * @param array $styles
   *   Image styles defined in Drupal core.
   * @param string $focalPoint
   *   The coordinates for the focal point.
   * @param \Drupal\media\MediaInterface $media
   *   The media entity.
   *
   * @return array
   *   Generated images.
   *
   * @throws \UnexpectedValueException
   * @throws \InvalidArgumentException
   */
  protected function getTableRows($imageUri, array $styles, $focalPoint, MediaInterface $media): array {
    // Count platform types.
    $platforms = [];
    foreach ($styles as $key => $style) {
      $tmp = explode('_', $key);
      $platform = array_shift($tmp);
      if (isset($platforms[$platform])) {
        $platforms[$platform]++;
      }
      else {
        $platforms[$platform] = 1;
      }
    }

    $modulePath = drupal_get_path('module', 'media_assets');
    $rows = [];
    $rowNumber = 0;
    $controller = [];
    /** @var \Drupal\image\Entity\ImageStyle $style */
    foreach ($styles as $style) {
      $styleLabel = $style->label();

      // @todo: One style = One effect, but what if someone adds another?
      $styleData = [];

      if ($styleConfig = $style->getEffects()->getConfiguration()) {
        $styleData = array_values($styleConfig)[0]['data'];
      }

      // @todo: Why the label??
      $hasBadge = strpos($styleLabel, '(no badge)') ? FALSE : TRUE;

      if (empty($styleData['width']) || empty($styleData['height'])) {
        $imageSize = getimagesize($imageUri);
        $styleData['width'] = $imageSize[0];
        $styleData['height'] = $imageSize[1];
      }

      // Create URL for the thumbnails and buttons.
      $styleUrl = $style->buildUrl($imageUri);
      $url = Url::fromUri($styleUrl, [
        'query' => ['focal_point_preview_value' => $focalPoint],
        'attributes' => [
          'class' => ['button', 'button--green'],
          'target' => '_blank',
          'rel' => 'noopener',
          'download' => '',
        ],
      ]);

      // Create thumbnail image element.
      $thumbnail = [
        '#theme' => 'image',
        '#uri' => $url->getUri(),
        '#height' => 100,
        '#alt' => $this->t('Media asset preview for %label', ['%label' => $styleLabel]),
      ];

      // Column 2: Image.
      $rows['images'][$rowNumber]['image'] = [
        'data' => [
          '#theme' => 'media_column_image',
          '#thumbnail' => $thumbnail,
        ],
        'class' => ['media-thumbnail'],
      ];
      $identifier = str_replace(
        ['(', ')', ',', ' '],
        ['', '', '', '-'],
        $styleLabel
      );
      $identifier = strtolower($identifier);

      // @todo: This is a hack.
      if (in_array($identifier, ['original-ratio', 'powerpoint'], TRUE)) {
        $identifier .= '-no-badge';
      }

      // Column 3: Metadata.
      $rows['images'][$rowNumber]['metadata'] = [
        'class' => [
          'media-meta',
        ],
        'data' => [
          '#theme' => 'media_column_metadata',
          'badge' => $hasBadge,
          'identifier' => $identifier,
          '#style' => [
            'label' => $styleLabel,
            'width' => $styleData['width'],
            'height' => $styleData['height'],
          ],
        ],
      ];
      $group = explode('-', $identifier)[0];
      $controller[$group][$rowNumber] = [
        'label' => $styleLabel,
        'badge' => $hasBadge,
        'identifier' => $identifier,
        'style' => $styleLabel,
        'download_link' => Link::fromTextAndUrl(t('Download'), $url),
      ];

      // This is equivalent to a "media_collection is installed" condition.
      if ($this->collectionHandler !== NULL) {
        $collectionLink = [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#attributes' => [
            'title' => $this->t('Add to collection'),
            'data-media-uuid' => $media->uuid(),
            'data-media-type' => $media->bundle(),
            'data-style-uuid' => $style->uuid(),
            'class' => [
              'button',
              'button--gray',
              'button--add-to-collection',
            ],
          ],
          '0' => $this->addToCollectionIcon,
          '1' => [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#value' => $this->t('Add to collection'),
            '#attributes' => [
              'class' => [
                'add-to-collection-text',
              ],
            ],
          ],
        ];

        /** @var \Drupal\media_collection\Entity\MediaCollectionItemInterface $collectionItem */
        if (
          $this->currentCollection !== NULL
          && ($collectionItem = $this->collectionHandler->itemWithGivenEntities($this->currentCollection, $media, $style))
        ) {
          $controller[$group][$rowNumber]['media_collection']['in_collection'] = TRUE;
          $collectionLink['#attributes']['data-collection-item-uuid'] = $collectionItem->uuid();
          $collectionLink['#attributes']['class'][] = 'style-in-collection';
        }

        // @todo: Add "Remove from collection" link?
        $controller[$group][$rowNumber]['media_collection']['add_to_collection_link'] = $collectionLink;
      }

      $svg_path = "{$modulePath}/images/social/social-{$group}.svg";

      if (file_exists($svg_path)) {
        $svg = file_get_contents($svg_path);
        $controller[$group]['icon_path'] = $svg;
      }

      $rowNumber++;
    }

    foreach ($controller as $group => $value) {
      $has_badge = 0;
      $no_badge = 0;
      $single = 0;
      $classes = '';

      foreach ($value as $link) {
        if (is_array($link) && isset($link['badge'])) {
          if (!$link['badge']) {
            $has_badge++;
          }
          else {
            $no_badge++;
          }
          $single++;
        }
      }
      if ($no_badge > 0 && $has_badge === 0) {
        $classes .= 'no-badge';
      }
      if ($single <= 2) {
        $classes .= ' single';
      }
      $controller[$group]['classes'] = $classes;
    }
    $rows['controllers'] = $controller;
    return $rows;
  }

  /**
   * Gets the URI of an image file.
   *
   * @param \Drupal\file\Entity\File $file
   *   The image file.
   *
   * @return null|string
   *   The image source.
   *
   * @throws \InvalidArgumentException
   */
  protected function getImageUri(File $file): ?string {
    /** @var \Drupal\Core\Image\Image $imageLoaded */
    $imageLoaded = $this->imageFactory->get($file->getFileUri());
    if (!$imageLoaded->isValid()) {
      throw new InvalidArgumentException('The file with id ' . $file->id() . ' is not an image.');
    }

    return $imageLoaded->getSource();
  }

  /**
   * Returns the relative crop value used by the focal point preview.
   *
   * @param \Drupal\file\Entity\File $file
   *   The loaded image.
   * @param \Drupal\image\Plugin\Field\FieldType\ImageItem $image
   *   The image from the entity.
   *
   * @return string
   *   The focal point value.
   *
   * @throws \InvalidArgumentException
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  protected function getFocalPointValue(File $file, ImageItem $image): string {
    // Load the focal point anchors for the file.
    $pos = $this->focalPointManager->getCropEntity($file, 'focal_point')->anchor();

    // Get the relative coordinates.
    $relativePosition = $this->focalPointManager->absoluteToRelative(
      $pos['x'],
      $pos['y'],
      $image->get('width')->getValue(),
      $image->get('height')->getValue()
    );

    return "{$relativePosition['x']}x{$relativePosition['y']}";
  }

  /**
   * Build a list of image styles that are needed for the crop list.
   *
   * @return \Drupal\image\ImageStyleInterface[]
   *   An array of image styles.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \InvalidArgumentException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @todo: This shouldn't be public, refactor.
   */
  public function getImageStyleList(): array {
    // @todo: Generalize; add a checkbox to the image styles instead of this static list.
    $styleList = [
      'other_hi_res_no_badge',
      'facebook_organic',
      'facebook_organic_no_badge',
      'facebook_paid_campaign',
      'facebook_paid_campaign_no_badge',
      'instagram_photo_size',
      'instagram_photo_size_no_badge',
      'instagram_paid_campaign',
      'instagram_paid_campaign_no_badge',
      'linkedin_organic_or_paid_image',
      'linkedin_organic_or_paid_image_no_badge',
      'linkedin_personal_account_newsfeed_update_organic',
      'linkedin_personal_account_newsfeed_update_organic_no_badge',
      'twitter_website_card_paid_campaign',
      'twitter_website_card_paid_campaign_no_badge',
      'twitter_in_stream_photo',
      'twitter_in_stream_photo_no_badge',
      'twitter_organic_tweet',
      'twitter_organic_tweet_no_badge',
      'ms_powerpoint_no_badge',
    ];

    /** @var \Drupal\Core\Config\Entity\ConfigEntityStorage $styleStorage */
    $styleStorage = $this->entityTypeManager->getStorage('image_style');
    /** @var \Drupal\image\ImageStyleInterface[] $styles */
    $styles = $styleStorage->loadMultiple($styleList);

    if (empty($styles)) {
      throw new InvalidArgumentException('No applicable image styles found.');
    }

    return $styles;
  }

}
