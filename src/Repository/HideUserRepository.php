<?php

namespace App\Repository;

use App\Entity\HideUser;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method HideUser|null find($id, $lockMode = null, $lockVersion = null)
 * @method HideUser|null findOneBy(array $criteria, array $orderBy = null)
 * @method HideUser[]    findAll()
 * @method HideUser[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class HideUserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HideUser::class);
    }

    // /**
    //  * @return HideUser[] Returns an array of HideUser objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('h.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?HideUser
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */

    public function getHideUsers(User $user)
    {
        $dql = "SELECT u.id, u.name, u.avatar            
            FROM App:User u WHERE u.id IN
            (SELECT IDENTITY(b.hide_user) FROM App:HideUser b WHERE b.from_user = :fromUser)";
        $query = $this->getEntityManager()->createQuery($dql)->setParameter("fromUser", $user);

        return $query->getResult();
    }

    public function isHide(User $fromUser, User $hideUser)
    {
        return $this->createQueryBuilder('b')
            ->where('b.from_user = :fromUser AND b.hide_user = :hideUser')
            ->setParameters([
                'fromUser' => $fromUser,
                'hideUser' => $hideUser
            ])
            ->getQuery()
            ->getOneOrNullResult();
    }
}
