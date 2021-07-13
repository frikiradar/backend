<?php
// src/Controller/RoomsController.php
namespace App\Controller;

use App\Entity\Chat;
use App\Entity\Room;
use App\Repository\ChatRepository;
use App\Service\AccessCheckerService;
use App\Service\FileUploaderService;
use App\Service\MessageService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Service\NotificationService;
use App\Service\RequestService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Class RoomsController
 *
 * @Route(path="/api")
 */
class RoomsController extends AbstractController
{
    public function __construct(
        ChatRepository $chatRepository,
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer,
        RequestService $request,
        NotificationService $notification,
        MessageService $message,
        AccessCheckerService $accessChecker,
        AuthorizationCheckerInterface $security
    ) {
        $this->chatRepository = $chatRepository;
        $this->em = $entityManager;
        $this->serializer = $serializer;
        $this->request = $request;
        $this->notification = $notification;
        $this->message = $message;
        $this->accessChecker = $accessChecker;
        $this->security = $security;
    }

    /**
     * @Route("/v1/rooms", name="get_rooms", methods={"GET"})
     */
    public function getRoomsAction()
    {
        $fromUser = $this->getUser();
        $cache = new FilesystemAdapter();
        $roomsCache = $cache->getItem('rooms.list.visible');
        if (!$roomsCache->isHit()) {
            $roomsCache->expiresAfter(3600 * 24);
            $rooms = $this->em->getRepository('App:Room')->findVisibleRooms();
            $roomsCache->set($rooms);
            $cache->save($roomsCache);
        } else {
            $rooms = $roomsCache->get();
        }

        $roomsSlugs = [];
        foreach ($rooms as $room) {
            $roomsSlugs[] = $room['slug'];
        }

        // Añadimos a las rooms de frikiradar las salas de páginas que el user haya hablado
        $pagesSlugs = $this->em->getRepository('App:Room')->findPageRooms($fromUser);
        $pageRooms = [];
        foreach ($pagesSlugs as $slug) {
            if (!in_array($slug['conversationId'], $roomsSlugs)) {
                $page = $this->em->getRepository('App:Page')->findOneBy(array('slug' => $slug['conversationId']));
                if (!empty($page)) {
                    $room = [];
                    $room['name'] = $page->getName();
                    $room['description'] = '#' . $slug['conversationId'];
                    $room['slug'] = $slug['conversationId'];
                    $room['permissions'] = ['ROLE_USER'];
                    $room['visible'] = false;
                    $room['image'] = $page->getCover();
                    $pageRooms[] = $room;
                }
            }
        }

        $rooms = [...$rooms, ...$pageRooms];

        foreach ($rooms as $room) {
            $slugs[] = $room['slug'];
        }
        $messages = $this->em->getRepository('App:Room')->getLastMessages($slugs, $fromUser);
        foreach ($rooms as $key => $room) {
            foreach ($messages as $message) {
                if ($message['conversationId'] == $room['slug']) {
                    $rooms[$key]['last_message'] = +$message['last_message'];
                }
            }
        }

        return new Response($this->serializer->serialize($rooms, "json", ['groups' => ['default']]));
    }

