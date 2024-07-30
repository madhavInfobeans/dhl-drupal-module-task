<?php

namespace Drupal\dhl_location_finder\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\dhl_location_finder\Service\DhlApiService;
use Symfony\Component\Yaml\Yaml;

class LocationFinderController extends ControllerBase {

  protected $dhlApiService;

  public function __construct(DhlApiService $dhlApiService) {
    $this->dhlApiService = $dhlApiService;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dhl_location_finder.dhl_api_service')
    );
  }

  public function findLocations(Request $request) {
    $country = $request->query->get('country');
    $city = $request->query->get('city');
    $postal_code = $request->query->get('postal_code');

    \Drupal::logger('dhl_location_finder')->info('Form Submitted: Country: @country, City: @city, Postal Code: @postal_code', [
      '@country' => $country,
      '@city' => $city,
      '@postal_code' => $postal_code,
    ]);

    if ($country && $city && $postal_code) {
      $locations = $this->dhlApiService->getLocations($country, $city, $postal_code);

      // Log the raw API response
      \Drupal::logger('dhl_location_finder')->info('API Response: <pre>@response</pre>', [
        '@response' => print_r($locations, TRUE),
      ]);

      if ($locations) {
        $filtered_locations = $this->filterLocations($locations);
        
        // Log filtered locations
        \Drupal::logger('dhl_location_finder')->info('Filtered Locations: <pre>@filtered_locations</pre>', [
          '@filtered_locations' => print_r($filtered_locations, TRUE),
        ]);
        
        if (!empty($filtered_locations)) {
          $yaml = $this->convertToYaml($filtered_locations);
          return [
            '#markup' => '<pre>' . htmlspecialchars($yaml) . '</pre>',
          ];
        } else {
          return [
            '#markup' => $this->t('No locations match the filter criteria.'),
          ];
        }
      } else {
        return [
          '#markup' => $this->t('No locations found or an error occurred.'),
        ];
      }
    }

    return [
      '#markup' => $this->t('Please provide valid inputs.'),
    ];
  }

  /**
   * Filter locations based on specific criteria.
   *
   * @param array $locations
   *   Array of locations to filter.
   *
   * @return array
   *   Filtered array of locations.
   */
  protected function filterLocations(array $locations) {
    \Drupal::logger('dhl_location_finder')->info('Unfiltered Locations: <pre>@locations</pre>', [
      '@locations' => print_r($locations, TRUE),
    ]);

    if (isset($locations['locations'])) {
      return array_filter($locations['locations'], function ($location) {
        $location_ids = $location['location']['ids'] ?? [];
        $worksOnWeekends = $this->worksOnWeekends($location);
        $containsOddAfterHyphen = $this->containsOddAfterHyphen($location_ids);

        // Include if it works on weekends and does not contain odd numbers after hyphen
        return $worksOnWeekends && !$containsOddAfterHyphen;
      });
    } else {
      \Drupal::logger('dhl_location_finder')->error('API response does not contain "locations" key.');
      return [];
    }
  }

  /**
   * Check if a location works on weekends.
   *
   * @param array $location
   *   The location data.
   *
   * @return bool
   *   TRUE if the location works on weekends, FALSE otherwise.
   */
  protected function worksOnWeekends(array $location) {
    $opening_hours = $location['openingHours'] ?? [];
    $open_on_saturday = false;
    $open_on_sunday = false;
    
    foreach ($opening_hours as $hours) {
      if (isset($hours['dayOfWeek'])) {
        $dayOfWeek = str_replace('http://schema.org/', '', $hours['dayOfWeek']);
        if ($dayOfWeek === 'Saturday') {
          $open_on_saturday = true;
        } elseif ($dayOfWeek === 'Sunday') {
          $open_on_sunday = true;
        }
        // Early exit if both flags are set
        if ($open_on_saturday && $open_on_sunday) {
          break;
        }
      }
    }
    
    // Works on weekends if it is open on either Saturday or Sunday
    return $open_on_saturday || $open_on_sunday;
  }

  /**
   * Check if a location ID contains odd numbers after a hyphen.
   *
   * @param array $location_ids
   *   Array of location IDs.
   *
   * @return bool
   *   TRUE if any location ID contains odd numbers after a hyphen, FALSE otherwise.
   */
  protected function containsOddAfterHyphen(array $location_ids) {
    foreach ($location_ids as $id) {
      $locationId = $id['locationId'] ?? '';
      if (strpos($locationId, '-') !== false) {
        list(, $afterHyphen) = explode('-', $locationId);
        if (preg_match('/\d/', $afterHyphen, $matches)) {
          $numbers = str_split($afterHyphen);
          foreach ($numbers as $char) {
            if (is_numeric($char) && (int)$char % 2 != 0) {
              return true;
            }
          }
        }
      }
    }
    return false;
  }

  /**
   * Convert locations data to YAML format.
   *
   * @param array $locations
   *   Array of locations to convert.
   *
   * @return string
   *   YAML formatted string.
   */
  private function convertToYaml(array $locations) {
    $yaml_output = [];
    $days_of_week = [
      'monday' => '00:00:00 - 23:59:00',
      'tuesday' => '00:00:00 - 23:59:00',
      'wednesday' => '00:00:00 - 23:59:00',
      'thursday' => '00:00:00 - 23:59:00',
      'friday' => '00:00:00 - 23:59:00',
      'saturday' => '00:00:00 - 23:59:00',
      'sunday' => '00:00:00 - 23:59:00',
    ];

    foreach ($locations as $location) {
      $formatted_hours = $days_of_week;

      foreach ($location['openingHours'] as $hours) {
        if (isset($hours['dayOfWeek'])) {
          $day = str_replace('http://schema.org/', '', $hours['dayOfWeek']);
          $day_key = strtolower($day);

          if (array_key_exists($day_key, $formatted_hours)) {
            $formatted_hours[$day_key] = $hours['opens'] . ' - ' . $hours['closes'];
          }
        }
      }

      $yaml_output[] = [
        'locationName' => $location['name'] ?? '',
        'address' => [
          'countryCode' => $location['place']['address']['countryCode'] ?? '',
          'postalCode' => (string) ($location['place']['address']['postalCode'] ?? ''),
          'addressLocality' => $location['place']['address']['addressLocality'] ?? '',
          'streetAddress' => $location['place']['address']['streetAddress'] ?? '',
        ],
        'openingHours' => $formatted_hours,
      ];
    }

    return Yaml::dump($yaml_output, 2, 4, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
  }
}
