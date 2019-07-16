<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\GeolocationService;

class MyCommand extends Command
{
    private $em;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->em = $entityManager;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('my:command')
            ->setDescription('Ejecutar mi comando');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $geolocation = new GeolocationService();
        $users = $this->em->getRepository('App:User')->findAll();

        foreach ($users as $user) {
            if (empty($user->getCountry()) && !empty($user->getCoordinates())) {
                $latitude = $user->getCoordinates()->getLatitude();
                $longitude = $user->getCoordinates()->getLongitude();

                $location = $geolocation->getLocationName($latitude, $longitude);
                $country = $location["country"];
                $user->setCountry($country ?: null);
                $this->em->persist($user);
                $this->em->flush();

                $output->writeln($user->getId() . " - " . $user->getUsername() . " - " . $country);
                $this->em->detach($user);
            }
        }
    }
}
