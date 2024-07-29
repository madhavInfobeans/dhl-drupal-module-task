<?php

namespace Drupal\dhl_location_finder\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;

/**
 * Class DhlApiService.
 *
 * @package Drupal\dhl_location_finder\Service
 */
class DhlApiService {
  
  protected $httpClient;
  protected $configFactory;

  public function __construct(ClientInterface $http_client, ConfigFactoryInterface $config_factory) {
    $this->httpClient = $http_client;
    $this->configFactory = $config_factory;
  }

  public function getLocations($country, $city, $postal_code) {
    $config = $this->configFactory->get('dhl_location_finder.settings');
    $api_key = $config->get('dhl_api_key');

    try {
      $response = $this->httpClient->get('https://api.dhl.com/location-finder/v1/find-by-address', [
        'headers' => [
          'DHL-API-Key' => $api_key,
        ],
        'query' => [
          'countryCode' => $country,
          'addressLocality' => $city,
          'postalCode' => $postal_code,
        ],
      ]);

      return json_decode($response->getBody(), TRUE);
    } catch (\Exception $e) {
      \Drupal::logger('dhl_location_finder')->error($e->getMessage());
      return NULL;
    }
  }
}
