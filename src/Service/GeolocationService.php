<?php
// src/Service/GeolocationService.php
namespace App\Service;

use CrEOF\Spatial\PHP\Types\Geometry\Point;
use Geocoder\Provider\GoogleMaps\GoogleMaps;
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

        if ($latitude && $longitude) {
            $coords
                ->setLatitude($latitude)
                ->setLongitude($longitude);
        } else {
            // $key = 'AIzaSyB3VlBHlrMY6Vw9wf3_oGE2PcI7QV9EBT8';
            $httpClient = new GuzzleClient();
            // $provider = new GoogleMaps($httpClient, null, $key);
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
        }
        return $coords;
    }
}
