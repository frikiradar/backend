<?php
// src/Controller/ChatController.php
namespace App\Controller;

use App\Entity\Chat;
use App\Repository\ChatRepository;
use App\Service\AccessCheckerService;
use App\Service\FileUploaderService;
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
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Class ChatController
 *
 * @Route(path="/api")
 */
class ChatController extends AbstractController
{
    public function __construct(
        ChatRepository $chatRepository,
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer,
        RequestService $request,
        NotificationService $notification,
        AccessCheckerService $accessChecker,
        AuthorizationCheckerInterface $security
    ) {
        $this->chatRepository = $chatRepository;
        $this->em = $entityManager;
        $this->serializer = $serializer;
        $this->request = $request;
        $this->notification = $notification;
        $this->accessChecker = $accessChecker;
        $this->security = $security;
    }

    /**
     * @Route("/v1/chat", name="put_chat", methods={"PUT"})
     */
    public function put(Request $request, PublisherInterface $publisher, \Swift_Mailer $mailer)
    {
        $fromUser = $this->getUser();
        $id = $this->request->get($request, "touser");
        if ($fromUser->getBanned() && $id !== 1) {
            $this->accessChecker->checkAccess($fromUser);
        }

        $cache = new FilesystemAdapter();
        $chat = new Chat();
        $toUser = $this->em->getRepository('App:User')->find($id);
        if (empty($this->em->getRepository('App:BlockUser')->isBlocked($fromUser, $toUser))) {
            if (!$fromUser->getBanned() && $id == 1 && !$this->security->isGranted('ROLE_DEMO')) {
                throw new HttpException(400, "No se puede escribir al usuario frikiradar sin estar baneado - Error");
            }
            $chat->setTouser($toUser);
            $chat->setFromuser($fromUser);

            $min = min($chat->getFromuser()->getId(), $chat->getTouser()->getId());
            $max = max($chat->getFromuser()->getId(), $chat->getTouser()->getId());

            $conversationId = $min . "_" . $max;

            $text = $this->request->get($request, "text", false);

            $chat->setText($text);
            $chat->setTimeCreation();
            $chat->setConversationId($conversationId);

            $replyToChat = $this->em->getRepository('App:Chat')->findOneBy(array('id' => $this->request->get($request, 'replyto', false)));
            if ($replyToChat && !$replyToChat->getModded()) {
                $chat->setReplyTo($replyToChat);
            }
            $this->em->persist($chat);
            $fromUser->setLastLogin();
            $this->em->persist($fromUser);
            $this->em->flush();

            $update = new Update($conversationId, $this->serializer->serialize($chat, "json", ['groups' => 'message']));
            $publisher($update);
            $update = new Update('chats-' . $fromUser->getId(), $this->serializer->serialize($chat, "json", ['groups' => 'message', AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true]));
            $publisher($update);
            if ($fromUser->getId() !== $toUser->getId()) {
                $update = new Update('chats-' . $toUser->getId(), $this->serializer->serialize($chat, "json", ['groups' => 'message', AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true]));
                $publisher($update);
            }

            $cache->deleteItem('users.chat.' . $fromUser->getId());

            $title = $fromUser->getName();
            $url = "/chat/" . $chat->getFromuser()->getId();

            $this->notification->set($fromUser, $toUser, $title, $text, $url, "chat");

            if ($fromUser->getBanned() && $id == 1) {
                // Enviamos email avisando
                $message = (new \Swift_Message('Mensaje de usuario baneado'))
                    ->setFrom([$fromUser->getEmail() => $fromUser->getName()])
                    ->setTo(['hola@frikiradar.com' => 'FrikiRadar'])
                    ->setBody("El usuario baneado " . $fromUser->getName() . " ha escrito un mensaje: " . $text, 'text/html');

                if (0 === $mailer->send($message)) {
                    // throw new HttpException(400, "Error al enviar el email avisando el bug");
                }
            }

            return new Response($this->serializer->serialize($chat, "json", ['groups' => 'message', AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true]));
        } else {
            throw new HttpException(400, "Error al marcar como leido - Error");
        }
    }

