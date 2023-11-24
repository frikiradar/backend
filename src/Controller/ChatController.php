<?php
// src/Controller/ChatController.php
namespace App\Controller;

use App\Entity\Chat;
use App\Service\AccessCheckerService;
use App\Service\FileUploaderService;
use App\Service\MessageService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Service\RequestService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Mime\Email;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

#[Route(path: '/api')]
class ChatController extends AbstractController
{
    private $em;
    private $serializer;
    private $request;
    private $message;
    private $accessChecker;
    private $security;

    public function __construct(
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer,
        RequestService $request,
        MessageService $message,
        AccessCheckerService $accessChecker,
        AuthorizationCheckerInterface $security
    ) {
        $this->em = $entityManager;
        $this->serializer = $serializer;
        $this->request = $request;
        $this->message = $message;
        $this->accessChecker = $accessChecker;
        $this->security = $security;
    }

    #[Route('/v1/chat', name: 'put_chat', methods: ['PUT'])]
    public function put(Request $request, MailerInterface $mailer)
    {
        /** @var \App\Entity\User $fromUser */
        $fromUser = $this->getUser();
        $id = $this->request->get($request, "touser");
        if ($fromUser->isBanned() && $id !== 1) {
            $this->accessChecker->checkAccess($fromUser);
        }

        $cache = new FilesystemAdapter();
        $chat = new Chat();
        $toUser = $this->em->getRepository(\App\Entity\User::class)->find($id);
        if (empty($this->em->getRepository(\App\Entity\BlockUser::class)->isBlocked($fromUser, $toUser))) {
            if (!$fromUser->isBanned() && $id == 1 && !$this->security->isGranted('ROLE_DEMO')) {
                throw new HttpException(400, "No se puede escribir al usuario frikiradar sin estar baneado - Error");
            }
            $chat->setTouser($toUser);
            $chat->setFromuser($fromUser);

            $min = min($chat->getFromuser()->getId(), $chat->getTouser()->getId());
            $max = max($chat->getFromuser()->getId(), $chat->getTouser()->getId());

            $conversationId = $min . "_" . $max;

            $tmp_id = $this->request->get($request, "tmp_id", false);
            if ($tmp_id) {
                $chat->setTmpId($tmp_id);
            }

            $text = $this->request->get($request, "text", false);
            $chat->setText($text);

            $chat->setTimeCreation();
            $chat->setConversationId($conversationId);

            $replyToChat = $this->em->getRepository(\App\Entity\Chat::class)->findOneBy(array('id' => $this->request->get($request, 'replyto', false)));
            if ($replyToChat && !$replyToChat->isModded()) {
                $chat->setReplyTo($replyToChat);
            }
            $this->em->persist($chat);
            $fromUser->setLastLogin();
            $this->em->persist($fromUser);
            $this->em->flush();

            $this->message->send($chat, $toUser, true);

            $cache->deleteItem('users.chat.' . $fromUser->getId());

            if ($fromUser->isBanned() && $id == 1) {
                // Enviamos email avisando
                $email = (new Email())
                    ->from($fromUser->getEmail())
                    ->to(new Address('hola@frikiradar.com', 'FrikiRadar'))
                    ->subject('Mensaje de usuario baneado')
                    ->html("El usuario baneado " . $fromUser->getName() . " ha escrito un mensaje: " . $text);

                $mailer->send($email);
            }

            return new JsonResponse($this->serializer->serialize($chat, "json", ['groups' => 'message', AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true]), Response::HTTP_OK, [], true);
        } else {
            throw new HttpException(400, "Error al marcar como leido - Error");
        }
    }

