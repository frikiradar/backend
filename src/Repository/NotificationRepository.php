<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\Notification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Kreait\Firebase;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as PushNotification;
use Kreait\Firebase\Messaging\AndroidConfig;

/**
 * @method Notification|null find($id, $lockMode = null, $lockVersion = null)
 * @method Notification|null findOneBy(array $criteria, array $orderBy = null)
 * @method Notification[]    findAll()
 * @method Notification[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    // /**
    //  * @return Notification[] Returns an array of Notification objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('n.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Notification
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */

    public function push(User $fromUser, User $toUser, string $title, string $text, string $url, string $type)
    {
        $em = $this->getEntityManager();
        $isNewNotification = false;

        // TODO: quitar las notificaciones de chat

        $newNotification = $this->findOneBy([
            'fromUser' => $fromUser,
            'toUser' => $toUser,
            'title' => $title,
            'type' => $type
        ]);

        if (is_null($newNotification) || $type == "chat") {
            $isNewNotification = true;
            $newNotification = new Notification();
            $newNotification->setFromUser($fromUser);
            $newNotification->setToUser($toUser);
            $newNotification->setTitle($title);
        }

        $newNotification->setText($text);
        $newNotification->setTimeCreation(new \DateTime);
        $newNotification->setUrl($url);
        $newNotification->setType($type);
        $newNotification->setViewed(false);

        $em->merge($newNotification);
        $em->flush();

        if ($isNewNotification) {
            $devices = $toUser->getDevices();
            foreach ($devices as $device) {
                if ($device->getActive() && !is_null($device->getToken())) {
                    $notification = PushNotification::create($title, $text);
                    $data = [
                        'fromUser' => (string)$fromUser->getId(),
                        'toUser' => (string)$toUser->getId(),
                        'url' => $url
                    ];

                    $config = AndroidConfig::fromArray([
                        'ttl' => '3600s',
                        'priority' => 'normal',
                        'notification' => [
                            'title' => $title,
                            'body' => $text,
                            'sound' => "bipbip",
                            'tag' => $type . '_' . $title,
                            'click_action' => "FCM_PLUGIN_ACTIVITY"
                        ],
                    ]);

                    $message = CloudMessage::withTarget('token', $device->getToken())
                        ->withNotification($notification) // optional
                        ->withData($data)
                        ->withAndroidConfig($config);

                    $firebase = (new Firebase\Factory())->create();
                    $messaging = $firebase->getMessaging();
                    @$messaging->send($message);
                }
            }
        }
    }

    public function getNotifications(User $toUser)
    {
        $dql = "SELECT IDENTITY(n.fromUser) fromuser, n.text, n.title, n.timeCreation time_creation,
            n.url, n.viewed FROM App:Notification n
            WHERE n.toUser = :id ORDER BY n.id DESC";

        $query = $this->getEntityManager()->createQuery($dql)->setParameter('id', $toUser->getId());
        return $query->getResult();
    }
}
