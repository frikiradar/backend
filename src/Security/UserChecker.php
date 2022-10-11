<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker extends AbstractController implements UserCheckerInterface
{
    public function __construct(\Swift_Mailer $mailer, EntityManagerInterface $entityManager)
    {
        $this->mailer = $mailer;
        $this->em = $entityManager;
    }

    public function checkPreAuth(UserInterface $user)
    {
        if (!$user instanceof User) {
            return;
        }

        if ($user->getBanned() !== false) {
            $now = new \DateTime;
            /*if (is_null($user->getBanEnd())) {
                $data = [
                    'message' => 'Banned account.',
                    'reason' => $user->getBanReason(),
                    'end' => $user->getBanEnd() ?: null
                ];

                $text = json_encode($data);
                throw new CustomUserMessageAuthenticationException($text);
            } else*/
            if (!is_null($user->getBanEnd()) && $user->getBanEnd() <= $now) {
                $user->setBanned(false);
                $user->setBanReason(null);
                $user->setBanEnd(null);
                $this->em->persist($user);
                $this->em->flush();
            }
        }
    }

    public function checkPostAuth(UserInterface $user)
    {
        if (!$user instanceof User) {
            return;
        }

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
                    $this->renderView(
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
