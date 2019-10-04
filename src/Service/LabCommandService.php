<?php

namespace App\Service;

use Symfony\Component\Console\Style\SymfonyStyle;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\GeolocationService;
use App\Service\NotificationService;

class LabCommandService
{
    protected $io;
    protected $o;

    private $geolocation;
    private $notification;
    private $em;

    public function __construct(EntityManagerInterface $entityManager, GeolocationService $geolocation, NotificationService $notification)
    {
        $this->em = $entityManager;
        $this->geolocation = $geolocation;
        $this->notification = $notification;
    }

    public function setIo($i, $o)
    {
        $this->io = new SymfonyStyle($i, $o);
        $this->o = $o;
    }

    public function geolocation()
    {
        $users = $this->em->getRepository('App:User')->findAll();

        foreach ($users as $user) {
            if ((empty($user->getCountry()) || empty($user->getLocation())) && !empty($user->getCoordinates())) {
                $latitude = $user->getCoordinates()->getLatitude();
                $longitude = $user->getCoordinates()->getLongitude();

                $location = $this->geolocation->getLocationName($latitude, $longitude);
                $country = $location["country"];
                $location = $location["locality"];
                if (!empty($country)) {
                    $user->setCountry($country);
                }
                if (!empty($location)) {
                    $user->setLocation($location);
                }
                $this->em->persist($user);
                $this->em->flush();

                $this->o->writeln($user->getId() . " - " . $user->getUsername() . " - " . $country . " - " . $location);
                $this->em->detach($user);
            }
        }
    }

    public function notification($fromId, $toId)
    {
        $fromUser = $this->em->getRepository('App:User')->findOneBy(array('id' => $fromId));
        $toUser = $this->em->getRepository('App:User')->findOneBy(array('id' => $toId));
        $title = "albertoi";
        $text = "💓Doki doki ¡El FrikiRadar ha detectado a alguien interesante cerca!";
        $url = "/profile/" . $fromId;
        $this->notification->push($fromUser, $toUser, $title, $text, $url, "radar");
    }
}
