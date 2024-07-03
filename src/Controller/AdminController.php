<?php

namespace App\Controller;

use App\Entity\Chat;
use App\Repository\ChatRepository;
use App\Repository\UserRepository;
use App\Service\NotificationService;
use App\Service\RequestService;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/api')]
#[IsGranted("ROLE_MASTER")]
class AdminController extends AbstractController
{
    private $request;
    private $serializer;
    private $notification;
    private $userRepository;
    private $chatRepository;

    public function __construct(
        SerializerInterface $serializer,
        RequestService $request,
        NotificationService $notification,
        UserRepository $userRepository,
        ChatRepository $chatRepository
    ) {
        $this->request = $request;
        $this->serializer = $serializer;
        $this->notification = $notification;
        $this->userRepository = $userRepository;
        $this->chatRepository = $chatRepository;
    }

    #[Route('/v1/topic-message', name: 'topic_message', methods: ['PUT'])]
    public function putTopicMessage(Request $request)
    {
        $chat = new Chat();

        try {
            $fromUser = $this->userRepository->findOneBy(array('username' => 'frikiradar'));
            $topic = $this->request->get($request, 'topic');
            $title = $this->request->get($request, 'title') ?: "❤ ¡Información importante! 🎏";
            $text = $this->request->get($request, 'message');
            $url = "/tabs/chat/" . $fromUser->getId();

            $chat->setFromuser($fromUser);
            if ($topic == 'test') {
                $chat->setTouser($this->getUser());
            }
            $chat->setText($title . "\r\n\r\n" . $text);
            $chat->setTimeCreation();
            $chat->setConversationId('frikiradar');
            $this->chatRepository->save($chat);

            $this->notification->pushTopic($fromUser, $topic, $title, $text, $url);

            return new JsonResponse($this->serializer->serialize("Notificación enviada correctamente", "json"), Response::HTTP_OK, [], true);
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al enviar la notificación global - Error: {$ex->getMessage()}");
        }
    }

    #[Route('/v1/banned-messages/{id}', name: 'get_banned_messages', methods: ['GET'])]
    public function getBannedMessagesAction(int $id)
    {
        $fromUser = $this->userRepository->findOneBy(array('id' => 1));
        $toUser = $this->userRepository->findOneBy(array('id' => $id));

        //marcamos como leidos los antiguos
        $this->chatRepository->markChatsAsRead($toUser, $fromUser);
        $chats = $this->chatRepository->getChat($fromUser, $toUser, 1, 0, true);

        return new JsonResponse($this->serializer->serialize($chats, "json", ['groups' => 'message']), Response::HTTP_OK, [], true);
    }

    #[Route('/v1/banned-message', name: 'banned_message', methods: ['PUT'])]
    public function put(Request $request)
    {
        $fromUser = $this->userRepository->findOneBy(array('id' => 1));
        $id = $this->request->get($request, "touser");

        $chat = new Chat();
        $toUser = $this->userRepository->find($id);

        $chat->setTouser($toUser);
        $chat->setFromuser($fromUser);

        $conversationId = 1 . "_" . $chat->getTouser()->getId();

        $text = $this->request->get($request, "text", false);

        $chat->setText($text);
        $chat->setTimeCreation();
        $chat->setConversationId($conversationId);
        $this->chatRepository->save($chat);

        $title = $fromUser->getUsername();
        $url = "/login/banned-account";

        $this->notification->set($fromUser, $toUser, $title, $text, $url, "chat");

        return new JsonResponse($this->serializer->serialize($chat, "json", ['groups' => 'message']), Response::HTTP_OK, [], true);
    }

    #[Route('/v1/ban', name: 'ban', methods: ['PUT'])]
    public function ban(Request $request)
    {
        try {
            $toUser = $this->userRepository->find($this->request->get($request, "touser"));
            $reason = $this->request->get($request, 'message');
            $days = $this->request->get($request, 'days', false);
            $hours = $this->request->get($request, 'hours', false);
            $this->userRepository->banUser($toUser, $reason, $days, $hours);

            return new JsonResponse($this->serializer->serialize("Baneo realizado correctamente", "json"), Response::HTTP_OK, [], true);
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al realizar el baneo - Error: {$ex->getMessage()}");
        }
    }

    #[Route('/v1/bans', name: 'bans', methods: ['GET'])]
    public function getBansAction()
    {
        $users = $this->userRepository->getBanUsers();

        return new JsonResponse($this->serializer->serialize($users, "json", ['groups' => 'default']), Response::HTTP_OK, [], true);
    }

    #[Route('/v1/ban/{id}', name: 'unban', methods: ['DELETE'])]
    public function removeBanAction(int $id)
    {
        try {
            /** @var \App\Entity\User $user */
            $user = $this->userRepository->findOneBy(array('id' => $id));

            $user->setBanned(0);
            $user->setBanReason(null);
            $user->setBanEnd(null);
            $this->userRepository->save($user);

            $users = $this->userRepository->getBanUsers();

            return new JsonResponse($this->serializer->serialize($users, "json", ['groups' => 'default']), Response::HTTP_OK, [], true);
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al desbanear al usuario - Error: {$ex->getMessage()}");
        }
    }

    #[Route('/v1/warn', name: 'warn_chat', methods: ['PUT'])]
    public function warn(Request $request)
    {
        $chat = new Chat();

        try {
            $fromUser = $this->userRepository->findOneBy(array('username' => 'frikiradar'));
            $toUser = $this->userRepository->find($this->request->get($request, "touser"));
            $title = "⚠️ Aviso de moderación";
            $text = $this->request->get($request, 'message');
            $url = "/tabs/chat/" . $fromUser->getId();

            $chat->setFromuser($fromUser);
            $chat->setTouser($toUser);

            $chat->setText($title . "\r\n\r\n" . $text);
            $chat->setTimeCreation();
            $chat->setConversationId('1_' . $toUser->getId());
            $this->chatRepository->save($chat);

            $this->notification->set($fromUser, $toUser, $title, $text, $url, "chat");

            return new JsonResponse($this->serializer->serialize("Aviso enviado correctamente", "json"), Response::HTTP_OK, [], true);
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al enviar el aviso de moderación - Error: {$ex->getMessage()}");
        }
    }
}
