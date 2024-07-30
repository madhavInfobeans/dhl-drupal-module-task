<?php

namespace Drupal\dhl_location_finder\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class DhlApiConfigForm.
 *
 * @package Drupal\dhl_location_finder\Form
 */
class DhlApiConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dhl_location_finder_config_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'dhl_location_finder.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('dhl_location_finder.settings');
  
    $form['dhl_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('DHL API Key'),
      '#default_value' => $config->get('dhl_api_key'),
      '#description' => $this->t('Enter your DHL API key. This key is required for accessing DHL API services.'),
    ];
  
    $form['help'] = [
      '#type' => 'item',
      '#title' => $this->t('Configuration Path'),
      '#description' => $this->t('To configure the DHL API settings, navigate to %path.', [
        '%path' => '/admin/config/dhl-location-finder',
      ]),
    ];
  
    return parent::buildForm($form, $form_state);
  }
  
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('dhl_location_finder.settings')
      ->set('dhl_api_key', $form_state->getValue('dhl_api_key'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}
