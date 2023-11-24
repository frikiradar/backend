<?php
// src/Service/GeolocationService.php
namespace App\Service;

use LongitudeOne\Spatial\PHP\Types\Geometry\Point;
use Geocoder\Query\GeocodeQuery;
use GuzzleHttp\Client as GuzzleClient;

class GeolocationService
{
    public function __construct()
    {
    }

    public function geolocate($latitude = '', $longitude = '', $city = '', $country = ''): Point
    {
        $coords = new Point(0, 0);
        $coords
            ->setLatitude($latitude)
            ->setLongitude($longitude);

        return $coords;
    }

    public function manualGeolocate($city = '', $country = ''): Point
    {
        $coords = new Point(0, 0);

        $httpClient = new GuzzleClient();
        $provider = new \Geocoder\Provider\ArcGISOnline\ArcGISOnline($httpClient);
        $geocoder = new \Geocoder\StatefulGeocoder($provider, 'en');

        $result = $geocoder->geocodeQuery(GeocodeQuery::create($city . ', ' . $country));
        $coordinates = $result->first()->getCoordinates();
        $latitude = $coordinates->getLatitude();
        $longitude = $coordinates->getLongitude();

        if ($latitude && $longitude) {
            $coords
                ->setLatitude($latitude)
                ->setLongitude($longitude);
        }

        return $coords;
    }
}
