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

    public function getChat(User $fromUser, User $toUser, $read = false, $page = 1)
    {
        $limit = 50;
        $offset = ($page - 1) * $limit;

        return $this->createQueryBuilder('c')
            ->andWhere('c.fromuser = :fromUser AND c.touser = :toUser')
            ->orWhere('c.fromuser = :toUser AND c.touser = :fromUser')
            ->andWhere($read == true ? '1=1' : 'c.timeRead IS NULL')
            ->setParameter('fromUser', $fromUser->getId())
            ->setParameter('toUser', $toUser->getId())
            ->orderBy('c.id', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getChatUsers(User $fromUser)
    {
        $dql = "SELECT IDENTITY(c.fromuser) fromuser, IDENTITY(c.touser) touser, c.text text, c.timeCreation time_creation
            FROM App:Chat c LEFT JOIN App:Chat d WITH (c.conversationId = d.conversationId AND c.id < d.id)
            WHERE d.id IS NULL AND (c.fromuser = :id OR c.touser = :id) ORDER BY c.id DESC";

        $query = $this->getEntityManager()->createQuery($dql)->setParameter('id', $fromUser->getId());
        return $query->getResult();
    }

    public function markAllAsRead(User $fromUser, User $toUser)
    {
        $em = $this->getEntityManager();
        $chats = $this->findBy(array('fromuser' => $fromUser->getId(), 'touser' => $toUser->getId(), 'timeRead' => null));
        foreach ($chats as $chat) {
            $chat->setTimeRead(new \DateTime);
            $em->merge($chat);
        }
        $em->flush();
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
}
