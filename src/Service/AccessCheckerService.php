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

        if ($this->em->getRepository('App:User')->isBannedIpOrDevice($user)) {
            $user->setBanned(true);
            $user->setBanReason('Multicuenta no autorizada. La cuenta original ha sido baneada.');
            $user->setBanEnd(null);

            $this->em->persist($user);
            $this->em->flush();

            throw new HttpException(401, "Banned account.");
        }

        if (!empty($user) && $user->getBanned() !== false) {
            $now = new \DateTime;
            if ($user->getBanEnd() > $now || is_null($user->getBanEnd())) {
                throw new HttpException(401, "Banned account.");
            }
        }

        if (!empty($user) && !$user->getActive()) {
            throw new HttpException(401, "Disabled account.");
        }
    }
}
