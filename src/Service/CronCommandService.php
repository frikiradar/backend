<?php

namespace App\Service;

use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Config\Definition\Exception\Exception;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;

class CronCommandService
{
    protected $io;
    protected $o;

    private $mailer;
    private $em;
    private $twig;
    private $notification;

    public function __construct(
        EntityManagerInterface $entityManager,
        \Swift_Mailer $mailer,
        NotificationService $notification
    ) {
        $this->em = $entityManager;
        $this->mailer = $mailer;
        $this->notification = $notification;
    }

    public function setIo($i, $o)
    {
        $this->io = new SymfonyStyle($i, $o);
        $this->o = $o;
    }

    public function reminder($days)
    {
        if ($days >= 15) {
            // Recoge los usuarios que hace exactamente $days días que no se conectan
            $users = $this->em->getRepository(\App\Entity\User::class)->getUsersByLastLogin($days);
            foreach ($users as $user) {
                $message = (new \Swift_Message('¡FrikiRadar te extraña 💔!'))
                    ->setFrom(['hola@frikiradar.com' => 'FrikiRadar'])
                    ->setTo($user->getEmail())
                    ->setBody(
                        $this->twig->render(
                            "emails/reminder.html.twig",
                            [
                                'username' => $user->getUsername(),
                                'code' => $user->getMailingCode(),
                            ]
                        ),
                        'text/html'
                    );

                if (0 === $this->mailer->send($message)) {
                    $this->o->writeln("Error al enviar el email a " . $user->getUsername() . " (" . $user->getEmail() . ")");
                } else {
                    $this->o->writeln("Email enviado a " . $user->getUsername() . " (" . $user->getEmail() . ")");
                }
            }
        } else {
            $this->o->writeln("El número de días es muy bajo");
        }
        /*$process = new Process(
            'php bin/console abo:crons ' . $commandName . ' --thread=' . $thread . ' --limit=' . $limit . ' --offset=' . $offset
        );
        $process->setWorkingDirectory(getcwd() . "../");
        $process->disableOutput();
        $process->start();*/
    }

    public function eventReminder()
    {
        // Buscamos todos los eventos próximos y enviamos avisos a los participantes
        $events = $this->em->getRepository(\App\Entity\Event::class)->findNextEvents();
        foreach ($events as $event) {
            $slug = 'event-' . $event->getId();
            // Evento
        }
    }
}
