<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\Chat;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Chat|null find($id, $lockMode = null, $lockVersion = null)
 * @method Chat|null findOneBy(array $criteria, array $orderBy = null)
 * @method Chat[]    findAll()
 * @method Chat[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ChatRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, EntityManagerInterface $entityManager)
    {
        parent::__construct($registry, Chat::class);
        $this->em = $entityManager;
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

    public function getChat(User $fromUser, User $toUser, $read = false, $page = 1, $lastId = 0, $banned = false)
    {
        $limit = 15;
        $offset = ($page - 1) * $limit;

        $dql = $this->createQueryBuilder('c');
        if ($toUser->getId() == 1 && !$banned) {
            $dql->andWhere('c.fromuser = 1 AND c.touser IS NULL');
        } else {
            $dql->andWhere('c.fromuser = :fromUser AND c.touser = :toUser');
        }

        return $dql->orWhere('c.fromuser = :toUser AND c.touser = :fromUser')
            ->andWhere($read == true ? '1=1' : 'c.time_read IS NULL')
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

    public function getRoomChat(string $slug, $page = 1)
    {
        $limit = 15;
        $offset = ($page - 1) * $limit;

        return $this->createQueryBuilder('c')
            ->where('c.conversationId = :slug')
            ->orderBy('c.id', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->setParameter('slug', $slug)
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
        $dql = "SELECT IDENTITY(c.fromuser) fromuser, IDENTITY(c.touser) touser, c.text text, c.time_creation time_creation, c.time_read time_read, c.conversationId conversationId FROM App:Chat c WHERE c.id IN(SELECT MAX(d.id) FROM App:Chat d WHERE (d.fromuser = :id OR d.touser = :id) OR (d.fromuser = 1 AND (d.touser = :id OR d.touser IS NULL)) AND d.text IS NOT NULL GROUP BY d.conversationId) ORDER BY c.id DESC";

        $query = $this->getEntityManager()->createQuery($dql)->setParameter('id', $fromUser->getId());
        $chats = $query->getResult();

        foreach ($chats as $key => $chat) {
            if (strpos($chat["conversationId"], '_') !== false || $chat["fromuser"] == 1) {
                if ($chat["fromuser"] == $fromUser->getId()) {
                    $userId = $chat["touser"];
                } elseif (!is_null($chat["fromuser"])) {
                    $userId = $chat["fromuser"];
                } else {
                    $userId = $chat["touser"];
                }
                $dql = "SELECT u FROM App:User u WHERE u.id = :id";
                $query = $this->getEntityManager()->createQuery($dql)->setParameter('id', $userId);
                $user = $query->getOneOrNullResult();
                if ($user) {
                    $chats[$key]['count'] = intval($this->countUnreadUser($fromUser, $user));
                    $blocked = !empty($this->em->getRepository('App:BlockUser')->isBlocked($fromUser, $user)) ? true : false;

                    if (!$blocked) {
                        $chats[$key]['user'] = [
                            'id' => $userId,
                            'username' => $user->getUsername(),
                            'name' => $user->getName(),
                            'avatar' =>  $user->getAvatar() ?: null,
                            'thumbnail' => $user->getThumbnail() ?: null
                        ];
                    } else {
                        $chats[$key]['user'] = [
                            'id' => $userId,
                            'username' => 'Usuario desconocido',
                            'name' => 'Usuario desconocido',
                            'avatar' => null,
                            'thumbnail' => null
                        ];
                    }
                }
            } else {
                unset($chats[$key]);
            }
        }



        return array_values($chats);
    }

    public function countUnread(User $toUser)
    {
        return $this->createQueryBuilder('c')
            ->select('count(c.id)')
            ->where('c.touser = :toUser')
            ->andWhere('c.time_read IS NULL')
            ->andWhere("c.conversationId LIKE '%_%'")
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
            ->andWhere('c.time_read IS NULL')
            ->setParameter('toUser', $toUser->getId())
            ->setParameter('fromUser', $fromUser->getId())
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function deleteChatUser(User $toUser, User $fromUser)
    {
        $chat = $this->createQueryBuilder('c')
            ->where('c.touser = :toUser AND c.fromuser = :fromUser')
            ->orWhere('c.fromuser = :toUser AND c.touser = :fromUser')
            ->setParameter('toUser', $toUser->getId())
            ->setParameter('fromUser', $fromUser->getId())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $conversationId = $chat->getConversationId();
        $folder = "/var/www/vhosts/frikiradar.com/app.frikiradar.com/images/chat/" . $conversationId . "/";
        foreach (glob($folder . "*.*") as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        if (file_exists($folder)) {
            rmdir($folder);
        }

        return $this->createQueryBuilder('c')
            ->delete()
            ->where('c.touser = :toUser AND c.fromuser = :fromUser')
            ->orWhere('c.fromuser = :toUser AND c.touser = :fromUser')
            ->setParameter('toUser', $toUser->getId())
            ->setParameter('fromUser', $fromUser->getId())
            ->getQuery()
            ->execute();
    }

    public function deleteChatsUser(User $user)
    {
        $chats = $this->createQueryBuilder('c')
            ->where('c.touser = :user OR c.fromuser = :user')
            ->andWhere('c.image IS NOT NULL OR c.audio IS NOT NULL')
            ->setParameter('user', $user->getId())
            ->getQuery()
            ->getResult();

        $ids = [];
        foreach ($chats as $chat) {
            if (!in_array($chat->getConversationId(), $ids)) {
                $ids[] = $chat->getConversationId();
            }
        }

        if (!empty($ids)) {
            foreach ($ids as $id) {
                $folder = "/var/www/vhosts/frikiradar.com/app.frikiradar.com/images/chat/" . $id . "/";
                foreach (glob($folder . "*.*") as $file) {
                    if (file_exists($file)) {
                        unlink($file);
                    }
                }
                if (file_exists($folder)) {
                    rmdir($folder);
                }
            }

            return $this->createQueryBuilder('c')
                ->delete()
                ->where('c.touser = :user OR c.fromuser = :user')
                ->setParameter('user', $user->getId())
                ->getQuery()
                ->execute();
        } else {
            return false;
        }
    }
}