    /**
     * @Route("/v1/room/{slug}", name="get_room", methods={"GET"})
     */
    public function getRoomAction(string $slug, Request $request)
    {
        $user = $this->getUser();
        $cache = new FilesystemAdapter();
        $roomCache = $cache->getItem('room.' . $slug);
        $cache->delete('room.' . $slug);
        try {
            if (!$roomCache->isHit()) {
                $room = $this->em->getRepository('App:Room')->findOneBy(array('slug' => $slug));
                if (empty($room)) {
                    if (strpos($slug, 'event-') !== false) {
                        $id = explode('-', $slug)[1];
                        $event = $this->em->getRepository('App:Event')->findOneBy(array('id' => $id));
                        if (!empty($event)) {
                            $room = new Room();
                            $room->setEvent($event);
                            $room->setName($event->getTitle());
                            $room->setDescription($event->getDescription());
                            $room->setSlug($slug);
                            $room->setPermissions(['ROLE_USER']);
                            $room->setVisible(false);
                            $room->setImage($event->getImage());
                        }
                    } else {
                        $page = $this->em->getRepository('App:Page')->findOneBy(array('slug' => $slug));
                        if (!empty($page)) {
                            $room = new Room();
                            $room->setPage($page);
                            $room->setName($page->getName());
                            $room->setDescription($page->getDescription());
                            $room->setSlug($page->getSlug());
                            $room->setPermissions(['ROLE_USER']);
                            $room->setVisible(false);
                            $room->setImage($page->getCover());
                        }
                    }
                }
                $roomCache->set($room);
                $cache->save($roomCache);
            } else {
                $room = $roomCache->get();
            }

            if (!isset($event)) {
                $messages = $this->em->getRepository('App:Room')->getLastMessages([$slug], $user);
                if (isset($messages[0])) {
                    $room->setLastMessage($messages[0]['last_message']);
                }
            }

            if (!empty($room)) {
                return new Response($this->serializer->serialize($room, "json", ['groups' => 'default']));
            } else {
                throw new HttpException(404, "Sala de chat no encontrada");
            }
        } catch (Exception $ex) {
            throw new HttpException(404, "Sala de chat no encontrada - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Route("/v1/room-messages/{slug}", name="get_room_messages", methods={"GET"})
     */
    public function getRoomMessagesAction(string $slug, Request $request)
    {
        $user = $this->getUser();
        $this->accessChecker->checkAccess($user);
        $page = $this->request->get($request, "page");
        $chats = $this->em->getRepository('App:Chat')->getRoomChat($slug, $page);
        /*foreach ($chats as $key => $chat) {
            if ($chat->getFromuser()->getBanned() || !$chat->getFromuser()->getActive()) {
                unset($chats[$key]);
            }
        }*/
        $chats = array_values($chats);

        return new Response($this->serializer->serialize($chats, "json", ['groups' => 'message', AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true]));
    }

    /**
     * @Route("/v1/room-message", name="put_room_message", methods={"PUT"})
     */
    public function putMessageAction(Request $request, \Swift_Mailer $mailer)
    {
        $fromUser = $this->getUser();
        $slug = $this->request->get($request, "slug");
        $name = $this->request->get($request, "name");

        $this->accessChecker->checkAccess($fromUser);
        $text = trim($this->request->get($request, "text", false));

        try {
            if (!empty($text)) {
                $chat = new Chat();
                $chat->setTouser(null);
                $chat->setFromuser($fromUser);
                $chat->setText($text);
                $chat->setTimeCreation();
                $chat->setConversationId($slug);

                $mentions = array_unique($this->request->get($request, "mentions", false));
                if ($mentions) {
                    $chat->setMentions($mentions);
                }

                $replyToChat = $this->em->getRepository('App:Chat')->findOneBy(array('id' => $this->request->get($request, 'replyto', false)));
                if ($replyToChat) {
                    $chat->setReplyTo($replyToChat);
                }
                $this->em->persist($chat);
                $this->em->flush();

                $this->message->sendTopic($chat, 'rooms', false);

                $url = "/room/" . $slug;

                if (count((array) $mentions) > 0 || $replyToChat) {
                    foreach ($mentions as $mention) {
                        $toUser = $this->em->getRepository('App:User')->findOneBy(array('username' => $mention));
                        $title = $fromUser->getUsername() . ' te ha mencionado en ' . $name;
                        $this->notification->set($fromUser, $toUser, $title, $text, $url, 'room');
                    }

                    if ($replyToChat) {
                        $toUser = $replyToChat->getFromuser();
                        if ($toUser->getId() !== $fromUser->getId()) {
                            $title = $fromUser->getUsername() . ' ha respondido a tu mensaje en ' . $name;
                            $this->notification->set($fromUser, $toUser, $title, $text, $url, 'room');
                        }
                    }
                } else {
                    $title = $fromUser->getUsername() . ' en ' . $name;
                    $this->notification->pushTopic($fromUser, $slug, $title, $text, $url);
                }

                if ($slug == 'frikiradar-bugs' && !$this->security->isGranted('ROLE_MASTER')) {
                    // Enviamos email avisando
                    $message = (new \Swift_Message('Nuevo mensaje de bug'))
                        ->setFrom([$fromUser->getEmail() => $fromUser->getUsername()])
                        ->setTo(['hola@frikiradar.com' => 'FrikiRadar'])
                        ->setBody("El usuario " . $fromUser->getUsername() . " ha notificado un bug: " . $text, 'text/html');

                    if (0 === $mailer->send($message)) {
                        // throw new HttpException(400, "Error al enviar el email avisando el bug");
                    }
                }

                return new Response($this->serializer->serialize($chat, "json", ['groups' => 'message', AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true]));
            } else {
                throw new HttpException(400, "El texto no puede estar vacío");
            }
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al publicar el mensaje - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Route("/v1/room-upload", name="put_room_upload", methods={"POST"})
     */
    public function upload(Request $request)
    {
        $fromUser = $this->getUser();
        $this->accessChecker->checkAccess($fromUser);
        try {
            $imageFile = $request->files->get('image');
            $text = trim($request->request->get("text"));

            if (!empty($imageFile)) {
                $chat = new Chat();
                $slug = $request->request->get("slug");
                $name = $request->request->get("name");

                $chat->setTouser(null);
                $chat->setFromuser($fromUser);

                $mentions = array_unique(json_decode($request->request->get("mentions"), true));
                if ($mentions) {
                    $chat->setMentions($mentions);
                }

                $filename = microtime(true);
                if ($_SERVER['HTTP_HOST'] == 'localhost:8000') {
                    $absolutePath = 'images/chat/';
                    $server = "https://$_SERVER[HTTP_HOST]";
                    $uploader = new FileUploaderService($absolutePath . $slug . "/", $filename);
                    $image = $uploader->uploadImage($imageFile, false, 80, 1920);
                    $chat->setImage($image);
                    $chat->setText($text);
                    $chat->setTimeCreation();
                    $chat->setConversationId($slug);
                } else {
                    $absolutePath = '/var/www/vhosts/frikiradar.com/app.frikiradar.com/images/chat/';
                    $server = "https://app.frikiradar.com";
                    $uploader = new FileUploaderService($absolutePath . $slug . "/", $filename);
                    $image = $uploader->uploadImage($imageFile, false, 80, 1920);
                    $src = str_replace("/var/www/vhosts/frikiradar.com/app.frikiradar.com", $server, $image);
                    $chat->setImage($src);
                    $chat->setText($text);
                    $chat->setTimeCreation();
                    $chat->setConversationId($slug);
                    $this->em->persist($chat);
                    $this->em->persist($fromUser);
                    $this->em->flush();
                }

                $this->message->sendTopic($chat, 'rooms', false);

                $url = "/room/" . $slug;

                if (count((array) $mentions) > 0) {
                    foreach ($mentions as $mention) {
                        $toUser = $this->em->getRepository('App:User')->findOneBy(array('username' => $mention));
                        $title = $fromUser->getUsername() . ' te ha mencionado en ' . $name;
                        $this->notification->set($fromUser, $toUser, $title, $text, $url, 'room');
                    }
                } else {
                    $title = $fromUser->getUsername() . ' en ' . $name;
                    $this->notification->pushTopic($fromUser, $slug, $title, $text, $url);
                }

                return new Response($this->serializer->serialize($chat, "json", ['groups' => 'message', AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true]));
            } else {
                throw new HttpException(400, "La imagen subida está vacía.");
            }
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al subir el archivo - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Route("/v1/writing-room", name="writing_room", methods={"PUT"})
     */
    public function writingAction(Request $request)
    {
        $fromUser = $this->getUser();

        $slug = $this->request->get($request, "slug");

        $chat = new Chat();
        $chat->setFromuser($fromUser);
        $chat->setConversationId($slug);
        $chat->setWriting(true);

        $this->message->sendTopic($chat, 'rooms', false);

        return new Response($this->serializer->serialize("Escribiendo en chat", "json"));
    }

    /**
     * @Route("/v1/rooms-config", name="rooms_config", methods={"PUT"})
     */
    public function roomsConfigAction(Request $request)
    {
        $user = $this->getUser();
        $config = $user->getConfig();
        $roomsConfig = $this->request->get($request, "rooms_config");
        $config['rooms'] = $roomsConfig;
        $user->setConfig($config);
        $this->em->persist($user);
        $this->em->flush();

        return new Response($this->serializer->serialize($user, "json", ['groups' => ['default', 'tags']]));
    }
}
