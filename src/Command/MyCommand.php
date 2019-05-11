<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\ORM\EntityManagerInterface;

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
        $users = $this->em->getRepository('App:User')->findAll();

        foreach ($users as $user) {
            $files = glob("../public/images/avatar/" . $user->getId() . "/*.jpg");
            usort($files, function ($a, $b) {
                return basename($b) <=> basename($a);
            });

            if (isset($files[0])) {
                $server = "https://$_SERVER[HTTP_HOST]";
                $avatar = str_replace("../public", $server, $files[0]);
            } else {
                $avatar = false;
            }
            echo $avatar;
            $user->setAvatar($avatar);
            $this->em->persist($user);
            $this->em->flush();

            $output->writeln($user->getId() . " - " . $user->getUsername() . "-" . $avatar);
            $this->em->detach($user);
        }
    }
}
