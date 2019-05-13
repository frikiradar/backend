<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class JWTAuthentication
{
    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @param RequestStack $requestStack
     */
    public function __construct(RequestStack $requestStack, ContainerInterface $container)
    {
        $this->requestStack = $requestStack;
        $this->container = $container;
    }

    public function onAuthenticationSuccessResponse(AuthenticationSuccessEvent $event, \Swift_Mailer $mailer)
    {
        $em = $this->container->get('doctrine')->getManager();
        $user = $event->getUser();
        $user->setLastLogin();
        $user->setLastIP();
        $user->setNumLogins(($user->getNumLogins() ?: 0) + 1);

        if (!$user->getActive() && !$user->getVerificationCode()) {
            //Generamos y enviamos por email
            $user->setVerificationCode();

            $message = (new \Swift_Message($user->getVerificationCode() . ' es tu código de activación de FrikiRadar'))
                ->setFrom(['hola@frikiradar.com' => 'FrikiRadar'])
                ->setTo($user->getEmail())
                ->setBody(
                    $this->renderView(
                        "emails/registration.html.twig",
                        [
                            'username' => $user->getUsername(),
                            'code' => $user->getVerificationCode()
                        ]
                    ),
                    'text/html'
                );

            $mailer->send($message);
        }

        $em->merge($user);
        $em->flush();
    }
}
