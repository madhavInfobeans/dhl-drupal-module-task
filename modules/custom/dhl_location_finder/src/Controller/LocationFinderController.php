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

  private function filterLocations($locations) {
    \Drupal::logger('dhl_location_finder')->info('Unfiltered Locations: <pre>@locations</pre>', [
      '@locations' => print_r($locations, TRUE),
    ]);

    if (isset($locations['locations'])) {
      return array_filter($locations['locations'], function ($location) {
        $address = $location['place']['address']['streetAddress'] ?? '';
        $address_number = preg_replace('/\D/', '', $address);
        $address_number = $address_number !== '' ? (int) $address_number : 0;

        $opening_hours = $location['openingHours'] ?? [];

        $closed_on_saturday = true;
        $closed_on_sunday = true;
        foreach ($opening_hours as $hours) {
          if (isset($hours['dayOfWeek']) && str_replace('http://schema.org/', '', $hours['dayOfWeek']) === 'Saturday') {
            $closed_on_saturday = false;
          }
          if (isset($hours['dayOfWeek']) && str_replace('http://schema.org/', '', $hours['dayOfWeek']) === 'Sunday') {
            $closed_on_sunday = false;
          }
        }

        $weekend_closed = $closed_on_saturday && $closed_on_sunday;

        \Drupal::logger('dhl_location_finder')->info('Location: @location_name, Address: @address, Address Number: @address_number, Weekend Closed: @weekend_closed', [
          '@location_name' => $location['name'] ?? 'Unknown',
          '@address' => $address,
          '@address_number' => $address_number,
          '@weekend_closed' => $weekend_closed ? 'Yes' : 'No',
        ]);

        return !$weekend_closed && $address_number % 2 === 0;
      });
    } else {
      \Drupal::logger('dhl_location_finder')->error('API response does not contain "locations" key.');
      return [];
    }
  }

  private function convertToYaml($locations) {
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
