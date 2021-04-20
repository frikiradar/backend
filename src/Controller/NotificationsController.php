<?php
// src/Controller/ChatController.php
namespace App\Controller;

use App\Entity\Chat;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Service\NotificationService;
use App\Service\RequestService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Class NotificationsController
 *
 * @Route(path="/api")
 */
class NotificationsController extends AbstractController
{
    public function __construct(SerializerInterface $serializer, EntityManagerInterface $entityManager, RequestService $request, NotificationService $notification)
    {
        $this->serializer = $serializer;
        $this->em = $entityManager;
        $this->request = $request;
        $this->notification = $notification;
    }


    /**
     * @Route("/v1/notifications", name="get_notifications", methods={"GET"})
     */
    public function getNotifications()
    {
        $cache = new FilesystemAdapter();
        $user = $this->getUser();
        try {
            $notificationsCache = $cache->getItem('users.notifications.' . $user->getId());
            if (!$notificationsCache->isHit()) {
                $notificationsCache->expiresAfter(60);

                // $countGeneral = $this->em->getRepository('App:Notification')->countUnread($user);
                $countGeneral = 0;
                $countChats = $this->em->getRepository('App:Chat')->countUnread($user);
                $notifications = ["notifications" => (int) $countGeneral, "chats" => (int) $countChats];

                $notificationsCache->set($notifications);
                $cache->save($notificationsCache);

                $user = $this->getUser();
                $user->setLastLogin();
                $this->em->persist($user);
                $this->em->flush();
            } else {
                $notifications = $notificationsCache->get();
            }

            return new Response($this->serializer->serialize($notifications, "json"));
        } catch (Exception $ex) {
            throw new HttpException(400, "No se pueden obtener los contadores de notificaciones - Error: {$ex->getMessage()}");
        }
    }
}
