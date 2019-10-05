<?php

namespace App\Service;

use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Config\Definition\Exception\Exception;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment;

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
        Environment $twig,
        NotificationService $notification
    ) {
        $this->em = $entityManager;
        $this->mailer = $mailer;
        $this->twig = $twig;
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
            $users = $this->em->getRepository('App:User')->getUsersByLastLogin($days);
            foreach ($users as $user) {
                $message = (new \Swift_Message('¡FrikiRadar te extraña 💔!'))
                    ->setFrom(['hola@frikiradar.com' => 'FrikiRadar'])
                    ->setTo($user->getEmail())
                    ->setBody(
                        $this->twig->render(
                            "emails/reminder.html.twig",
                            [
                                'username' => $user->getUsername(),
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

    public function gift($credits)
    {
        if ($credits) {
            $fromUser = $this->em->getRepository('App:User')->findOneBy(array('username' => 'frikiradar'));
            $creditText = $credits . " " . ($credits > 1 ? "Créditos" : "Crédito");

            $users = $this->em->getRepository('App:User')->getUsersWithoutCredits();

            foreach ($users as $user) {
                try {
                    // Le añadimos $credits créditos
                    $user->setCredits($user->getCredits() + $credits);
                    $this->em->merge($user);

                    $title = "🎁 " . $creditText;
                    $text = "Te hemos regalado " . $creditText . " ¡Esperamos que lo disfrutes!";
                    $url = "/tabs/radar";
                    $this->notification->push($fromUser, $user, $title, $text, $url, "credits");

                    $this->o->writeln("Regalo $creditText enviado a " . $user->getUsername());
                } catch (Exception $ex) {
                    $this->o->writeln("Error al enviar el regalo $creditText a " . $user->getUsername() . " - Error: {$ex->getMessage()}");
                }
            }

            $this->em->flush();
        } else {
            $this->o->writeln("No se pueden regalar 0 créditos");
        }
    }
}
