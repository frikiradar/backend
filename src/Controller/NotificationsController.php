<?php
// src/Controller/ChatController.php
namespace App\Controller;

use App\Repository\ChatRepository;
use App\Repository\NotificationRepository;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\SerializerInterface;

#[Route(path: '/api')]
class NotificationsController extends AbstractController
{
    private $serializer;
    private $userRepository;
    private $notificationRepository;
    private $chatRepository;

    public function __construct(SerializerInterface $serializer, UserRepository $userRepository, NotificationRepository $notificationRepository, ChatRepository $chatRepository)
    {
        $this->serializer = $serializer;
        $this->userRepository = $userRepository;
        $this->notificationRepository = $notificationRepository;
        $this->chatRepository = $chatRepository;
    }


    #[Route('/v1/notifications', name: 'get_notifications', methods: ['GET'])]
    public function getNotifications()
    {
        $cache = new FilesystemAdapter();
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        try {
            $notificationsCache = $cache->getItem('users.notifications.' . $user->getId());
            if (!$notificationsCache->isHit()) {
                $countGeneral = $this->notificationRepository->countUnread($user);
                $countChats = $this->chatRepository->countUnread($user);
                $notifications = ["notifications" => (int) $countGeneral, "chats" => (int) $countChats];

                $notificationsCache->set($notifications);
                $cache->save($notificationsCache);

                $user = $this->getUser();
                $this->userRepository->save($user);
            } else {
                $notifications = $notificationsCache->get();
            }

            return new JsonResponse($this->serializer->serialize($notifications, "json"), Response::HTTP_OK, [], true);
        } catch (Exception $ex) {
            throw new HttpException(400, "No se pueden obtener los contadores de notificaciones - Error: {$ex->getMessage()}");
        }
    }

    #[Route('/v1/notifications-list', name: 'notifications_list', methods: ['GET'])]
    public function getNotificationsList()
    {
        $cache = new FilesystemAdapter();
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        try {
            $notificationsCache = $cache->getItem('users.notifications-list.' . $user->getId());
            if (!$notificationsCache->isHit()) {
                $notifications = $this->notificationRepository->findBy(['user' => $user], ['id' => 'DESC'], 25);
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

    #[Route('/v1/read-notification/{id}', name: 'read_notification', methods: ['GET'])]
    public function readNotification(int $id)
    {
        $cache = new FilesystemAdapter();
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        try {
            $notification = $this->notificationRepository->findOneBy(array('id' => $id));
            if ($notification->getUser()->getId() == $user->getId()) {
                $notification->setTimeRead(new \DateTime);
                $this->notificationRepository->save($notification);

                $cache->deleteItem('users.notifications.' . $user->getId());
                $cache->deleteItem('users.notifications-list.' . $user->getId());

                return new JsonResponse($this->serializer->serialize($notification, "json", ['groups' => 'notification']), Response::HTTP_OK, [], true);
            } else {
                throw new HttpException(401, "No se puede marcar como leída la notificación de otro usuario");
            }
        } catch (Exception $ex) {
            throw new HttpException(400, "No se puede marcar como leída la notificación - Error: {$ex->getMessage()}");
        }
    }

    #[Route('/v1/unread-notification/{id}', name: 'unread_notification', methods: ['GET'])]
    public function unreadNotification(int $id)
    {
        $cache = new FilesystemAdapter();
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        try {
            $notification = $this->notificationRepository->findOneBy(array('id' => $id));
            if ($notification->getUser()->getId() == $user->getId()) {
                $notification->setTimeRead(null);
                $this->notificationRepository->save($notification);

                $cache->deleteItem('users.notifications.' . $user->getId());
                $cache->deleteItem('users.notifications-list.' . $user->getId());

                return new JsonResponse($this->serializer->serialize($notification, "json", ['groups' => 'notification']), Response::HTTP_OK, [], true);
            } else {
                throw new HttpException(401, "No se puede desmarcar como leída la notificación de otro usuario");
            }
        } catch (Exception $ex) {
            throw new HttpException(400, "No se puede desmarcar como leída la notificación - Error: {$ex->getMessage()}");
        }
    }

    // REad all notifications
    #[Route('/v1/read-notifications', name: 'read_notifications', methods: ['GET'])]
    public function readNotifications()
    {
        $cache = new FilesystemAdapter();
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        try {
            $this->notificationRepository->readNotifications($user);

            $cache->deleteItem('users.notifications.' . $user->getId());
            $cache->deleteItem('users.notifications-list.' . $user->getId());

            $data = [
                'code' => 200,
                'message' => "Notificaciones marcadas como leídas correctamente",
            ];
            return new JsonResponse($data, 200);
        } catch (Exception $ex) {
            throw new HttpException(400, "No se pueden marcar como leídas las notificaciones - Error: {$ex->getMessage()}");
        }
    }

    #[Route('/v1/remove-notification/{id}', name: 'remove_notification', methods: ['DELETE'])]
    public function removeNotification(int $id)
    {
        $cache = new FilesystemAdapter();
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        try {
            $notification = $this->notificationRepository->findOneBy(array('id' => $id));
            if ($notification->getUser()->getId() == $user->getId()) {
                $this->notificationRepository->remove($notification);

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

    #[Route('/v1/remove-notifications', name: 'remove_notifications', methods: ['DELETE'])]
    public function removeNotifications()
    {
        $cache = new FilesystemAdapter();
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        try {
            $this->notificationRepository->removeNotifications($user);

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
