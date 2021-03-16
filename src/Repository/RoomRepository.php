<?php

namespace App\Repository;

use App\Entity\Room;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Room|null find($id, $lockMode = null, $lockVersion = null)
 * @method Room|null findOneBy(array $criteria, array $orderBy = null)
 * @method Room[]    findAll()
 * @method Room[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RoomRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Room::class);
    }

    // /**
    //  * @return Room[] Returns an array of Room objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('r.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Room
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */

    public function findVisibleRooms()
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.visible = TRUE')
            ->orderBy('r.id', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    public function getLastMessages($slugs, User $fromUser)
    {
        $dql = "SELECT MAX(c.id) last_message, c.conversationId FROM App:Chat c WHERE c.conversationId IN (:slugs) AND c.fromuser <> :id GROUP BY c.conversationId";
        $query = $this->getEntityManager()
            ->createQuery($dql)
            ->setParameter('slugs', $slugs)
            ->setParameter('id', $fromUser->getId());
        return $query->getResult();
    }
}
