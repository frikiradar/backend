<?php

namespace App\Controller;

use App\Entity\Chat;
use App\Service\NotificationService;
use App\Service\RequestService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Class AdminController
 * @Route(path="/api")
 */
#[IsGranted("ROLE_MASTER")]
class AdminController extends AbstractController
{
    private $request;
    private $serializer;
    private $em;
    private $notification;

    public function __construct(
        SerializerInterface $serializer,
        RequestService $request,
        EntityManagerInterface $entityManager,
        NotificationService $notification
    ) {
        $this->request = $request;
        $this->serializer = $serializer;
        $this->em = $entityManager;
        $this->notification = $notification;
    }

    /**
     * @Route("/v1/topic-message", name="topic_message", methods={"PUT"})
     */
    public function putTopicMessage(Request $request)
    {
        $chat = new Chat();

        try {
            $fromUser = $this->em->getRepository(\App\Entity\User::class)->findOneBy(array('username' => 'frikiradar'));
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

    /**
     * @Route("/v1/banned-messages/{id}", name="get_banned_messages", methods={"GET"})
     */
    public function getBannedMessagesAction(int $id)
    {
        $fromUser = $this->em->getRepository(\App\Entity\User::class)->findOneBy(array('id' => 1));
        $toUser = $this->em->getRepository(\App\Entity\User::class)->findOneBy(array('id' => $id));

        //marcamos como leidos los antiguos
        $unreadChats = $this->em->getRepository(\App\Entity\Chat::class)->findBy(array('fromuser' => $toUser->getId(), 'touser' => $fromUser->getId(), 'time_read' => null));
        foreach ($unreadChats as $chat) {
            if (!is_null($chat->getFromUser())) {
                $chat->setTimeRead(new \DateTime);
                $this->em->persist($chat);
            }
        }
        $this->em->flush();

        $chats = $this->em->getRepository(\App\Entity\Chat::class)->getChat($fromUser, $toUser, true, 1, 0, true);

        return new Response($this->serializer->serialize($chats, "json", ['groups' => 'message']));
    }

    /**
     * @Route("/v1/banned-message", name="banned_message", methods={"PUT"})
     */
    public function put(Request $request)
    {
        $fromUser = $this->em->getRepository(\App\Entity\User::class)->findOneBy(array('id' => 1));
        $id = $this->request->get($request, "touser");

        $chat = new Chat();
        $toUser = $this->em->getRepository(\App\Entity\User::class)->find($id);

        $chat->setTouser($toUser);
        $chat->setFromuser($fromUser);

        $conversationId = 1 . "_" . $chat->getTouser()->getId();

        $text = $this->request->get($request, "text", false);

        $chat->setText($text);
        $chat->setTimeCreation();
        $chat->setConversationId($conversationId);
        $this->em->persist($chat);
        $this->em->flush();

        $title = $fromUser->getUsername();
        $url = "/login/banned-account";

        $this->notification->set($fromUser, $toUser, $title, $text, $url, "chat");

        return new Response($this->serializer->serialize($chat, "json", ['groups' => 'message']));
    }

    /**
     * @Route("/v1/ban", name="ban", methods={"PUT"})
     */
    public function ban(Request $request)
    {
        try {
            $toUser = $this->em->getRepository(\App\Entity\User::class)->find($this->request->get($request, "touser"));
            $reason = $this->request->get($request, 'message');
            $days = $this->request->get($request, 'days', false);
            $hours = $this->request->get($request, 'hours', false);
            $this->em->getRepository(\App\Entity\User::class)->banUser($toUser, $reason, $days, $hours);

            return new Response($this->serializer->serialize("Baneo realizado correctamente", "json"));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al realizar el baneo - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Route("/v1/bans", name="bans", methods={"GET"})
     */
    public function getBansAction()
    {
        $users = $this->em->getRepository(\App\Entity\User::class)->getBanUsers();

        return new Response($this->serializer->serialize($users, "json", ['groups' => 'default']));
    }

    /**
     * @Route("/v1/ban/{id}", name="unban", methods={"DELETE"})
     */
    public function removeBanAction(int $id)
    {
        try {
            /**
             * @var User
             */
            $user = $this->em->getRepository(\App\Entity\User::class)->findOneBy(array('id' => $id));

            $user->setBanned(0);
            $user->setBanReason(null);
            $user->setBanEnd(null);
            $this->em->persist($user);
            $this->em->flush();

            $users = $this->em->getRepository(\App\Entity\User::class)->getBanUsers();

            return new Response($this->serializer->serialize($users, "json", ['groups' => 'default']));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al desbanear al usuario - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Route("/v1/warn", name="warn_chat", methods={"PUT"})
     */
    public function warn(Request $request)
    {
        $chat = new Chat();

        try {
            $fromUser = $this->em->getRepository(\App\Entity\User::class)->findOneBy(array('username' => 'frikiradar'));
            $toUser = $this->em->getRepository(\App\Entity\User::class)->find($this->request->get($request, "touser"));
            $title = "⚠️ Aviso de moderación";
            $text = $this->request->get($request, 'message');
            $url = "/chat/" . $fromUser->getId();

            $chat->setFromuser($fromUser);
            $chat->setTouser($toUser);

            $chat->setText($title . "\r\n\r\n" . $text);
            $chat->setTimeCreation();
            $chat->setConversationId('1_' . $toUser->getId());
            $this->em->persist($chat);
            $this->em->flush();

            $this->notification->set($fromUser, $toUser, $title, $text, $url, "chat");

            return new Response($this->serializer->serialize("Aviso enviado correctamente", "json"));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al enviar el aviso de moderación - Error: {$ex->getMessage()}");
        }
    }
}