    /**
     * @Route("/v1/chat-upload", name="chat_upload", methods={"POST"})
     */
    public function upload(Request $request, PublisherInterface $publisher)
    {
        $fromUser = $this->getUser();
        $this->accessChecker->checkAccess($fromUser);
        try {
            $cache = new FilesystemAdapter();
            $chat = new Chat();
            $toUser = $this->em->getRepository('App:User')->find($request->request->get("touser"));
            if (empty($this->em->getRepository('App:BlockUser')->isBlocked($fromUser, $toUser)) && $toUser->getUsername() !== 'frikiradar') {
                $chat->setTouser($toUser);
                $chat->setFromuser($fromUser);

                $min = min($chat->getFromuser()->getId(), $chat->getTouser()->getId());
                $max = max($chat->getFromuser()->getId(), $chat->getTouser()->getId());

                $conversationId = $min . "_" . $max;

                $imageFile = $request->files->get('image');
                $text = $request->request->get("text");
                $audioFile = $request->files->get('audio');

                // return new Response($this->serializer->serialize($audioFile, 'json'));

                $filename = date('YmdHis');
                if ($_SERVER['HTTP_HOST'] == 'localhost:8000') {
                    $absolutePath = 'images/chat/';
                    $server = "https://$_SERVER[HTTP_HOST]";
                    $uploader = new FileUploaderService($absolutePath . $conversationId . "/", $filename);
                    if ($imageFile) {
                        $image = $uploader->uploadImage($imageFile, false, 70);
                        $chat->setImage($image);
                    }
                    if ($audioFile) {
                        $audio = $uploader->uploadAudio($audioFile);
                        $chat->setAudio($audio);
                    }
                    $chat->setTimeCreation();
                    $chat->setConversationId($conversationId);
                } else {
                    $absolutePath = '/var/www/vhosts/frikiradar.com/app.frikiradar.com/images/chat/';
                    $server = "https://app.frikiradar.com";
                    $uploader = new FileUploaderService($absolutePath . $conversationId . "/", $filename);
                    if ($imageFile) {
                        $image = $uploader->uploadImage($imageFile, false, 50);
                        $src = str_replace("/var/www/vhosts/frikiradar.com/app.frikiradar.com", $server, $image);
                        $chat->setImage($src);
                        $chat->setText($text);
                    }
                    if ($audioFile) {
                        $audio = $uploader->uploadAudio($audioFile);
                        $src = str_replace("/var/www/vhosts/frikiradar.com/app.frikiradar.com", $server, $audio);
                        $chat->setAudio($src);
                    }

                    $chat->setTimeCreation();
                    $chat->setConversationId($conversationId);
                    $this->em->persist($chat);
                    $fromUser->setLastLogin();
                    $this->em->persist($fromUser);
                    $this->em->flush();
                }

                $update = new Update($conversationId, $this->serializer->serialize($chat, "json", ['groups' => 'message', AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true]));
                $publisher($update);
                $update = new Update('chats-' . $fromUser->getId(), $this->serializer->serialize($chat, "json", ['groups' => 'message', AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true]));
                $publisher($update);
                if ($fromUser->getId() !== $toUser->getId()) {
                    $update = new Update('chats-' . $toUser->getId(), $this->serializer->serialize($chat, "json", ['groups' => 'message', AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true]));
                    $publisher($update);
                }

                $cache->deleteItem('users.chat.' . $fromUser->getId());

                $title = $fromUser->getName();
                $url = "/chat/" . $chat->getFromuser()->getId();

                if (empty($text) && isset($image)) {
                    $text = '📷 ' . $fromUser->getName() . ' te ha enviado una imagen.';
                } elseif (isset($image)) {
                    $text = '📷 ' . $text;
                } elseif (isset($audio)) {
                    $text = '🎤 ' . $fromUser->getName() . ' te ha enviado un audio.';
                }

                $this->notification->set($fromUser, $toUser, $title, $text, $url, "chat");

                return new Response($this->serializer->serialize($chat, "json", ['groups' => 'message', AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true]));
            } else {
                throw new HttpException(400, "Error al marcar como leido - Error");
            }
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al subir el archivo - Error: {$ex->getMessage()}");
        }
    }