    #[Route('/v1/chat-upload', name: 'chat_upload', methods: ['POST'])]
    public function upload(Request $request)
    {
        /** @var \App\Entity\User $fromUser */
        $fromUser = $this->getUser();
        $this->accessChecker->checkAccess($fromUser);
        try {
            $cache = new FilesystemAdapter();
            $chat = new Chat();
            $toUser = $this->em->getRepository(\App\Entity\User::class)->find($request->request->get("touser"));
            if (empty($this->em->getRepository(\App\Entity\BlockUser::class)->isBlocked($fromUser, $toUser)) && $toUser->getUsername() !== 'frikiradar') {
                $chat->setTouser($toUser);
                $chat->setFromuser($fromUser);

                $min = min($chat->getFromuser()->getId(), $chat->getTouser()->getId());
                $max = max($chat->getFromuser()->getId(), $chat->getTouser()->getId());

                $conversationId = $min . "_" . $max;

                $imageFile = $request->files->get('image');
                $text = $request->request->get("text");
                $audioFile = $request->files->get('audio');
                $tmp_id = $request->request->get("tmp_id");
                if ($tmp_id) {
                    $chat->setTmpId($tmp_id);
                }

                // return new Response($this->serializer->serialize($audioFile, 'json'));

                $filename = date('YmdHis');
                if ($_SERVER['HTTP_HOST'] == 'localhost:8000') {
                    $absolutePath = 'images/chat/';
                    $server = "https://$_SERVER[HTTP_HOST]";
                    $uploader = new FileUploaderService($absolutePath . $conversationId . "/", $filename);
                    if ($imageFile) {
                        $image = $uploader->uploadImage($imageFile, false, 80, 1920);
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
                        $image = $uploader->uploadImage($imageFile, false, 80, 1920);
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


                if (empty($text) && isset($image)) {
                    $chat->setText('📷 ' . $fromUser->getName() . ' te ha enviado una imagen.');
                } elseif (isset($image)) {
                    $chat->setText('📷 ' . $text);
                } elseif (isset($audio)) {
                    $chat->setText('🎤 ' . $fromUser->getName() . ' te ha enviado un audio.');
                }

                $this->message->send($chat, $toUser, true);

                $cache->deleteItem('users.chat.' . $fromUser->getId());

                return new JsonResponse($this->serializer->serialize($chat, "json", ['groups' => 'message', AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true]), Response::HTTP_OK, [], true);
            } else {
                throw new HttpException(400, "Error al marcar como leido - Error");
            }
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al subir el archivo - Error: {$ex->getMessage()}");
        }
    }


    #[Route('/v1/chats', name: 'get_chats', methods: ['GET'])]
    public function getChats()
    {
        /** @var \App\Entity\User $fromUser */
        $fromUser = $this->getUser();
        $this->accessChecker->checkAccess($fromUser);
        try {
            $chats = $this->em->getRepository(\App\Entity\Chat::class)->getChatUsers($fromUser);
            $this->em->persist($fromUser);
            $this->em->flush();
            return new JsonResponse($this->serializer->serialize($chats, "json"), Response::HTTP_OK, [], true);
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al obtener los usuarios - Error: {$ex->getMessage()}");
        }
    }


    #[Route('/v1/chat/{id}', name: 'get_chat', methods: ['GET'])]
    public function getChatAction(int $id, Request $request)
    {
        /** @var \App\Entity\User $fromUser */
        $fromUser = $this->getUser();
        $cache = new FilesystemAdapter();
        $cache->deleteItem('users.chat.' . $fromUser->getId());

        $read = $this->request->get($request, "read");
        $page = $this->request->get($request, "page");
        $lastId = $this->request->get($request, "lastid", false) ?: 0;

        $toUser = $this->em->getRepository(\App\Entity\User::class)->findOneBy(array('id' => $id));

        $blocked = !empty($this->em->getRepository(\App\Entity\BlockUser::class)->isBlocked($fromUser, $toUser)) ? true : false;

        if ($fromUser->getId() !== $toUser->getId()) {
            //marcamos como leidos los antiguos
            $unreadChats = $this->em->getRepository(\App\Entity\Chat::class)->findBy(array('fromuser' => $toUser->getId(), 'touser' => $fromUser->getId(), 'time_read' => null));
            foreach ($unreadChats as $chat) {
                if (!is_null($chat->getFromuser())) {
                    $chat->setTimeRead(new \DateTime);
                    $this->em->persist($chat);

                    if ($fromUser->getId() !== $toUser->getId()) {
                        $this->message->send($chat, $toUser);
                    }
                }
            }
        }

        // Borrar cachés de notificaciones de chat
        $cache->deleteItem('users.notifications.' . $fromUser->getId());
        $this->em->persist($fromUser);
        $this->em->flush();

        $chats = $this->em->getRepository(\App\Entity\Chat::class)->getChat($fromUser, $toUser, $read, $page, $lastId, $fromUser->isBanned());
        foreach ($chats as $key => $chat) {
            if ((null !== $chat->getFromuser() && !$chat->getFromuser()->isActive()) || $blocked) {
                if ($blocked) {
                    $chats[$key]->getFromuser()->setUsername('Usuario desconocido');
                    $chats[$key]->getFromuser()->setName('Usuario desconocido');
                    $chats[$key]->getFromuser()->setAvatar(null);
                }
                $chats[$key]->getFromuser()->setActive(false);
                $chats[$key]->getFromuser()->setLastLogin(null);
            }
            if ((null !== $chat->getTouser() && !$chat->getTouser()->isActive()) || $blocked) {
                if ($blocked) {
                    $chats[$key]->getTouser()->setUsername('Usuario desconocido');
                    $chats[$key]->getTouser()->setName('Usuario desconocido');
                    $chats[$key]->getTouser()->setAvatar(null);
                }
                $chats[$key]->getTouser()->setActive(false);
                $chats[$key]->getTouser()->setLastLogin(null);
            }
        }

        return new JsonResponse($this->serializer->serialize($chats, "json", ['groups' => 'message']), Response::HTTP_OK, [], true);
    }


    #[Route('/v1/read-chat/{id}', name: 'read_chat', methods: ['GET'])]
    public function markAsReadAction(int $id)
    {
        try {
            /** @var \App\Entity\User $toUser */
            $toUser = $this->getUser();
            $chat = $this->em->getRepository(\App\Entity\Chat::class)->findOneBy(array('id' => $id));
            if ($chat->getTouser()->getId() == $toUser->getId()) {
                $chat->setTimeRead(new \DateTime);
                $this->em->persist($chat);
                $this->em->flush();

                $this->message->send($chat, $chat->getFromuser());

                // Borrar cachés de notificaciones de chat
                $cache = new FilesystemAdapter();
                $cache->deleteItem('users.notifications.' . $toUser->getId());

                return new JsonResponse($this->serializer->serialize($chat, "json", ['groups' => 'message', AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true]), Response::HTTP_OK, [], true);
            } else {
                throw new HttpException(401, "No se puede marcar como leído el chat de otro usuario");
            }
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al marcar como leido - Error: {$ex->getMessage()}");
        }
    }

    #[Route('/v1/writing-chat', name: 'writing_chat', methods: ['PUT'])]
    public function writingAction(Request $request)
    {
        /** @var \App\Entity\User $fromUser */
        $fromUser = $this->getUser();
        $toUser = $this->em->getRepository(\App\Entity\User::class)->findOneBy(array('id' => $this->request->get($request, "touser")));

        $chat = new Chat();
        $min = min($fromUser->getId(), $toUser->getId());
        $max = max($fromUser->getId(), $toUser->getId());

        $conversationId = $min . "_" . $max;

        $chat->setTouser($toUser);
        $chat->setFromuser($fromUser);
        $chat->setConversationId($conversationId);
        $chat->setWriting(true);

        $this->message->send($chat, $toUser);

        $data = [
            'code' => 200,
            'message' => "Escribiendo en chat",
        ];
        return new JsonResponse($data, 200);
    }

    #[Route('/v1/update-message', name: 'update_message', methods: ['PUT'])]
    public function updateMessageAction(Request $request)
    {
        try {
            /** @var \App\Entity\User $user */
            $user = $this->getUser();
            $id = $this->request->get($request, "id");
            $text = $this->request->get($request, "text");
            $chat = $this->em->getRepository(\App\Entity\Chat::class)->findOneBy(array('id' => $id));
            if ($chat->getFromuser()->getId() == $user->getId() && !$chat->isModded()) {
                $chat->setText($text);
                $chat->setEdited(1);
                $this->em->persist($chat);
                $this->em->flush();

                if ($chat->getTouser()) {
                    $this->message->send($chat, $chat->getTouser());
                    if (null !== ($chat->getTouser()) && $chat->getTouser()->getId() !== $chat->getFromuser()->getId()) {
                        $this->message->send($chat, $chat->getFromuser());
                    }
                }

                return new JsonResponse($this->serializer->serialize($chat, "json", ['groups' => 'message', AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true]), Response::HTTP_OK, [], true);
            } else {
                throw new HttpException(401, "No se puede editar el mensaje de otro usuario.");
            }
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al editar el mensaje - Error: {$ex->getMessage()}");
        }
    }


    #[Route('/v1/chat-message/{id}', name: 'delete_message', methods: ['DELETE'])]
    public function deleteMessageAction(int $id)
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $this->accessChecker->checkAccess($user);
        try {
            $cache = new FilesystemAdapter();
            $cache->deleteItem('users.chat.' . $user->getId());

            $message = $this->em->getRepository(\App\Entity\Chat::class)->findOneBy(array('id' => $id));
            if (!$message->isModded() && ($message->getFromuser()->getId() == $user->getId() || $this->security->isGranted('ROLE_MASTER'))) {
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

                $audio = $message->getAudio();
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
                    $message->setImage(null);
                    $message->setAudio(null);
                    $this->em->persist($message);
                    $this->em->flush();
                } else {
                    $this->em->remove($message);
                    $this->em->flush();
                    $message->setDeleted(1);
                }

                if ($message->getTouser()) {
                    $this->message->send($message, $message->getTouser());
                    if (!empty($message->getTouser()) && $message->getTouser()->getId() !== $message->getFromuser()->getId()) {
                        $this->message->send($message, $message->getFromuser());
                    }
                }

                return new JsonResponse($this->serializer->serialize($message, "json", ['groups' => 'message']), Response::HTTP_OK, [], true);
            } else {
                throw new HttpException(400, "Error al eliminar el mensaje. - Error: acción no permitida.");
            }
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al eliminar el mensaje - Error: {$ex->getMessage()}");
        }
    }


