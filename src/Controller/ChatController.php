<?php
 // src/Controller/ChatController.php
namespace App\Controller;

use App\Entity\Chat;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\FOSRestController;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Swagger\Annotations as SWG;
use Symfony\Component\Mercure\Publisher;
use Symfony\Component\Mercure\Update;

/**
 * Class ChatController
 *
 * @Route("/api")
 */
class ChatController extends FOSRestController
{
    /**
     * @Rest\Put("/v1/chat")
     * 
     * @SWG\Response(
     *     response=201,
     *     description="Usuario actualizado correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al actualizar el usuario"
     * )
     * 
     * @SWG\Parameter(
     *     name="touser",
     *     in="body",
     *     type="string",
     *     description="To user id",
     *     schema={}
     * )
     *
     * 
     * @SWG\Parameter(
     *     name="text",
     *     in="body",
     *     type="string",
     *     description="Text",
     *     schema={}
     * )
     */
    public function putAction(Request $request, Publisher $publisher)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        $chat = $em->getRepository('App:Chat')->sendMessage(
            $this->getUser()->getId(),
            $request->request->get("touser"),
            $request->request->get("text")
        );

        return new Response($serializer->serialize($chat, "json"));
    }

    /**
     * @Rest\Get("/v1/chat/{id}")
     * 
     * @SWG\Response(
     *     response=201,
     *     description="Chat obtenido correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Chat al obtener el usuario"
     * )
     * 
     */
    public function getChatAction(int $id)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        $toUser = $em->getRepository('App:User')->findOneBy(array('id' => $id));
        $obChats = $em->getRepository('App:Chat')->getChat($this->getUser(), $toUser);

        $chats = [];
        foreach ($obChats as $key => $chat) {
            $chats[$key]['fromuser'] = $chat->getFromuser()->getId();
            $chats[$key]['touser'] = $chat->getTouser()->getId();
            $chats[$key]['text'] = $chat->getText();
            $chats[$key]['time_creation'] = $chat->getTimeCreation();
        }

        return new Response($serializer->serialize($chats, "json"));
    }

    /**
     * @Rest\Get("/v1/chats")
     *
     * @SWG\Response(
     *     response=201,
     *     description="Usuarios del chat obtenidos correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al obtener los usuarios"
     * )
     * 
     */
    public function getChats()
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        try {
            $fromUser = $em->getRepository('App:User')->findOneBy(array('id' => $this->getUser()->getId()));
            $chats = $em->getRepository('App:Chat')->getChatUsers($fromUser);

            foreach ($chats as $key => $chat) {
                $userId = $chat["fromuser"] == $this->getUser()->getId() ? $chat["touser"] : $chat["fromuser"];
                $user = $em->getRepository('App:User')->findOneBy(array('id' => $userId));
                $chats[$key]['user'] = [
                    'id' => $userId,
                    'username' => $user->getUsername(),
                    'avatar' =>  $user->getAvatar() ?: null
                ];
            }
            $response = $chats;
        } catch (Exception $ex) {
            $response = [
                'code' => 500,
                'error' => true,
                'data' => "Error al obtener los usuarios - Error: {$ex->getMessage()}",
            ];
        }

        return new Response($serializer->serialize($response, "json"));
    }
}
