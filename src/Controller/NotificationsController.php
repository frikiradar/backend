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
use Symfony\Component\HttpFoundation\JsonResponse;
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
                $countGeneral = $this->em->getRepository('App:Notification')->countUnread($user);
                $countChats = $this->em->getRepository('App:Chat')->countUnread($user);
                $notifications = ["notifications" => (int) $countGeneral, "chats" => (int) $countChats];

                $notificationsCache->set($notifications);
                $cache->save($notificationsCache);

                $user = $this->getUser();
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

    /**
     * @Route("/v1/notifications-list", name="notifications_list", methods={"GET"})
     */
    public function getNotificationsList()
    {
        $cache = new FilesystemAdapter();
        $user = $this->getUser();
        try {
            $notificationsCache = $cache->getItem('users.notifications-list.' . $user->getId());
            if (!$notificationsCache->isHit()) {
                $notifications = $this->em->getRepository('App:Notification')->findBy(['user' => $user], ['id' => 'DESC'], 25);
                $notifications = $this->serializer->serialize($notifications, "json", ['groups' => ['notification']]);
                $notificationsCache->set($notifications);
                $cache->save($notificationsCache);
            } else {
                $notifications = $notificationsCache->get();
            }

            return new Response($notifications);
        } catch (Exception $ex) {
            throw new HttpException(400, "No se pueden obtener las notificaciones - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Route("/v1/read-notification/{id}", name="read_notification", methods={"GET"})
     */
    public function readNotification(int $id)
    {
        $cache = new FilesystemAdapter();
        $user = $this->getUser();
        try {
            $notification = $this->em->getRepository('App:Notification')->findOneBy(array('id' => $id));
            if ($notification->getUser()->getId() == $user->getId()) {
                $notification->setTimeRead(new \DateTime);
                $this->em->persist($notification);
                $this->em->flush();

                $cache->deleteItem('users.notifications.' . $user->getId());
                $cache->deleteItem('users.notifications-list.' . $user->getId());

                return new Response($this->serializer->serialize($notification, "json", ['groups' => 'notification']));
            } else {
                throw new HttpException(401, "No se puede marcar como leída la notificación de otro usuario");
            }
        } catch (Exception $ex) {
            throw new HttpException(400, "No se puede marcar como leída la notificación - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Route("/v1/unread-notification/{id}", name="unread_notification", methods={"GET"})
     */
    public function unreadNotification(int $id)
    {
        $cache = new FilesystemAdapter();
        $user = $this->getUser();
        try {
            $notification = $this->em->getRepository('App:Notification')->findOneBy(array('id' => $id));
            if ($notification->getUser()->getId() == $user->getId()) {
                $notification->setTimeRead(null);
                $this->em->persist($notification);
                $this->em->flush();

                $cache->deleteItem('users.notifications.' . $user->getId());
                $cache->deleteItem('users.notifications-list.' . $user->getId());

                return new Response($this->serializer->serialize($notification, "json", ['groups' => 'notification']));
            } else {
                throw new HttpException(401, "No se puede desmarcar como leída la notificación de otro usuario");
            }
        } catch (Exception $ex) {
            throw new HttpException(400, "No se puede desmarcar como leída la notificación - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Route("/v1/remove-notification/{id}", name="remove_notification", methods={"DELETE"})
     */
    public function removeNotification(int $id)
    {
        $cache = new FilesystemAdapter();
        $user = $this->getUser();
        try {
            $notification = $this->em->getRepository('App:Notification')->findOneBy(array('id' => $id));
            if ($notification->getUser()->getId() == $user->getId()) {
                $this->em->remove($notification);
                $this->em->flush();

                $cache->deleteItem('users.notifications.' . $user->getId());
                $cache->deleteItem('users.notifications-list.' . $user->getId());

                $data = [
                    'code' => 200,
                    'message' => "Notificación eliminada correctamente",
                ];
                return new JsonResponse($data, 200);
            } else {
                throw new HttpException(401, "No se puede eliminar la notificación de otro usuario");
            }
        } catch (Exception $ex) {
            throw new HttpException(400, "No se puede eliminar la notificación - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Route("/v1/remove-notifications", name="remove_notifications", methods={"DELETE"})
     */
    public function removeNotifications()
    {
        $cache = new FilesystemAdapter();
        $user = $this->getUser();
        try {
            $notifications = $user->getNotifications();
            foreach ($notifications as $notification) {
                $this->em->remove($notification);
            }
            $this->em->flush();

            $cache->deleteItem('users.notifications.' . $user->getId());
            $cache->deleteItem('users.notifications-list.' . $user->getId());

            $data = [
                'code' => 200,
                'message' => "Notificaciones eliminadas correctamente",
            ];
            return new JsonResponse($data, 200);
        } catch (Exception $ex) {
            throw new HttpException(400, "No se pueden eliminar la notificaciones - Error: {$ex->getMessage()}");
        }
    }
}
