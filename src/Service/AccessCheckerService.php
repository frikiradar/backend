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
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->em = $entityManager;
    }

    public function checkAccess($user = false)
    {
        if (!$user instanceof User) {
            $user = $this->getUser();
        }
        $now = new \DateTime;
        if (!empty($user) && $user->getBanned() !== false) {
            $now = new \DateTime;
            if ($user->getBanEnd() > $now || is_null($user->getBanEnd())) {
                throw new HttpException(401, "Banned account.");
            }
        }

        if (
            strpos(strtolower($user->getDescription()), 'parece') !== false &&
            preg_match("/tengo\s(\d+)\saños/", strtolower($user->getDescription()), $matches)
        ) {
            if ($user->getBanned() === false && $matches[1] < 18) {
                try {
                    $age = $matches[1];
                    // Baneamos la cuenta
                    $reason = 'Eres menor de edad, para usar FrikiRadar es necesario tener al menos 18 años.';
                    $days = (18 - $age) * 365;
                    $hours = null;
                    $this->em->getRepository('App:user')->banUser($user, $reason, $days, $hours);
                } catch (Exception $ex) {
                    // Si falla por cualquier motivo el baneo igualmente no le dejamos acceder.
                    throw new HttpException(401, "Eres menor de 18 años");
                }
            }
        }
    }
}
