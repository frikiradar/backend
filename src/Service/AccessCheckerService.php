<?php
// src/Service/AccessCheckerService.php
namespace App\Service;

use App\Entity\User;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class AccessCheckerService extends AbstractController
{
    public function checkAccess($user = false)
    {
        if (!$user instanceof User) {
            $user = $this->getUser();
        }

        if ($user->getBanned()) {
            $now = new \DateTime;
            if ($user->getBanEnd() > $now || is_null($user->getBanEnd())) {
                throw new HttpException(401, "Tu cuenta está baneada");
            }
        }
    }
}
