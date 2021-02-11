<?php
// src/Controller/ChatController.php
namespace App\Controller;

use App\Entity\Chat;
use App\Repository\ChatRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mercure\PublisherInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Service\NotificationService;
use App\Service\RequestService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Class ChatController
 *
 * @Route(path="/api")
 */
class ChatController extends AbstractController
{
    public function __construct(ChatRepository $chatRepository, EntityManagerInterface $entityManager, SerializerInterface $serializer, RequestService $request, NotificationService $notification)
    {
        $this->chatRepository = $chatRepository;
        $this->em = $entityManager;
        $this->serializer = $serializer;
        $this->request = $request;
        $this->notification = $notification;
    }

    /**
     * @Route("/v1/chat", name="put_chat", methods={"PUT"})
     */
    public function put(Request $request, PublisherInterface $publisher)
    {
        $chat = new Chat();
        $fromUser = $this->getUser();
        $toUser = $this->em->getRepository('App:User')->find($this->request->get($request, "touser"));
        if (empty($this->em->getRepository('App:BlockUser')->isBlocked($fromUser, $toUser)) && $toUser->getUsername() !== 'frikiradar') {
            $chat->setTouser($toUser);
            $chat->setFromuser($fromUser);

            $min = min($chat->getFromuser()->getId(), $chat->getTouser()->getId());
            $max = max($chat->getFromuser()->getId(), $chat->getTouser()->getId());

            $conversationId = $min . "_" . $max;

            $text = $this->request->get($request, "text");

            $chat->setText($text);
            $chat->setTimeCreation();
            $chat->setConversationId($conversationId);
            $this->em->persist($chat);
            $fromUser->setLastLogin();
            $this->em->persist($fromUser);
            $this->em->flush();

            $update = new Update($conversationId, $this->serializer->serialize($chat, "json", ['groups' => 'message']));
            $publisher($update);

            $title = $fromUser->getUsername();
            $url = "/chat/" . $chat->getFromuser()->getId();

            $this->notification->push($fromUser, $toUser, $title, $text, $url, "chat");

            return new Response($this->serializer->serialize($chat, "json", ['groups' => 'message']));
        } else {
            throw new HttpException(400, "Error al marcar como leido - Error");
        }
    }


    /**
     * @Route("/v1/chats", name="get_chats", methods={"GET"})
     */
    public function getChats()
    {
        $fromUser = $this->getUser();
        try {
            $chats = $this->em->getRepository('App:Chat')->getChatUsers($fromUser);
            $response = $chats;
        } catch (Exception $ex) {
            $response = [
                'code' => 500,
                'error' => true,
                'data' => "Error al obtener los usuarios - Error: {$ex->getMessage()}",
            ];
        }

        $fromUser->setLastLogin();
        $this->em->persist($fromUser);
        $this->em->flush();

        return new Response($this->serializer->serialize($response, "json"));
    }


    /**
     * @Route("/v1/chat/{id}", name="get_chat", methods={"GET"})
     */
    public function getChatAction(int $id, Request $request, PublisherInterface $publisher)
    {
        $read = $this->request->get($request, "read");
        $page = $this->request->get($request, "page");
        $lastId = $this->request->get($request, "lastid", false) ?: 0;

        $toUser = $this->em->getRepository('App:User')->findOneBy(array('id' => $id));
        $fromUser = $this->getUser();

        //marcamos como leidos los antiguos
        $unreadChats = $this->em->getRepository('App:Chat')->findBy(array('fromuser' => $toUser->getId(), 'touser' => $fromUser->getId(), 'time_read' => null));
        foreach ($unreadChats as $chat) {
            $conversationId = $chat->getConversationId();
            if (!is_null($chat->getFromUser())) {
                $chat->setTimeRead(new \DateTime);
                $this->em->persist($chat);

                $update = new Update($conversationId, $this->serializer->serialize($chat, "json", ['groups' => 'message']));
                $publisher($update);
            }
        }

        $fromUser->setLastLogin();
        $this->em->persist($fromUser);
        $this->em->flush();

        $chats = $this->em->getRepository('App:Chat')->getChat($fromUser, $toUser, $read, $page, $lastId);

        return new Response($this->serializer->serialize($chats, "json", ['groups' => 'message']));
    }


    /**
     * @Route("/v1/read-chat/{id}", name="read_chat", methods={"GET"})
     */
    public function markAsReadAction(int $id, PublisherInterface $publisher)
    {
        try {
            $chat = $this->em->getRepository('App:Chat')->findOneBy(array('id' => $id));
            // if ($chat->getTouser()->getId() == $this->getUser()->getId()) {
            $conversationId = $chat->getConversationId();
            $chat->setTimeRead(new \DateTime);
            $this->em->persist($chat);
            $this->em->flush();

            $update = new Update($conversationId, $this->serializer->serialize($chat, "json", ['groups' => 'message']));
            $publisher($update);

            return new Response($this->serializer->serialize($chat, "json", ['groups' => 'message']));
            /*} else {
                throw new HttpException(401, "No se puede marcar como leído el chat de otro usuario");
            }*/
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al marcar como leido - Error: {$ex->getMessage()}");
        }
    }


    /**
     * @Route("/v1/chat-message/{id}", name="delete_message", methods={"DELETE"})
     */
    public function deleteMessageAction(int $id)
    {
        try {
            $user = $this->getUser();
            $message = $this->em->getRepository('App:Chat')->findOneBy(array('id' => $id));
            if ($message->getFromuser()->getId() == $user->getId()) {
                $this->em->remove($message);
                $this->em->flush();
                return new Response($this->serializer->serialize($message, "json", ['groups' => 'message']));
            } else {
                throw new HttpException(400, "Error al eliminar el mensaje. - Error: usuario no permitido.");
            }
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al eliminar el mensaje - Error: {$ex->getMessage()}");
        }
    }


    /**
     * @Route("/v1/chat/{id}", name="delete_chat", methods={"DELETE"})
     */
    public function deleteAction(int $id)
    {
        try {
            $fromUser = $this->getUser();
            $toUser = $this->em->getRepository('App:User')->findOneBy(array('id' => $id));
            $this->em->getRepository('App:Chat')->deleteChatUser($toUser, $fromUser);

            return new Response($this->serializer->serialize($id, "json"));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al eliminar el mensaje - Error: {$ex->getMessage()}");
        }
    }
}
