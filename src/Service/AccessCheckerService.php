<?php
// src/Service/AccessCheckerService.php
namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class AccessCheckerService extends AbstractController
{
    private $em;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->em = $entityManager;
    }

    public function checkAccess($user = false)
    {
        $now = new \DateTime;

        if (!$user instanceof User) {
            /** @var \App\Entity\User $user */
            $user = $this->getUser();
        }

        if ($this->em->getRepository(\App\Entity\User::class)->isBannedIpOrDevice($user)) {
            $user->setBanned(true);
            $user->setBanReason('Multicuenta no autorizada. La cuenta original ha sido baneada.');
            $user->setBanEnd(null);

            $this->em->persist($user);
            $this->em->flush();

            throw new HttpException(401, "Banned account.");
        }

        if (!empty($user) && $user->isBanned() !== false) {
            if ($user->getBanEnd() > $now || is_null($user->getBanEnd())) {
                throw new HttpException(401, "Banned account.");
            }
        }

        if (!empty($user) && !$user->isActive()) {
            throw new HttpException(401, "Disabled account.");
        }

        $age = $now->diff($user->getBirthday())->y;
        if ($age < 18) {
            try {
                $reason = 'Debido a las nuevas políticas de frikiradar es necesario tener al menos 18 años para utilizar la aplicación.';
                $days = (18 - $age) * 365;
                $hours = null;
                $this->em->getRepository(\App\Entity\User::class)->banUser($user, $reason, $days, $hours);
            } catch (Exception $ex) {
                // Si falla por cualquier motivo el baneo igualmente no le dejamos acceder.
                throw new HttpException(401, "Eres menor de 18 años");
            }
        }

        if (
            strpos(strtolower($user->getDescription()), 'parece') !== false &&
            preg_match("/tengo\s(\d+)\saños/", strtolower($user->getDescription()), $matches)
        ) {
            if ($user->isBanned() === false && $matches[1] < 18) {
                try {
                    $age = $matches[1];
                    // Baneamos la cuenta
                    $reason = 'Eres menor de edad, para usar frikiradar es necesario tener al menos 18 años.';
                    $days = (18 - $age) * 365;
                    $hours = null;
                    $this->em->getRepository(\App\Entity\User::class)->banUser($user, $reason, $days, $hours);
                } catch (Exception $ex) {
                    // Si falla por cualquier motivo el baneo igualmente no le dejamos acceder.
                    throw new HttpException(401, "Eres menor de 18 años");
                }
            }
        }
    }
}
