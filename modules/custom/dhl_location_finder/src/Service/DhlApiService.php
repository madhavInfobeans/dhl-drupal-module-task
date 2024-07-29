<?php

namespace Drupal\dhl_location_finder\Service;

use GuzzleHttp\ClientInterface;

class DhlApiService {

  protected $httpClient;

  public function __construct(ClientInterface $httpClient) {
    $this->httpClient = $httpClient;
  }

  public function getLocations($country, $city, $postal_code) {
    $url = 'https://api.dhl.com/location-finder/v1/find-by-address';
    try {
      $response = $this->httpClient->get($url, [
        'headers' => [
          'DHL-API-Key' => 'buxERDZGi26uIouY8kstPgri1AHdAdE1',
        ],
        'query' => [
          'countryCode' => $country,
          'addressLocality' => $city,
          'postalCode' => $postal_code,
        ],
      ]);

      $data = json_decode($response->getBody(), TRUE);
      \Drupal::logger('dhl_location_finder')->info('API Response: <pre>@data</pre>', ['@data' => print_r($data, TRUE)]);
      return $data['locations'] ?? [];
    } catch (\Exception $e) {
      \Drupal::logger('dhl_location_finder')->error('API request failed: @message', [
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }
}
