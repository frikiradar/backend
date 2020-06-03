<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\Chat;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method Chat|null find($id, $lockMode = null, $lockVersion = null)
 * @method Chat|null findOneBy(array $criteria, array $orderBy = null)
 * @method Chat[]    findAll()
 * @method Chat[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ChatRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
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

    public function getChat(User $fromUser, User $toUser, $read = false, $page = 1, $lastId = 0)
    {
        $limit = 50;
        $offset = ($page - 1) * $limit;

        return $this->createQueryBuilder('c')
            ->andWhere($toUser->getUsername() == 'frikiradar' ? 'c.fromuser = 1 AND c.touser IS NULL' : 'c.fromuser = :fromUser AND c.touser = :toUser')
            ->orWhere('c.fromuser = :toUser AND c.touser = :fromUser')
            ->andWhere($read == true ? '1=1' : 'c.timeRead IS NULL')
            ->andWhere('c.id > :lastId')
            ->setParameter('fromUser', $fromUser->getId())
            ->setParameter('toUser', $toUser->getId())
            ->setParameter('lastId', $lastId)
            ->orderBy('c.id', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function isChat(User $fromUser, User $toUser)
    {
        return $this->createQueryBuilder('c')
            ->select('count(c.id)')
            ->where('c.fromuser = :fromUser AND c.touser = :toUser')
            ->orWhere('c.fromuser = :toUser AND c.touser = :fromUser')
            ->setParameter('fromUser', $fromUser->getId())
            ->setParameter('toUser', $toUser->getId())
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getChatUsers(User $fromUser)
    {
        $dql = "SELECT IDENTITY(c.fromuser) fromuser, IDENTITY(c.touser) touser, c.text text, c.timeCreation time_creation FROM App:Chat c WHERE c.id IN(SELECT MAX(d.id) FROM App:Chat d WHERE (d.fromuser = :id OR d.touser = :id) OR (d.fromuser = 1 AND (d.touser = :id OR d.touser IS NULL)) AND d.text IS NOT NULL GROUP BY d.conversationId) ORDER BY c.id DESC";

        $query = $this->getEntityManager()->createQuery($dql)->setParameter('id', $fromUser->getId());
        return $query->getResult();
    }

    public function countUnread(User $toUser)
    {
        return $this->createQueryBuilder('c')
            ->select('count(c.id)')
            ->where('c.touser = :toUser')
            ->andWhere('c.timeRead IS NULL')
            ->setParameter('toUser', $toUser->getId())
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countUnreadUser(User $toUser, User $fromUser)
    {
        return $this->createQueryBuilder('c')
            ->select('count(c.id)')
            ->where('c.touser = :toUser')
            ->andWhere('c.fromuser = :fromUser')
            ->andWhere('c.timeRead IS NULL')
            ->setParameter('toUser', $toUser->getId())
            ->setParameter('fromUser', $fromUser->getId())
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function deleteChatUser(User $toUser, User $fromUser)
    {
        return $this->createQueryBuilder('c')
            ->delete()
            ->where('c.touser = :toUser AND c.fromuser = :fromUser')
            ->orWhere('c.fromuser = :toUser AND c.touser = :fromUser')
            ->setParameter('toUser', $toUser->getId())
            ->setParameter('fromUser', $fromUser->getId())
            ->getQuery()
            ->execute();
    }
}
