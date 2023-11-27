<?php

namespace App\Service;

use Symfony\Component\Console\Style\SymfonyStyle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class CronCommandService
{
    protected $io;
    protected $o;

    private $em;
    private $twig;

    public function __construct(
        EntityManagerInterface $entityManager,
    ) {
        $this->em = $entityManager;
    }

    public function setIo($i, $o)
    {
        $this->io = new SymfonyStyle($i, $o);
        $this->o = $o;
    }

    public function reminder($days, MailerInterface $mailer)
    {
        if ($days >= 15) {
            // Recoge los usuarios que hace exactamente $days días que no se conectan
            $users = $this->em->getRepository(\App\Entity\User::class)->getUsersByLastLogin($days);
            foreach ($users as $user) {
                $email = (new Email())
                    ->from(new Address('hola@frikiradar.com', 'frikiradar'))
                    ->to(new Address($user->getEmail(), $user->getUsername()))
                    ->subject('¡frikiradar te extraña 💔!')
                    ->html($this->twig->render(
                        "emails/reminder.html.twig",
                        [
                            'username' => $user->getUsername(),
                            'code' => $user->getMailingCode(),
                        ]
                    ));

                try {
                    $mailer->send($email);
                    $this->o->writeln("Email enviado a " . $user->getUsername() . " (" . $user->getEmail() . ")");
                } catch (TransportExceptionInterface $e) {
                    $this->o->writeln("Error al enviar el email a " . $user->getUsername() . " (" . $user->getEmail() . ")");
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
