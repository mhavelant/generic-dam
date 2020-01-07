<?php

namespace Drupal\media_assets\Model;

/**
 * Class FileArchivingData.
 *
 * @package Drupal\media_assets\Model
 */
class FileArchivingData {

  /**
   * File entity.
   *
   * @var \Drupal\file\FileInterface
   */
  public $file;

  /**
   * Absolute path of the file on the system.
   *
   * @var string
   */
  public $systemPath;

  /**
   * Desired path of the file in an archive.
   *
   * @var string
   */
  public $archiveTargetPath;

  /**
   * FileArchivingData constructor.
   *
   * @param array $values
   *   Values.
   */
  public function __construct(array $values) {
    foreach ($values as $key => $value) {
      $this->{$key} = $value;
    }
  }

}
