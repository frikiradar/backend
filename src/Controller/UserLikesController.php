<?php
// src/Controller/ChatController.php
namespace App\Controller;

use App\Entity\LikeUser;
use App\Repository\UserRepository;
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
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
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
        NotificationService $notification,
        AuthorizationCheckerInterface $security
    ) {
        $this->serializer = $serializer;
        $this->userRepository = $userRepository;
        $this->em = $entityManager;
        $this->request = $request;
        $this->notification = $notification;
        $this->security = $security;
    }


    /**
     * @Route("/v1/like", name="like", methods={"PUT"})
     */
    public function putLikeAction(Request $request)
    {
        $fromUser = $this->getUser();
        try {
            $toUser = $this->em->getRepository(\App\Entity\User::class)->findOneBy(array('id' => $this->request->get($request, 'user')));
            $cache = new FilesystemAdapter();
            $cache->deleteItem('users.get.' . $fromUser->getId() . '.' . $toUser->getId());
            $cache->deleteItem('users.get.' . $toUser->getId());

            $like = $this->em->getRepository(\App\Entity\LikeUser::class)->findOneBy(array('to_user' => $toUser, 'from_user' => $this->getUser()));

            if (empty($like)) {
                $newLike = new LikeUser();
                $newLike->setFromUser($fromUser);
                $newLike->setToUser($toUser);
                $this->em->persist($newLike);
                $this->em->flush();

                $title = $newLike->getFromUser()->getUsername();
                $text = "Te ha entregado su kokoro ❤️, ya puedes comenzar a chatear.";
                $url = "/profile/" . $newLike->getFromUser()->getId();

                $this->notification->set($newLike->getFromuser(), $newLike->getTouser(), $title, $text, $url, "like");
            }

            $user = $this->em->getRepository(\App\Entity\User::class)->findOneUser($fromUser, $toUser);

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
        $fromUser = $this->getUser();
        try {
            $toUser = $this->em->getRepository(\App\Entity\User::class)->findOneBy(array('id' => $id));
            $cache = new FilesystemAdapter();
            $cache->deleteItem('users.get.' . $fromUser->getId() . '.' . $toUser->getId());
            $cache->deleteItem('users.get.' . $toUser->getId());

            $like = $this->em->getRepository(\App\Entity\LikeUser::class)->findOneBy(array('to_user' => $toUser, 'from_user' => $this->getUser()));
            $this->em->remove($like);
            $this->em->flush();

            $user = $this->em->getRepository(\App\Entity\User::class)->findOneUser($fromUser, $toUser);

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
        $cache = new FilesystemAdapter();
        try {
            $param = $this->request->get($request, "param") ?: "received";
            $page = $this->request->get($request, "page", false);
            $id = $this->request->get($request, "user", false);
            if ($id) {
                $user = $this->em->getRepository(\App\Entity\User::class)->findOneBy(array('id' => $id));
            } else {
                $user = $this->getUser();
            }

            if ($user->getId() === $this->getUser()->getId() || !$user->isHideLikes() || $this->security->isGranted('ROLE_MASTER')) {
                $likesCache = $cache->getItem('users.likes.' . $user->getId() . $param . $page);
                if (!$likesCache->isHit()) {
                    $likesCache->expiresAfter(5 * 60);
                    $likes = $this->em->getRepository(\App\Entity\LikeUser::class)->getLikeUsers($user, $param, $page);
                    $likesCache->set($likes);
                    $cache->save($likesCache);
                } else {
                    $likes = $likesCache->get();
                }
                return new Response($this->serializer->serialize($likes, "json", ['groups' => 'default']));
            } else {
                throw new HttpException(400, "El usuario no permite ver sus kokoros");
            }
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
            $like = $this->em->getRepository(\App\Entity\LikeUser::class)->findOneBy(array('from_user' => $id, 'to_user' => $this->getUser()->getId()));
            $like->setTimeRead(new \DateTime);
            $this->em->persist($like);
            $this->em->flush();

            return new Response($this->serializer->serialize($like, "json", ['groups' => 'like']));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al marcar como leido - Error: {$ex->getMessage()}");
        }
    }
}
