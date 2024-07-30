<?php

namespace Drupal\dhl_location_finder\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class LocationFinderForm extends FormBase {

  public function getFormId() {
    return 'dhl_location_finder_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['country'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Country'),
      '#required' => TRUE,
      '#placeholder' => $this->t('e.g., DE'),
      '#description' => $this->t('Enter a 2-letter country code (e.g., DE for Germany).'),
    ];
    $form['city'] = [
      '#type' => 'textfield',
      '#title' => $this->t('City'),
      '#required' => TRUE,
      '#placeholder' => $this->t('e.g., Berlin'),
      '#description' => $this->t('Enter the name of the city.'),
    ];
    $form['postal_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Postal Code'),
      '#required' => TRUE,
      '#placeholder' => $this->t('e.g., 10115'),
      '#description' => $this->t('Enter the postal code (numbers only).'),
    ];
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Find Locations'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $country = $form_state->getValue('country');
    $city = $form_state->getValue('city');
    $postal_code = $form_state->getValue('postal_code');

    // Validate country code: must be exactly 2 capital letters.
    if (!preg_match('/^[A-Z]{2}$/', $country)) {
      $form_state->setErrorByName('country', $this->t('The country code must be exactly 2 capital letters (e.g., DE).'));
    }

    // Validate city: must not be empty.
    if (empty($city)) {
      $form_state->setErrorByName('city', $this->t('City field cannot be empty.'));
    }

    // Validate postal code: must be numeric.
    if (!ctype_digit($postal_code)) {
      $form_state->setErrorByName('postal_code', $this->t('The postal code must contain only numbers.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $query = [
      'country' => $form_state->getValue('country'),
      'city' => $form_state->getValue('city'),
      'postal_code' => $form_state->getValue('postal_code'),
    ];
    $form_state->setRedirect('dhl_location_finder.results', [], ['query' => $query]);
  }
}
