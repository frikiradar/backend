<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Doctrine\ORM\EntityManagerInterface;

class JWTAuthentication
{
    private $mailer;

    /**
     * @param RequestStack $requestStack
     */
    public function __construct(ContainerInterface $container, \Swift_Mailer $mailer, EntityManagerInterface $entityManager)
    {
        $this->container = $container;
        $this->mailer = $mailer;
        $this->em = $entityManager;
    }

    public function onAuthenticationSuccessResponse(AuthenticationSuccessEvent $event)
    {
        $user = $event->getUser();
        $user->setLastLogin();
        $user->setLastIP();
        $user->setNumLogins(($user->getNumLogins() ?: 0) + 1);

        if (!$user->getActive() && !$user->getVerificationCode()) {
            //Generamos y enviamos por email
            $user->setVerificationCode();

            $message = (new \Swift_Message($user->getVerificationCode() . ' es tu código de activación de FrikiRadar'))
                ->setFrom(['noreply@frikiradar.app' => 'FrikiRadar'])
                ->setTo($user->getEmail())
                ->setBody(
                    $this->templating->render(
                        "emails/registration.html.twig",
                        [
                            'username' => $user->getUsername(),
                            'code' => $user->getVerificationCode()
                        ]
                    ),
                    'text/html'
                );

            $this->mailer->send($message);
        }

        $this->em->persist($user);
        $this->em->flush();
    }
}
