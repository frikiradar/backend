<?php

namespace App\Service;

use Symfony\Component\Console\Style\SymfonyStyle;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment;

class CronCommandService
{
    protected $io;
    protected $o;

    private $mailer;
    private $em;
    private $twig;

    public function __construct(EntityManagerInterface $entityManager, \Swift_Mailer $mailer, Environment $twig)
    {
        $this->em = $entityManager;
        $this->mailer = $mailer;
        $this->twig = $twig;
    }

    public function setIo($i, $o)
    {
        $this->io = new SymfonyStyle($i, $o);
        $this->o = $o;
    }

    public function reminder($days)
    {
        $dql = "SELECT u FROM App:User u WHERE u.last_login < DATE_SUB(CONCAT(CURDATE(), ' ',CURTIME()), INTERVAL 120 DAY)
        AND active IS true AND mailing IS true";

        $user = $this->em->getRepository('App:User')->findOneBy(array('id' => 2));
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
            throw new \RuntimeException('Unable to send email');
        }
        /*$process = new Process(
            'php bin/console abo:crons ' . $commandName . ' --thread=' . $thread . ' --limit=' . $limit . ' --offset=' . $offset
        );
        $process->setWorkingDirectory(getcwd() . "../");
        $process->disableOutput();
        $process->start();*/
    }

    public function gift($days, $credits)
    { }
}
