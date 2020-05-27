<?php
// src/Controller/ChatController.php
namespace App\Controller;

use App\Entity\Chat;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Request\ParamFetcherInterface;
use JMS\Serializer\SerializationContext;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Swagger\Annotations as SWG;
use Symfony\Component\Mercure\Publisher;
use Symfony\Component\Mercure\Update;
use App\Service\NotificationService;

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

        $chat = new Chat();
        $fromUser = $this->getUser();
        $toUser = $em->getRepository('App:User')->find($request->request->get("touser"));
        if (empty($em->getRepository('App:BlockUser')->isBlocked($fromUser, $toUser)) && $toUser->getUsername() !== 'frikiradar') {
            $chat->setTouser($toUser);
            $chat->setFromuser($fromUser);

            $min = min($chat->getFromuser()->getId(), $chat->getTouser()->getId());
            $max = max($chat->getFromuser()->getId(), $chat->getTouser()->getId());

            $conversationId = $min . "_" . $max;

            $text = $request->request->get("text");

            $chat->setText($text);
            $chat->setTimeCreation(new \DateTime);
            $chat->setConversationId($conversationId);
            $em->persist($chat);
            $fromUser->setLastLogin();
            $em->persist($fromUser);
            $em->flush();

            $update = new Update($conversationId, $serializer->serialize($chat, "json", SerializationContext::create()->setGroups(array('message'))->enableMaxDepthChecks()));
            $publisher($update);

            $title = $fromUser->getUsername();
            $url = "/chat/" . $chat->getFromuser()->getId();

            $notification = new NotificationService();
            $notification->push($fromUser, $toUser, $title, $text, $url, "chat");

            return new Response($serializer->serialize($chat, "json", SerializationContext::create()->setGroups(array('message'))->enableMaxDepthChecks()));
        } else {
            throw new HttpException(400, "Error al marcar como leido - Error");
        }
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

        $fromUser = $this->getUser();
        try {
            $chats = $em->getRepository('App:Chat')->getChatUsers($fromUser);

            foreach ($chats as $key => $chat) {
                $userId = $chat["fromuser"] == $fromUser->getId() ? $chat["touser"] : $chat["fromuser"];
                $user = $em->getRepository('App:User')->findOneBy(array('id' => $userId));
                $chats[$key]['count'] = $em->getRepository('App:Chat')->countUnreadUser($fromUser, $user);
                $chats[$key]['user'] = [
                    'id' => $userId,
                    'username' => $user->getUsername(),
                    'name' => $user->getName(),
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

        $fromUser->setLastLogin();
        $em->persist($fromUser);
        $em->flush();

        return new Response($serializer->serialize($response, "json"));
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
     * @Rest\QueryParam(
     *     name="read",
     *     default="false",
     *     description="Get read chats or not"
     * )
     * 
     * @Rest\QueryParam(
     *     name="page",
     *     default="1",
     *     description="Chat page"
     * )
     * 
     */
    public function getChatAction(int $id, ParamFetcherInterface $params, Publisher $publisher)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        $read = $params->get("read");
        $page = $params->get("page");

        $toUser = $em->getRepository('App:User')->findOneBy(array('id' => $id));
        $fromUser = $this->getUser();

        //marcamos como leidos los antiguos
        $unreadChats = $em->getRepository('App:Chat')->findBy(array('fromuser' => $toUser->getId(), 'touser' => $fromUser->getId(), 'timeRead' => null));
        foreach ($unreadChats as $chat) {
            $conversationId = $chat->getConversationId();
            if ($conversationId !== 'frikiradar') {
                $chat->setTimeRead(new \DateTime);
                $em->merge($chat);

                $update = new Update($conversationId, $serializer->serialize($chat, "json", SerializationContext::create()->setGroups(array('message'))->enableMaxDepthChecks()));
                $publisher($update);
            }
        }

        $fromUser->setLastLogin();
        $em->persist($fromUser);
        $em->flush();

        $chats = $em->getRepository('App:Chat')->getChat($fromUser, $toUser, $read, $page);

        return new Response($serializer->serialize($chats, "json", SerializationContext::create()->setGroups(array('message'))->enableMaxDepthChecks()));
    }


    /**
     * @Rest\Get("/v1/read-chat/{id}")
     * 
     * @SWG\Response(
     *     response=201,
     *     description="Mensaje leido correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al marcar como leido el mensaje"
     * )
     *
     */
    public function markAsReadAction(int $id, Publisher $publisher)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        try {
            $chat = $em->getRepository('App:Chat')->findOneBy(array('id' => $id));
            // if ($chat->getTouser()->getId() == $this->getUser()->getId()) {
            $conversationId = $chat->getConversationId();
            $chat->setTimeRead(new \DateTime);
            $em->merge($chat);
            $em->flush();

            $update = new Update($conversationId, $serializer->serialize($chat, "json", SerializationContext::create()->setGroups(array('message'))->enableMaxDepthChecks()));
            $publisher($update);

            return new Response($serializer->serialize($chat, "json", SerializationContext::create()->setGroups(array('message'))->enableMaxDepthChecks()));
            /*} else {
                throw new HttpException(401, "No se puede marcar como leído el chat de otro usuario");
            }*/
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al marcar como leido - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Rest\Delete("/v1/chat-message/{id}")
     *
     * @SWG\Response(
     *     response=201,
     *     description="Mensaje eliminado correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al eliminar el mensaje"
     * )
     * 
     */
    public function deleteMessageAction(int $id)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        try {
            $user = $this->getUser();
            $message = $em->getRepository('App:Chat')->findOneBy(array('id' => $id));
            if ($message->getFromuser()->getId() == $user->getId()) {
                $em->remove($message);
                $em->flush();
                return new Response($serializer->serialize($message, "json", SerializationContext::create()->setGroups(array('message'))));
            } else {
                throw new HttpException(400, "Error al eliminar el mensaje. - Error: usuario no permitido.");
            }
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al eliminar el mensaje - Error: {$ex->getMessage()}");
        }
    }


    /**
     * @Rest\Delete("/v1/chat/{id}")
     *
     * @SWG\Response(
     *     response=201,
     *     description="Chat eliminado correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Chat al eliminar el mensaje"
     * )
     * 
     */
    public function deleteAction(int $id)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        try {
            $fromUser = $this->getUser();
            $toUser = $em->getRepository('App:User')->findOneBy(array('id' => $id));
            $em->getRepository('App:Chat')->deleteChatUser($toUser, $fromUser);

            return new Response($serializer->serialize($id, "json"));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al eliminar el mensaje - Error: {$ex->getMessage()}");
        }
    }
}
