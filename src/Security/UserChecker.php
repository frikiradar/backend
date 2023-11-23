<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker extends AbstractController implements UserCheckerInterface
{
    private $em;
    private $mailer;

    public function __construct(EntityManagerInterface $entityManager, MailerInterface $mailer)
    {
        $this->em = $entityManager;
        $this->mailer = $mailer;
    }

    public function checkPreAuth(UserInterface $user)
    {
        if (!$user instanceof User) {
            return;
        }

        if ($user->isBanned() !== false) {
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

    public function checkPostAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        $user->setLastLogin();
        $user->setLastIP();
        $user->setNumLogins(($user->getNumLogins() ?: 0) + 1);

        if (!$user->isActive() && !$user->getVerificationCode()) {
            //Generamos y enviamos por email
            $user->setVerificationCode();

            $email = (new Email())
                ->from(new Address('hola@frikiradar.com', 'FrikiRadar'))
                ->to(new Address($user->getEmail(), $user->getUsername()))
                ->subject($user->getVerificationCode() . ' es tu código de activación de FrikiRadar')
                ->html($this->renderView(
                    "emails/registration.html.twig",
                    [
                        'username' => $user->getUserIdentifier(),
                        'code' => $user->getVerificationCode()
                    ]
                ));

            $this->mailer->send($email);
        }

        $this->em->persist($user);
        $this->em->flush();
    }
}
