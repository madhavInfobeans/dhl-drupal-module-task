<?php

namespace Drupal\Tests\dhl_location_finder\Unit\Controller;

use Drupal\dhl_location_finder\Controller\LocationFinderController;
use Drupal\dhl_location_finder\Service\DhlApiService;
use Drupal\Tests\UnitTestCase;
use Prophecy\Prophecy\ObjectProphecy;

/**
 * @coversDefaultClass \Drupal\dhl_location_finder\Controller\LocationFinderController
 * @group dhl_location_finder
 */
class LocationFinderControllerTest extends UnitTestCase {

  /**
   * The LocationFinderController being tested.
   *
   * @var \Drupal\dhl_location_finder\Controller\LocationFinderController
   */
  protected $controller;

  /**
   * The mocked DhlApiService.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $dhlApiService;

  /**
   * Set up the environment for testing.
   */
  protected function setUp(): void {
    parent::setUp();
    
    // Create a mock DhlApiService.
    $this->dhlApiService = $this->prophesize(DhlApiService::class);
    
    // Create the controller with the mocked service.
    $this->controller = new LocationFinderController($this->dhlApiService->reveal());
  }

  /**
   * Test filterLocations method with valid data.
   *
   * @covers ::filterLocations
   */
  public function testFilterLocations() {
    // Mock data to pass into the filterLocations method.
    $locations = [
      'locations' => [
        [
          'location' => [
            'ids' => [
              ['locationId' => '123-45'],
              ['locationId' => '456-78'],
            ],
          ],
          'openingHours' => [
            ['dayOfWeek' => 'http://schema.org/Saturday', 'opens' => '08:00', 'closes' => '18:00'],
            ['dayOfWeek' => 'http://schema.org/Sunday', 'opens' => '08:00', 'closes' => '18:00'],
          ],
        ],
        [
          'location' => [
            'ids' => [
              ['locationId' => '789-12'],
            ],
          ],
          'openingHours' => [
            ['dayOfWeek' => 'http://schema.org/Wednesday', 'opens' => '09:00', 'closes' => '17:00'],
          ],
        ],
      ],
    ];

    // Call the protected method.
    $reflection = new \ReflectionClass($this->controller);
    $method = $reflection->getMethod('filterLocations');
    $method->setAccessible(true);

    $result = $method->invoke($this->controller, $locations);

    // Assert that the result is as expected.
    $this->assertCount(1, $result);
    $this->assertArrayHasKey('location', $result[0]);
    $this->assertArrayHasKey('ids', $result[0]['location']);
  }

  /**
   * Test worksOnWeekends method with location data.
   *
   * @covers ::worksOnWeekends
   */
  public function testWorksOnWeekends() {
    $location = [
      'openingHours' => [
        ['dayOfWeek' => 'http://schema.org/Saturday', 'opens' => '08:00', 'closes' => '18:00'],
        ['dayOfWeek' => 'http://schema.org/Sunday', 'opens' => '08:00', 'closes' => '18:00'],
      ],
    ];

    $reflection = new \ReflectionClass($this->controller);
    $method = $reflection->getMethod('worksOnWeekends');
    $method->setAccessible(true);

    $result = $method->invoke($this->controller, $location);

    // Assert that the result is true.
    $this->assertTrue($result);
  }

  /**
   * Test containsOddAfterHyphen method with location IDs.
   *
   * @covers ::containsOddAfterHyphen
   */
  public function testContainsOddAfterHyphen() {
    $location_ids = [
      ['locationId' => '123-45'],
      ['locationId' => '456-78'],
    ];

    $reflection = new \ReflectionClass($this->controller);
    $method = $reflection->getMethod('containsOddAfterHyphen');
    $method->setAccessible(true);

    $result = $method->invoke($this->controller, $location_ids);

    // Assert that the result is true as '45' contains odd number '5'.
    $this->assertTrue($result);
  }
}
