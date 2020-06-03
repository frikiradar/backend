<?php

namespace App\Service;

use Symfony\Component\Console\Style\SymfonyStyle;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\GeolocationService;
use App\Service\NotificationService;
use Twig\Environment;

class LabCommandService
{
    protected $io;
    protected $o;

    private $notification;
    private $mailer;
    private $twig;

    public function __construct(
        EntityManagerInterface $entityManager,
        GeolocationService $geolocation,
        NotificationService $notification,
        \Swift_Mailer $mailer,
        Environment $twig
    ) {
        $this->em = $entityManager;
        $this->geolocation = $geolocation;
        $this->notification = $notification;
        $this->mailer = $mailer;
        $this->twig = $twig;
    }

    public function setIo($i, $o)
    {
        $this->io = new SymfonyStyle($i, $o);
        $this->o = $o;
    }

    public function geolocation()
    {
        /*$users = $this->em->getRepository('App:User')->findAll();

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
        }*/
    }

    public function notification($fromId, $toId)
    {
        $fromUser = $this->em->getRepository('App:User')->findOneBy(array('id' => $fromId));
        $toUser = $this->em->getRepository('App:User')->findOneBy(array('id' => $toId));
        $title = "Notificación de prueba";
        $text = "test";
        $url = "/profile/" . $fromId;
        $this->notification->push($fromUser, $toUser, $title, $text, $url, "radar");
    }

    public function email($toId)
    {
        $user = $this->em->getRepository('App:User')->findOneBy(array('id' => $toId));
        $message = (new \Swift_Message('¡FrikiRadar te extraña 💔!'))
            ->setFrom(['hola@frikiradar.com' => 'FrikiRadar'])
            ->setTo($user->getEmail())
            ->setBody(
                $this->twig->render(
                    "emails/registration.html.twig",
                    [
                        'username' => $user->getUsername(),
                        'code' => 'ABCDEF'
                    ]
                ),
                'text/html'
            );

        if (0 === $this->mailer->send($message)) {
            throw new \RuntimeException('Unable to send email');
        }
    }
}
