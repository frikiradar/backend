<?php
// src/Controller/ChatController.php
namespace App\Controller;

use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\FOSRestController;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpFoundation\Request;
use App\Service\NotificationService;
use App\Entity\Chat;

/**
 * Class NotificationsController
 *
 * @Route("/api")
 */
class NotificationsController extends FOSRestController
{
    /**
     * @Rest\Get("/v1/notifications")
     *
     * @SWG\Response(
     *     response=201,
     *     description="Notificaciones obtenidas correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al obtener las notificaciones"
     * )
     * 
     */
    public function getNotifications()
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        try {
            $countRadar = $em->getRepository('App:Radar')->countUnread($this->getUser());
            $countChats = $em->getRepository('App:Chat')->countUnread($this->getUser());
            $countLikes = $em->getRepository('App:LikeUser')->countUnread($this->getUser());

            $notifications = ["radar" => (int) $countRadar, "chats" => (int) $countChats, "likes" => (int) $countLikes];

            $user = $this->getUser();
            $user->setLastLogin();
            $em->persist($user);
            $em->flush();

            return new Response($serializer->serialize($notifications, "json"));
        } catch (Exception $ex) {
            throw new HttpException(400, "No se pueden obtener los contadores de notificaciones - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Rest\Put("/v1/topic-message", name="topic-message")
     *
     * @SWG\Response(
     *     response=201,
     *     description="Mensaje enviado correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al enviar el mensaje"
     * )
     * 
     * @SWG\Parameter(
     *     name="title",
     *     in="body",
     *     type="string",
     *     description="Title",
     *     schema={}
     * )
     * 
     * @SWG\Parameter(
     *     name="message",
     *     in="body",
     *     type="string",
     *     description="Message",
     *     schema={}
     * )
     * 
     * @SWG\Parameter(
     *     name="topic",
     *     in="body",
     *     type="string",
     *     description="Topic",
     *     schema={}
     * )
     *
     */
    public function putTopicMessage(Request $request)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();
        $chat = new Chat();

        try {
            $fromUser = $em->getRepository('App:User')->findOneBy(array('username' => 'frikiradar'));
            $topic = $request->request->get('topic');
            $title = $request->request->get('title') ?: "❤ ¡Información importante! 🎏";
            $text = $request->request->get('message');
            $url = "/chat/" . $fromUser->getId();

            $chat->setFromuser($fromUser);
            if ($topic == 'test') {
                $chat->setTouser($this->getUser());
            }
            $chat->setText($title . "\r\n\r\n" . $text);
            $chat->setTimeCreation(new \DateTime);
            $chat->setConversationId($topic);
            $em->persist($chat);
            $em->flush();

            $notification = new NotificationService();
            $notification->pushTopic($fromUser, $topic, $title, $text, $url);

            return new Response($serializer->serialize("Notificación enviada correctamente", "json"));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al enviar la notificación global - Error: {$ex->getMessage()}");
        }
    }
}
