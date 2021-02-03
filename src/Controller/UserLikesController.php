<?php
// src/Controller/ChatController.php
namespace App\Controller;

use App\Entity\LikeUser;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mercure\Publisher;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Service\NotificationService;
use App\Service\RequestService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Class UserLikesController
 *
 * @Route(path="/api")
 */
class UserLikesController extends AbstractController
{
    public function __construct(
        UserRepository $userRepository,
        SerializerInterface $serializer,
        EntityManagerInterface $entityManager,
        RequestService $request,
        NotificationService $notification
    ) {
        $this->serializer = $serializer;
        $this->userRepository = $userRepository;
        $this->em = $entityManager;
        $this->request = $request;
        $this->notification = $notification;
    }


    /**
     * @Route("/v1/like", name="like", methods={"PUT"})
     */
    public function putLikeAction(Request $request)
    {
        try {
            $toUser = $this->em->getRepository('App:User')->findOneBy(array('id' => $this->request->get($request, 'user')));

            $like = $this->em->getRepository('App:LikeUser')->findOneBy(array('to_user' => $toUser, 'from_user' => $this->getUser()));

            if (empty($like)) {
                $newLike = new LikeUser();
                $newLike->setFromUser($this->getUser());
                $newLike->setToUser($toUser);
                $this->em->persist($newLike);
                $this->em->flush();

                $title = $newLike->getFromUser()->getUsername();
                $text = "Te ha entregado su kokoro ❤️, ya puedes comenzar a chatear.";
                $url = "/profile/" . $newLike->getFromUser()->getId();

                $this->notification->push($newLike->getFromuser(), $newLike->getTouser(), $title, $text, $url, "like");
            }

            $user = $this->em->getRepository('App:User')->findeOneUser($this->getUser(), $toUser);

            return new Response($this->serializer->serialize($user, "json", ['groups' => 'default']));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al entregar tu kokoro - Error: {$ex->getMessage()}");
        }
    }


    /**
     * @Route("/v1/like/{id}", name="unlike", methods={"DELETE"})
     */
    public function removeLikeAction(int $id)
    {
        try {
            $toUser = $this->em->getRepository('App:User')->findOneBy(array('id' => $id));
            $like = $this->em->getRepository('App:LikeUser')->findOneBy(array('to_user' => $toUser, 'from_user' => $this->getUser()));
            $this->em->remove($like);
            $this->em->flush();

            $user = $this->em->getRepository('App:User')->findeOneUser($this->getUser(), $toUser);

            return new Response($this->serializer->serialize($user, "json", ['groups' => 'default']));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al retirarle tu kokoro - Error: {$ex->getMessage()}");
        }
    }


    /**
     * @Route("/v1/likes", name="get_likes", methods={"GET"})
     */
    public function getLikesAction(Request $request)
    {
        try {
            $likes = $this->em->getRepository('App:LikeUser')->getLikeUsers($this->getUser(), $this->request->get($request, "param") ?: "received");

            foreach ($likes as $key => $like) {
                $userId = $like["fromuser"];
                $user = $this->em->getRepository('App:User')->findOneBy(array('id' => $userId));
                $likes[$key]['user'] = [
                    'id' => $userId,
                    'username' => $user->getUsername(),
                    'name' => $user->getName(),
                    'description' => $user->getDescription(),
                    'avatar' =>  $user->getAvatar() ?: null
                ];
            }
            return new Response($this->serializer->serialize($likes, "json"));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al obtener los likes - Error: {$ex->getMessage()}");
        }
    }


    /**
     * @Route("/v1/read-like/{id}", name="read_like", methods={"GET"})
     */
    public function markAsReadAction(int $id)
    {
        try {
            $like = $this->em->getRepository('App:LikeUser')->findOneBy(array('from_user' => $id, 'to_user' => $this->getUser()->getId()));
            $like->setTimeRead(new \DateTime);
            $this->em->persist($like);
            $this->em->flush();

            return new Response($this->serializer->serialize($like, "json", ['groups' => 'like']));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al marcar como leido - Error: {$ex->getMessage()}");
        }
    }
}
