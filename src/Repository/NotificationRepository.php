<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\Device;
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

    public function push(User $fromUser, User $toUser, string $title, string $text, string $url)
    {
        $devices = $toUser->getDevices();
        $em = $this->getEntityManager();

        $newNotification = new Notification();
        $newNotification->setFromUser($fromUser);
        $newNotification->setToUser($toUser);
        $newNotification->setTitle($title);
        $newNotification->setText($text);
        $newNotification->setTimeCreation(new \DateTime);
        $newNotification->setUrl($url);
        $newNotification->setType("chat");
        $newNotification->setViewed(false);

        $em->persist($newNotification);
        $em->flush();

        foreach ($devices as $device) {
            $notification = PushNotification::create($title, $text);
            $data = [
                'fromUser' => (string)$fromUser->getId(),
                'toUser' => (string)$toUser->getId()
            ];

            $config = AndroidConfig::fromArray([
                'ttl' => '3600s',
                'priority' => 'normal',
                'notification' => [
                    'title' => $title,
                    'body' => $text,
                    'click_action' => "FCM_PLUGIN_ACTIVITY"
                ],
            ]);

            $message = CloudMessage::withTarget('token', $device->getToken())
                ->withNotification($notification) // optional
                ->withData($data)
                ->withAndroidConfig($config);

            $firebase = (new Firebase\Factory())->create();
            $messaging = $firebase->getMessaging();
            $messaging->send($message);
        }
    }
}
