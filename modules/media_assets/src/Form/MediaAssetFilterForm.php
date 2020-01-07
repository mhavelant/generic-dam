<?php

namespace Drupal\media_assets\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Implements an example form.
 */
class MediaAssetFilterForm extends FormBase {

  /**
   * Available social media platform types.
   *
   * @var array
   */
  private static $platformTypes = [
    'other' => 'Hi-Res',
    'facebook' => 'Facebook',
    'twitter' => 'Twitter',
    'instagram' => 'Instagram',
    'linkedin' => 'LinkedIn',
    'ms' => 'Powerpoint',
    'tieto' => 'Tieto.com',
  ];

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'media_asset_filter_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attributes']['id'] = 'filter-platform';
    $form['platform'] = [
      '#type' => 'select',
      '#title' => $this->t('Filter by platform'),
      '#options' => ['_none' => $this->t('- All -')] + static::$platformTypes,
    ];
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Ok'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    drupal_set_message($this->t('Showing social media assets only for @name.', ['@name' => $form_state->getValue('platform')]));
  }

}
