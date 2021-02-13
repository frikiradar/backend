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
                $countRadar = $this->em->getRepository('App:Radar')->countUnread($user);
                $countChats = $this->em->getRepository('App:Chat')->countUnread($user);
                $countLikes = $this->em->getRepository('App:LikeUser')->countUnread($user);

                $notifications = ["radar" => (int) $countRadar, "chats" => (int) $countChats, "likes" => (int) $countLikes];
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


    /**
     * @Route("/v1/topic-message", name="topic_message", methods={"PUT"})
     */
    public function putTopicMessage(Request $request)
    {
        $chat = new Chat();

        try {
            $fromUser = $this->em->getRepository('App:User')->findOneBy(array('username' => 'frikiradar'));
            $topic = $this->request->get($request, 'topic');
            $title = $this->request->get($request, 'title') ?: "❤ ¡Información importante! 🎏";
            $text = $this->request->get($request, 'message');
            $url = "/chat/" . $fromUser->getId();

            $chat->setFromuser($fromUser);
            if ($topic == 'test') {
                $chat->setTouser($this->getUser());
            }
            $chat->setText($title . "\r\n\r\n" . $text);
            $chat->setTimeCreation();
            $chat->setConversationId('frikiradar');
            $this->em->persist($chat);
            $this->em->flush();

            $this->notification->pushTopic($fromUser, $topic, $title, $text, $url);

            return new Response($this->serializer->serialize("Notificación enviada correctamente", "json"));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al enviar la notificación global - Error: {$ex->getMessage()}");
        }
    }
}
