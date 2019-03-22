<?php
 // src/Controller/ChatController.php
namespace App\Controller;

use App\Entity\Notification;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\FOSRestController;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Swagger\Annotations as SWG;

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
            $toUser = $em->getRepository('App:User')->findOneBy(array('id' => $this->getUser()->getId()));
            $notifications = $em->getRepository('App:Notification')->getNotifications($toUser);

            foreach ($notifications as $key => $notification) {
                $user = $em->getRepository('App:User')->findOneBy(array('id' => $notification->getFromUser()->getId()));
                $notifications[$key]['user'] = [
                    'id' => $user->getId(),
                    'username' => $user->getUsername(),
                    'avatar' =>  $user->getAvatar() ?: null
                ];
            }
            $response = $notifications;
        } catch (Exception $ex) {
            $response = [
                'code' => 500,
                'error' => true,
                'data' => "Error al obtener las notificaciones - Error: {$ex->getMessage()}",
            ];
        }

        return new Response($serializer->serialize($response, "json"));
    }
}
