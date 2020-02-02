<?php
// src/Service/GeolocationService.php
namespace App\Service;

use Geocoder\Query\ReverseQuery;
use CrEOF\Spatial\PHP\Types\Geometry\Point;

class GeolocationService
{
    private $httpClient;

    public function __construct()
    {
        $this->httpClient = new \Http\Adapter\Guzzle6\Client();
    }

    public function geolocate($ip, $latitude = '', $longitude = ''): Point
    {
        $coords = new Point(0, 0);
        $coords
            ->setLatitude($latitude)
            ->setLongitude($longitude);

        /*if ($latitude && $longitude) {
            $coords
                ->setLatitude($latitude)
                ->setLongitude($longitude);
        } else {
            $provider = new \Geocoder\Provider\GeoPlugin\GeoPlugin($this->httpClient);
            $geocoder = new \Geocoder\StatefulGeocoder($provider, 'es');
            $ipResult = $geocoder->geocode($ip);
            if (!is_null($ipResult->first()->getCoordinates())) {
                $coords
                    ->setLatitude($ipResult->first()->getCoordinates()->getLatitude())
                    ->setLongitude($ipResult->first()->getCoordinates()->getLongitude());
            }
        }*/
        return $coords;
    }

    public function getLocationName($latitude, $longitude): array
    {
        try {
            $google = new \Geocoder\Provider\GoogleMaps\GoogleMaps($this->httpClient, null, 'AIzaSyDgwnkBNx1TrvQO0GZeMmT6pNVvG3Froh0');
            $geocoder = new \Geocoder\StatefulGeocoder($google, 'es');
            $result = $geocoder->reverseQuery(ReverseQuery::fromCoordinates($latitude, $longitude));
            if (!$result->isEmpty()) {
                return ["locality" => $result->first()->getLocality() ?: $result->first()->getSubLocality(), "country" => $result->first()->getCountry()->getCode()];
            } else {
                return false;
            }
        } catch (Exception $ex) {
            // throw new HttpException(400, "No se ha podido obtener la localidad - Error: {$ex->getMessage()}");
        }
    }
}