    /**
     * @Route("/v1/chats", name="get_chats", methods={"GET"})
     */
    public function getChats()
    {
        $fromUser = $this->getUser();
        $this->accessChecker->checkAccess($fromUser);
        // $cache = new FilesystemAdapter();
        try {
            /*$chatsCache = $cache->getItem('users.chat.' . $fromUser->getId());
            if (!$chatsCache->isHit()) {
                $chatsCache->expiresAfter(3600);*/
            $chats = $this->em->getRepository('App:Chat')->getChatUsers($fromUser);
            /*$chatsCache->set($chats);
                $cache->save($chatsCache);*/
            $this->em->persist($fromUser);
            $this->em->flush();
            /*} else {
                $chats = $chatsCache->get();
            }*/
            return new Response($this->serializer->serialize($chats, "json"));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al obtener los usuarios - Error: {$ex->getMessage()}");
        }
    }


    /**
     * @Route("/v1/chat/{id}", name="get_chat", methods={"GET"})
     */
    public function getChatAction(int $id, Request $request, PublisherInterface $publisher)
    {
        $fromUser = $this->getUser();
        $cache = new FilesystemAdapter();
        $cache->deleteItem('users.chat.' . $fromUser->getId());

        $read = $this->request->get($request, "read");
        $page = $this->request->get($request, "page");
        $lastId = $this->request->get($request, "lastid", false) ?: 0;

        $toUser = $this->em->getRepository('App:User')->findOneBy(array('id' => $id));

        $blocked = !empty($this->em->getRepository('App:BlockUser')->isBlocked($fromUser, $toUser)) ? true : false;

        //marcamos como leidos los antiguos
        $unreadChats = $this->em->getRepository('App:Chat')->findBy(array('fromuser' => $toUser->getId(), 'touser' => $fromUser->getId(), 'time_read' => null));
        foreach ($unreadChats as $chat) {
            $conversationId = $chat->getConversationId();
            if (!is_null($chat->getFromUser())) {
                $chat->setTimeRead(new \DateTime);
                $this->em->persist($chat);

                $update = new Update($conversationId, $this->serializer->serialize($chat, "json", ['groups' => 'message', AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true]));
                $publisher($update);
                $update = new Update('chats-' . $fromUser->getId(), $this->serializer->serialize($chat, "json", ['groups' => 'message', AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true]));
                $publisher($update);
                if ($fromUser->getId() !== $toUser->getId()) {
                    $update = new Update('chats-' . $toUser->getId(), $this->serializer->serialize($chat, "json", ['groups' => 'message', AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true]));
                    $publisher($update);
                }
            }
        }

        // Borrar cachés de notificaciones de chat
        $cache->deleteItem('users.notifications.' . $fromUser->getId());
        $this->em->persist($fromUser);
        $this->em->flush();

        $chats = $this->em->getRepository('App:Chat')->getChat($fromUser, $toUser, $read, $page, $lastId, $fromUser->getBanned());
        foreach ($chats as $key => $chat) {
            if ((null !== $chat->getFromuser() && !$chat->getFromuser()->getActive()) || $blocked) {
                if ($blocked) {
                    $chats[$key]->getFromuser()->setUsername('Usuario desconocido');
                    $chats[$key]->getFromuser()->setName('Usuario desconocido');
                    $chats[$key]->getFromuser()->setAvatar(null);
                }
                $chats[$key]->getFromuser()->setActive(false);
                $chats[$key]->getFromuser()->setLastLogin(null);
            }
            if ((null !== $chat->getTouser() && !$chat->getTouser()->getActive()) || $blocked) {
                if ($blocked) {
                    $chats[$key]->getTouser()->setUsername('Usuario desconocido');
                    $chats[$key]->getTouser()->setName('Usuario desconocido');
                    $chats[$key]->getTouser()->setAvatar(null);
                }
                $chats[$key]->getTouser()->setActive(false);
                $chats[$key]->getTouser()->setLastLogin(null);
            }
        }

        return new Response($this->serializer->serialize($chats, "json", ['groups' => 'message']));
    }


