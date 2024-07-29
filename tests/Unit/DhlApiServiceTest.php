<?php

namespace Drupal\Tests\dhl_location_finder\Service;

use Drupal\Tests\UnitTestCase;
use Drupal\dhl_location_finder\Service\DhlApiService;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests the DhlApiService.
 *
 * @group dhl_location_finder
 */
class DhlApiServiceTest extends UnitTestCase {
  use ProphecyTrait;

  protected $httpClient;
  protected $dhlApiService;
  protected $apiKey;

  protected function setUp(): void {
    parent::setUp();
    $this->httpClient = $this->prophesize(ClientInterface::class);
    $this->dhlApiService = new DhlApiService($this->httpClient->reveal());

    // Retrieve API key from environment variable once and store it
    $this->apiKey = getenv('DHL_API_KEY');
  }

  public function testGetLocations() {
    $country = 'DE';
    $city = 'Dresden';
    $postal_code = '01067';

    $mock_response = new Response(200, [], json_encode([
      'locations' => [
        [
          'name' => 'Packstation 103',
          'place' => [
            'address' => [
              'countryCode' => 'DE',
              'postalCode' => '01067',
              'addressLocality' => 'Dresden',
              'streetAddress' => 'Falkenstr. 10',
            ],
          ],
          'openingHours' => [
            ['dayOfWeek' => 'http://schema.org/Monday', 'opens' => '00:00:00', 'closes' => '23:59:00'],
            ['dayOfWeek' => 'http://schema.org/Tuesday', 'opens' => '00:00:00', 'closes' => '23:59:00'],
            ['dayOfWeek' => 'http://schema.org/Wednesday', 'opens' => '00:00:00', 'closes' => '23:59:00'],
            ['dayOfWeek' => 'http://schema.org/Thursday', 'opens' => '00:00:00', 'closes' => '23:59:00'],
            ['dayOfWeek' => 'http://schema.org/Friday', 'opens' => '00:00:00', 'closes' => '23:59:00'],
            ['dayOfWeek' => 'http://schema.org/Saturday', 'opens' => '00:00:00', 'closes' => '23:59:00'],
            ['dayOfWeek' => 'http://schema.org/Sunday', 'opens' => '00:00:00', 'closes' => '23:59:00'],
          ],
        ],
      ],
    ]));

    $this->httpClient->get('https://api.dhl.com/location-finder/v1/find-by-address', [
      'headers' => [
        'DHL-API-Key' => $this->apiKey,
      ],
      'query' => [
        'countryCode' => $country,
        'addressLocality' => $city,
        'postalCode' => $postal_code,
      ],
    ])->willReturn($mock_response);

    $locations = $this->dhlApiService->getLocations($country, $city, $postal_code);

    $expected = [
      [
        'name' => 'Packstation 103',
        'place' => [
          'address' => [
            'countryCode' => 'DE',
            'postalCode' => '01067',
            'addressLocality' => 'Dresden',
            'streetAddress' => 'Falkenstr. 10',
          ],
        ],
        'openingHours' => [
          ['dayOfWeek' => 'http://schema.org/Monday', 'opens' => '00:00:00', 'closes' => '23:59:00'],
          ['dayOfWeek' => 'http://schema.org/Tuesday', 'opens' => '00:00:00', 'closes' => '23:59:00'],
          ['dayOfWeek' => 'http://schema.org/Wednesday', 'opens' => '00:00:00', 'closes' => '23:59:00'],
          ['dayOfWeek' => 'http://schema.org/Thursday', 'opens' => '00:00:00', 'closes' => '23:59:00'],
          ['dayOfWeek' => 'http://schema.org/Friday', 'opens' => '00:00:00', 'closes' => '23:59:00'],
          ['dayOfWeek' => 'http://schema.org/Saturday', 'opens' => '00:00:00', 'closes' => '23:59:00'],
          ['dayOfWeek' => 'http://schema.org/Sunday', 'opens' => '00:00:00', 'closes' => '23:59:00'],
        ],
      ],
    ];

    $this->assertEquals($expected, $locations);
  }

  public function testGetLocationsApiFailure() {
    $country = 'DE';
    $city = 'Dresden';
    $postal_code = '01067';

    $this->httpClient->get('https://api.dhl.com/location-finder/v1/find-by-address', [
      'headers' => [
        'DHL-API-Key' => $this->apiKey,
      ],
      'query' => [
        'countryCode' => $country,
        'addressLocality' => $city,
        'postalCode' => $postal_code,
      ],
    ])->willThrow(new \Exception('API request failed'));

    $locations = $this->dhlApiService->getLocations($country, $city, $postal_code);

    $this->assertNull($locations);
  }
}