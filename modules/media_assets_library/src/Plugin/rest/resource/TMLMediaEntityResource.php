<?php

namespace Drupal\media_assets_library\Plugin\rest\resource;

use Drupal;
use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides a resource for media entities.
 *
 * @RestResource(
 *   id = "tml_media_library",
 *   label = @Translation("TML Media entity"),
 *   serialization_class = "Drupal\Core\Entity\Entity",
 *   uri_paths = {
 *     "canonical" = "/api/v1/media_library/{media_entity}",
 *     "https://www.drupal.org/link-relations/create" = "/api/v1/media_library"
 *   }
 * )
 */
class TMLMediaEntityResource extends ResourceBase implements DependentPluginInterface {

  /**
   * The entity type targeted by this resource.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface
   */
  protected $entityType;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a Drupal\rest\Plugin\rest\resource\EntityResource object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, array $serializer_formats, LoggerInterface $logger, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->entityType = $entity_type_manager->getDefinition('media');
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('config.factory')
    );
  }

  /**
   * Provides predefined HTTP request methods.
   *
   * Plugins can override this method to provide additional custom request
   * methods.
   *
   * @return array
   *   The list of allowed HTTP request method strings.
   */
  protected function requestMethods() {
    return [
      'GET',
    ];
  }

  /**
   * RESTful response to GET requests.
   *
   * @param string|int $media_entity_id
   *   The ID of the Marketing Activity.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The Marketing Activity as a ResourceResponse.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Thrown when the log entry was not found.
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   Thrown when no log entry was provided.
   */
  public function get($media_entity_id = NULL) {
    if (empty($media_entity_id)) {
      throw new BadRequestHttpException(t('No Media entity ID was provided'));
    }

    // If the request is 'all', we return a list of links to the entities.
    if ($media_entity_id === 'list') {
      /** @var int[] $media_ids */
      $media_ids = Drupal::entityQuery('media')
        ->condition('status', 1, '=')
        ->execute();

      $media_entity_storage = Drupal::entityTypeManager()
        ->getStorage('media');

      /** @var \Drupal\media\MediaInterface[] $media_data */
      $media_entity = $media_entity_storage->loadMultiple($media_ids);
    }
    else {
      // If the request is an id, we try to load it.
      $media_entity_storage = Drupal::entityTypeManager()
        ->getStorage('media');
      /** @var \Drupal\media\MediaInterface $media_data */
      $media_entity = $media_entity_storage->load($media_entity_id);

      if (NULL === $media_entity) {
        throw new NotFoundHttpException(t('Media entity with ID @id was not found', ['@id' => $media_entity_id]));
      }

      /** @var \Drupal\Core\Access\AccessResultReasonInterface $entity_access */
      $entity_access = $media_entity->access('view', NULL, TRUE);
      if (!$entity_access->isAllowed()) {
        throw new AccessDeniedHttpException($entity_access->getReason() ?: $this->generateFallbackAccessDeniedMessage($media_entity, 'view'));
      }

      $type = $media_entity->bundle();
      if ('image' !== $type) {
        throw new BadRequestHttpException(t('The type of the requested Media entity (@type) is not supported.', ['@type' => $type]));
      }
    }

    $response = new ResourceResponse($media_entity, 200);
    $response->addCacheableDependency($media_entity);


    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    if (isset($this->entityType)) {
      return ['module' => [$this->entityType->getProvider()]];
    }
  }

}
