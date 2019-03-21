<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\Chat;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\Mercure\Publisher;
use Symfony\Component\Mercure\Update;

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
        $dql = "SELECT IDENTITY(c.fromuser) fromuser, IDENTITY(c.touser) touser, c.text text, c.timeCreation time_creation
            FROM App:Chat c LEFT JOIN App:Chat d WITH (c.conversationId = d.conversationId AND c.id < d.id)
            WHERE d.id IS NULL AND (c.fromuser = :id OR c.touser = :id) ORDER BY c.id DESC";

        $query = $this->getEntityManager()->createQuery($dql)->setParameter('id', $fromUser->getId());
        return $query->getResult();
    }

    public function sendMessage($fromUserId, $toUserId, $text, Publisher $publisher)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        $newChat = new Chat();
        $fromUser = $em->getRepository('App:User')->findOneBy(array('id' => $fromUserId));
        $toUser = $em->getRepository('App:User')->findOneBy(array('id' => $toUserId));
        $newChat->setTouser($toUser);
        $newChat->setFromuser($fromUser);

        $min = min($newChat->getFromuser()->getId(), $newChat->getTouser()->getId());
        $max = max($newChat->getFromuser()->getId(), $newChat->getTouser()->getId());

        $conversationId = $min . "_" . $max;

        $newChat->setText($text);
        $newChat->setTimeCreation(new \DateTime);
        $newChat->setConversationId($conversationId);
        $em->merge($newChat);
        $em->flush();

        $chat = [
            "fromuser" => $newChat->getFromuser()->getId(),
            "touser" => $newChat->getTouser()->getId(),
            "text" => $newChat->getText(),
            "time_creation" => $newChat->getTimeCreation()
        ];

        $update = new Update($conversationId, $serializer->serialize($chat, "json"));
        $publisher($update);

        $title = $fromUser->getUsername();

        $em->getRepository('App:Notification')->push($fromUser, $toUser, $title, $text);

        return $chat;
    }
}
