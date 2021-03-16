<?php
// src/Controller/RoomsController.php
namespace App\Controller;

use App\Entity\Chat;
use App\Entity\Room;
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
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

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
        AccessCheckerService $accessChecker
    ) {
        $this->chatRepository = $chatRepository;
        $this->em = $entityManager;
        $this->serializer = $serializer;
        $this->request = $request;
        $this->notification = $notification;
        $this->accessChecker = $accessChecker;
    }

    /**
     * @Route("/v1/rooms", name="get_rooms", methods={"GET"})
     */
    public function getRoomsAction()
    {
        $cache = new FilesystemAdapter();
        $roomsCache = $cache->getItem('rooms.list.visible');
        if (!$roomsCache->isHit()) {
            $rooms = $this->em->getRepository('App:Room')->findVisibleRooms();
            $roomsCache->set($rooms);
            $cache->save($roomsCache);
        } else {
            $rooms = $roomsCache->get();
        }

        foreach ($rooms as $room) {
            $slugs[] = $room['slug'];
        }

        $messages = $this->em->getRepository('App:Room')->getLastMessages($slugs);
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
        $cache = new FilesystemAdapter();
        $roomCache = $cache->getItem('room.' . $slug);
        if (!$roomCache->isHit()) {
            $room = $this->em->getRepository('App:Room')->findOneBy(array('slug' => $slug));
            $roomCache->set($room);
            $cache->save($roomCache);
        } else {
            $room = $roomCache->get();
        }

        return new Response($this->serializer->serialize($room, "json", ['groups' => 'default']));
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

        return new Response($this->serializer->serialize($chats, "json", ['groups' => 'message', AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true]));
    }

    /**
     * @Route("/v1/room-message", name="put_room_message", methods={"PUT"})
     */
    public function putMessageAction(Request $request, PublisherInterface $publisher)
    {
        $fromUser = $this->getUser();
        $slug = $this->request->get($request, "slug");
        $name = $this->request->get($request, "name");

        $this->accessChecker->checkAccess($fromUser);

        $chat = new Chat();
        $chat->setTouser(null);
        $chat->setFromuser($fromUser);

        $text = $this->request->get($request, "text", false);

        $chat->setText($text);
        $chat->setTimeCreation();
        $chat->setConversationId($slug);

        $mentions = $this->request->get($request, "mentions", false);
        if ($mentions) {
            $chat->setMentions($mentions);
        }

        $replyToChat = $this->em->getRepository('App:Chat')->findOneBy(array('id' => $this->request->get($request, 'replyto', false)));
        if ($replyToChat) {
            $chat->setReplyTo($replyToChat);
        }
        $this->em->persist($chat);
        $this->em->flush();

        $update = new Update($slug, $this->serializer->serialize($chat, "json", ['groups' => 'message']));
        $publisher($update);

        $url = "/room/" . $slug;

        if (count((array) $mentions) > 0) {
            foreach ($mentions as $mention) {
                $toUser = $this->em->getRepository('App:User')->findOneBy(array('username' => $mention));
                $title = $fromUser->getUsername() . ' te ha mencionado en ' . $name;
                $this->notification->push($fromUser, $toUser, $title, $text, $url, 'chat');
            }
        } else {
            $title = $fromUser->getUsername() . ' en ' . $name;
            $this->notification->pushTopic($fromUser, $slug, $title, $text, $url);
        }

        return new Response($this->serializer->serialize($chat, "json", ['groups' => 'message', AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true]));
    }

    /**
     * @Route("/v1/room-upload", name="put_room_upload", methods={"POST"})
     */
    public function upload(Request $request, PublisherInterface $publisher)
    {
        $fromUser = $this->getUser();
        $this->accessChecker->checkAccess($fromUser);
        try {
            $chat = new Chat();
            $slug = $request->request->get("slug");
            $name = $request->request->get("name");

            $chat->setTouser(null);
            $chat->setFromuser($fromUser);

            $imageFile = $request->files->get('image');
            $text = $request->request->get("text");

            $mentions = $this->request->get($request, "mentions", false);
            if ($mentions) {
                $chat->setMentions($mentions);
            }

            $filename = microtime(true);
            if ($_SERVER['HTTP_HOST'] == 'localhost:8000') {
                $absolutePath = 'images/chat/';
                $server = "https://$_SERVER[HTTP_HOST]";
                $uploader = new FileUploaderService($absolutePath . $slug . "/", $filename);
                $image = $uploader->upload($imageFile, false, 70);
                $chat->setImage($image);
                $chat->setText($text);
                $chat->setTimeCreation();
                $chat->setConversationId($slug);
            } else {
                $absolutePath = '/var/www/vhosts/frikiradar.com/app.frikiradar.com/images/chat/';
                $server = "https://app.frikiradar.com";
                $uploader = new FileUploaderService($absolutePath . $slug . "/", $filename);
                $image = $uploader->upload($imageFile, false, 50);
                $src = str_replace("/var/www/vhosts/frikiradar.com/app.frikiradar.com", $server, $image);
                $chat->setImage($src);
                $chat->setText($text);
                $chat->setTimeCreation();
                $chat->setConversationId($slug);
                $this->em->persist($chat);
                $fromUser->setLastLogin();
                $this->em->persist($fromUser);
                $this->em->flush();
            }

            $update = new Update($slug, $this->serializer->serialize($chat, "json", ['groups' => 'message', AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true]));
            $publisher($update);

            $url = "/room/" . $slug;

            if (count((array) $mentions) > 0) {
                foreach ($mentions as $mention) {
                    $toUser = $this->em->getRepository('App:User')->findOneBy(array('username' => $mention));
                    $title = $fromUser->getUsername() . ' te ha mencionado en ' . $name;
                    $this->notification->push($fromUser, $toUser, $title, $text, $url, 'chat');
                }
            } else {
                $title = $fromUser->getUsername() . ' en ' . $name;
                $this->notification->pushTopic($fromUser, $slug, $title, $text, $url);
            }

            return new Response($this->serializer->serialize($chat, "json", ['groups' => 'message', AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true]));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al subir el archivo - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Route("/v1/writing-room", name="writing_room", methods={"PUT"})
     */
    public function writingAction(Request $request, PublisherInterface $publisher)
    {
        $name = $this->request->get($request, "name");
        $slug = $this->request->get($request, "slug");

        $chat = [];
        $chat['fromuser']['name'] = $name;
        $chat['conversation_id'] = $slug;
        $chat['writing'] = true;

        $update = new Update($slug, json_encode($chat));
        $publisher($update);

        return new Response($this->serializer->serialize("Escribiendo en chat", "json"));
    }
}
