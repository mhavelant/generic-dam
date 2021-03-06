<?php

namespace Drupal\media_assets_download\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use SplFileInfo;
use function count;
use function is_dir;
use function reset;

/**
 * Class AssetDownloadHandler.
 *
 * @package Drupal\media_assets_download\Service
 */
class AssetDownloadHandler {

  private $fileHandler;
  private $archiver;

  private $fileManager;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  private $fileSystem;

  /**
   * Time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  private $time;

  /**
   * Date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  private $dateFormatter;

  /**
   * AssetDownloadHandler constructor.
   *
   * @param \Drupal\media_assets_download\Service\AssetFileHandler $fileHandler
   *   File handler service.
   * @param \Drupal\media_assets_download\Service\AssetArchiver $archiver
   *   File archiver.
   * @param \Drupal\media_assets_download\Service\FileManager $fileManager
   *   File manager.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   Time service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   Date formatter.
   */
  public function __construct(
    AssetFileHandler $fileHandler,
    AssetArchiver $archiver,
    FileManager $fileManager,
    FileSystemInterface $fileSystem,
    TimeInterface $time,
    DateFormatterInterface $dateFormatter
  ) {
    $this->fileHandler = $fileHandler;
    $this->archiver = $archiver;
    $this->fileManager = $fileManager;
    $this->fileSystem = $fileSystem;
    $this->time = $time;
    $this->dateFormatter = $dateFormatter;
  }

  /**
   * Generates a downloadable file for the media entity.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media.
   *
   * @return \Drupal\file\FileInterface|null
   *   The file or NULL.
   */
  public function generateDownloadableFile(MediaInterface $media): ?FileInterface {
    $fileData = $this->fileHandler->mediaFilesData($media);
    $fileCount = count($fileData);

    if ($fileCount <= 0) {
      return NULL;
    }

    if ($fileCount === 1) {
      return reset($fileData)->file;
    }

    $archiveLocation = $this->archiver->createFileArchive($this->archiveTargetPath($media), $fileData);

    if ($archiveLocation === NULL) {
      return NULL;
    }

    return $this->fileManager->createArchiveEntity($media->getOwner(), new SplFileInfo($archiveLocation));
  }

  /**
   * Returns the desired path to the media archive.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media.
   *
   * @return string|null
   *   Tha path, or NULL on failure.
   */
  private function archiveTargetPath(MediaInterface $media): ?string {
    $basePath = $this->fileSystem->realpath('private://');
    $fileDir = "{$basePath}/tmp/media/{$media->bundle()}/{$media->uuid()}";

    if (!$this->mkdir($fileDir)) {
      return NULL;
    }

    return "{$fileDir}/{$this->archiveTargetName($media)}";
  }

  /**
   * Generate archive name for a media.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity.
   *
   * @return string
   *   The archive name.
   */
  private function archiveTargetName(MediaInterface $media): string {
    return "{$media->getName()}_{$this->currentDate()}.zip";
  }

  /**
   * Returns the current date in the desired format.
   *
   * @return string
   *   The properly formatted current date.
   *
   * @todo: Move to service?
   */
  private function currentDate(): string {
    return $this->dateFormatter->format($this->time->getCurrentTime(), 'custom', 'Y-m-d');
  }

  /**
   * Safely and recursively create a directory.
   *
   * @param string $uri
   *   Directory path or URI.
   *
   * @return bool
   *   TRUE on success, FALSE on error.
   *
   * @todo: Move to service.
   */
  private function mkdir($uri): bool {
    $uriInfo = new SplFileInfo($uri);
    $path = $uri;

    if ($uriInfo->getExtension()) {
      $path = $uriInfo->getPath();
    }

    return !(!is_dir($path) && !$this->fileSystem->mkdir($path, NULL, TRUE) && !is_dir($path));
  }

}
