<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\Chat;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Chat|null find($id, $lockMode = null, $lockVersion = null)
 * @method Chat|null findOneBy(array $criteria, array $orderBy = null)
 * @method Chat[]    findAll()
 * @method Chat[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ChatRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Chat::class);
    }

    // /**
    //  * @return Chat[] Returns an array of Chat objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('c.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Chat
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */

    public function getChat(User $fromUser, User $toUser)
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.fromuser = :fromUser AND c.touser = :toUser')
            ->orWhere('c.fromuser = :toUser AND c.touser = :fromUser')
            ->setParameter('fromUser', $fromUser->getId())
            ->setParameter('toUser', $toUser->getId())
            ->orderBy('c.id', 'ASC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();
    }

    public function getChatUsers(User $fromUser)
    {
        $dql = "SELECT c FROM App:Chat c WHERE c.fromuser = :id OR c.touser = :id
        GROUP BY c.fromuser, c.touser
        ORDER BY MAX(c.timeCreation) DESC";

        $query = $this->getEntityManager()->createQuery($dql)->setParameter('id', $fromUser->getId());

        return $query->getResult();
    }
}
