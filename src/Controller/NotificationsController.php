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

/**
 * Class ChatController
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
}