    /**
     * @Route("/v1/read-chat/{id}", name="read_chat", methods={"GET"})
     */
    public function markAsReadAction(int $id, PublisherInterface $publisher)
    {
        try {
            $toUser = $this->getUser();
            $chat = $this->em->getRepository('App:Chat')->findOneBy(array('id' => $id));
            if ($chat->getToUser()->getId() == $toUser->getId()) {
                $conversationId = $chat->getConversationId();
                $chat->setTimeRead(new \DateTime);
                $this->em->persist($chat);
                $this->em->flush();

                $update = new Update($conversationId, $this->serializer->serialize($chat, "json", ['groups' => 'message', AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true]));
                $publisher($update);
                $update = new Update('chats-' . $chat->getToUser()->getId(), $this->serializer->serialize($chat, "json", ['groups' => 'message', AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true]));
                $publisher($update);
                if ($chat->getToUser()->getId() !== $chat->getFromUser()->getId()) {
                    $update = new Update('chats-' . $chat->getFromUser()->getId(), $this->serializer->serialize($chat, "json", ['groups' => 'message', AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true]));
                    $publisher($update);
                }

                // Borrar cachés de notificaciones de chat
                $cache = new FilesystemAdapter();
                $cache->deleteItem('users.notifications.' . $toUser->getId());

                return new Response($this->serializer->serialize($chat, "json", ['groups' => 'message', AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true]));
            } else {
                throw new HttpException(401, "No se puede marcar como leído el chat de otro usuario");
            }
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al marcar como leido - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Route("/v1/writing-chat", name="writing_chat", methods={"PUT"})
     */
    public function writingAction(Request $request, PublisherInterface $publisher)
    {
        $fromuser = $this->request->get($request, "fromuser");
        $touser = $this->request->get($request, "touser");

        $chat = [];
        $min = min($fromuser, $touser);
        $max = max($fromuser, $touser);

        $conversationId = $min . "_" . $max;

        $chat['fromuser']['id'] = $fromuser;
        $chat['conversationId'] = $conversationId;
        $chat['writing'] = true;

        $update = new Update($conversationId, json_encode($chat));
        $publisher($update);
        $update = new Update('chats-' . $touser, $this->serializer->serialize($chat, "json", ['groups' => 'message', AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true]));
        $publisher($update);

        $data = [
            'code' => 200,
            'message' => "Escribiendo en chat",
        ];
        return new JsonResponse($data, 200);
    }

    /**
     * @Route("/v1/update-message", name="update_message", methods={"PUT"})
     */
    public function updateMessageAction(Request $request, PublisherInterface $publisher)
    {
        try {
            $id = $this->request->get($request, "id");
            $text = $this->request->get($request, "text");
            $chat = $this->em->getRepository('App:Chat')->findOneBy(array('id' => $id));
            if ($chat->getFromUser()->getId() == $this->getUser()->getId() && !$chat->getModded()) {
                $conversationId = $chat->getConversationId();
                $chat->setText($text);
                $chat->setEdited(1);
                $this->em->persist($chat);
                $this->em->flush();

                $update = new Update($conversationId, $this->serializer->serialize($chat, "json", ['groups' => 'message', AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true]));
                $publisher($update);
                $update = new Update('chats-' . $chat->getFromUser()->getId(), $this->serializer->serialize($chat, "json", ['groups' => 'message', AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true]));
                $publisher($update);
                if (null !== ($chat->getToUser()) && $chat->getToUser()->getId() !== $chat->getFromUser()->getId()) {
                    $update = new Update('chats-' . $chat->getToUser()->getId(), $this->serializer->serialize($chat, "json", ['groups' => 'message', AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true]));
                    $publisher($update);
                }

                return new Response($this->serializer->serialize($chat, "json", ['groups' => 'message', AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true]));
            } else {
                throw new HttpException(401, "No se puede editar el mensaje de otro usuario.");
            }
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al editar el mensaje - Error: {$ex->getMessage()}");
        }
    }


    /**
     * @Route("/v1/chat-message/{id}", name="delete_message", methods={"DELETE"})
     */
    public function deleteMessageAction(int $id, PublisherInterface $publisher)
    {
        $user = $this->getUser();
        $this->accessChecker->checkAccess($user);
        try {
            $cache = new FilesystemAdapter();
            $cache->deleteItem('users.chat.' . $user->getId());

            $message = $this->em->getRepository('App:Chat')->findOneBy(array('id' => $id));
            if (!$message->getModded() && ($message->getFromuser()->getId() == $user->getId() || $this->security->isGranted('ROLE_MASTER'))) {
                $conversationId = $message->getConversationId();
                $image = $message->getImage();
                if (!empty($image)) {
                    $f = explode("/", $image);
                    $filename = $f[count($f) - 1];
                    $file = "/var/www/vhosts/frikiradar.com/app.frikiradar.com/images/chat/" . $conversationId . "/" . $filename;
                    if (file_exists($file)) {
                        unlink($file);
                    } else {
                        // "No se ha podido borrar el archivo"
                    }
                }

                $audio = $message->getImage();
                if (!empty($audio)) {
                    $f = explode("/", $audio);
                    $filename = $f[count($f) - 1];
                    $file = "/var/www/vhosts/frikiradar.com/app.frikiradar.com/images/chat/" . $conversationId . "/" . $filename;
                    if (file_exists($file)) {
                        unlink($file);
                    } else {
                        // "No se ha podido borrar el archivo"
                    }
                }

                if ($message->getFromuser()->getId() != $user->getId() && $this->security->isGranted('ROLE_MASTER')) {
                    $message->setText("<em>Mensaje eliminado por un moderador</em>");
                    $message->setModded(true);
                    $this->em->persist($message);
                    $this->em->flush();
                } else {
                    $this->em->remove($message);
                    $this->em->flush();
                    $message->setDeleted(1);
                }

                $update = new Update($conversationId, $this->serializer->serialize($message, "json", ['groups' => 'message', AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true]));
                $publisher($update);
                $update = new Update('chats-' . $message->getFromuser()->getId(), $this->serializer->serialize($message, "json", ['groups' => 'message', AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true]));
                $publisher($update);
                if (!empty($message->getToUser()) && $message->getToUser()->getId() !== $message->getFromUser()->getId()) {
                    $update = new Update('chats-' . $message->getToUser()->getId(), $this->serializer->serialize($message, "json", ['groups' => 'message', AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true]));
                    $publisher($update);
                }
                return new Response($this->serializer->serialize($message, "json", ['groups' => 'message']));
            } else {
                throw new HttpException(400, "Error al eliminar el mensaje. - Error: acción no permitida.");
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
            $cache = new FilesystemAdapter();
            $cache->deleteItem('users.chat.' . $fromUser->getId());

            $toUser = $this->em->getRepository('App:User')->findOneBy(array('id' => $id));
            $this->em->getRepository('App:Chat')->deleteChatUser($toUser, $fromUser);

            return new Response($this->serializer->serialize($id, "json"));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al eliminar el mensaje - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Route("/v1/chats-config", name="chats_config", methods={"PUT"})
     */
    public function chatsConfigAction(Request $request)
    {
        $user = $this->getUser();
        $config = $user->getConfig();
        $chatsConfig = $this->request->get($request, "chats_config");
        $config['chats'] = $chatsConfig;
        $user->setConfig($config);
        $this->em->persist($user);
        $this->em->flush();

        return new Response($this->serializer->serialize($user, "json", ['groups' => ['default', 'tags']]));
    }
}