    #[Route('/v1/chat/{id}', name: 'delete_chat', methods: ['DELETE'])]
    public function deleteAction(int $id)
    {
        try {
            /** @var \App\Entity\User $fromUser */
            $fromUser = $this->getUser();
            $cache = new FilesystemAdapter();
            $cache->deleteItem('users.chat.' . $fromUser->getId());

            $toUser = $this->em->getRepository(\App\Entity\User::class)->findOneBy(array('id' => $id));
            $this->em->getRepository(\App\Entity\Chat::class)->deleteChatUser($toUser, $fromUser);

            return new JsonResponse($this->serializer->serialize($id, "json"), Response::HTTP_OK, [], true);
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al eliminar el mensaje - Error: {$ex->getMessage()}");
        }
    }

    #[Route('/v1/chats-config', name: 'chats_config', methods: ['PUT'])]
    public function chatsConfigAction(Request $request)
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $config = $user->getConfig();
        $chatsConfig = $this->request->get($request, "chats_config");
        $config['chats'] = $chatsConfig;
        $user->setConfig($config);
        $this->em->persist($user);
        $this->em->flush();

        return new JsonResponse($this->serializer->serialize($user, "json", ['groups' => ['default', 'tags']]), Response::HTTP_OK, [], true);
    }

    #[Route('/v1/report-chat', name: 'report_chat', methods: ['PUT'])]
    public function putReportAction(Request $request, MailerInterface $mailer)
    {
        try {
            /**
             * @var Chat
             */
            $chat = $this->request->get($request, 'message', true);
            $note = $this->request->get($request, 'note', false);

            $username = $chat['fromuser']['username'];
            $text = $chat['text'];
            $room = $chat['conversationId'];

            // Enviar email al administrador informando del motivo
            /** @var \App\Entity\User $user */
            $user = $this->getUser();
            $email = (new Email())
                ->from(new Address('hola@frikiradar.com', 'FrikiRadar'))
                ->to(new Address('hola@frikiradar.com', 'FrikiRadar'))
                ->subject('Nuevo mensaje reportado')
                ->html("El usuario " . $user->getUsername() . " ha reportado un mensaje en <a href='https://frikiradar.app/room/" . $room . "'>" . $room . "</a> del usuario <a href='https://frikiradar.app/" . urlencode($username) . "'>" . $username . "</a> por el siguiente motivo: " . $note . "<br><br>Contenido del mensaje: " . $text);

            $mailer->send($email);

            return new JsonResponse($this->serializer->serialize("Mensaje reportado correctamente", "json"), Response::HTTP_OK, [], true);
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al reportar mensaje - Error: {$ex->getMessage()}");
        }
    }
}
