<?php
// src/Controller/StoriesController.php
namespace App\Controller;

use App\Entity\Comment;
use App\Entity\LikeStory;
use App\Entity\Story;
use App\Entity\ViewStory;
use App\Service\AccessCheckerService;
use App\Service\FileUploaderService;
use App\Service\NotificationService;
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
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Class StoriesController
 *
 * @Route(path="/api")
 */
class StoriesController extends AbstractController
{
    public function __construct(
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer,
        RequestService $request,
        AccessCheckerService $accessChecker,
        NotificationService $notification
    ) {
        $this->em = $entityManager;
        $this->serializer = $serializer;
        $this->request = $request;
        $this->accessChecker = $accessChecker;
        $this->notification = $notification;
    }

    /**
     * @Route("/v1/stories", name="get_stories", methods={"GET"})
     */
    public function getStoriesAction()
    {
        $user = $this->getUser();
        $this->accessChecker->checkAccess($user);
        $stories = $this->em->getRepository('App:Story')->getStories($user);

        return new Response($this->serializer->serialize($stories, "json", ['groups' => ['story']]));
    }

    /**
     * @Route("/v1/all-stories", name="get_all_stories", methods={"GET"})
     */
    public function getAllStoriesAction()
    {
        $user = $this->getUser();
        $this->accessChecker->checkAccess($user);
        $stories = $this->em->getRepository('App:Story')->getAllStories();

        return new Response($this->serializer->serialize($stories, "json", ['groups' => ['story']]));
    }

    /**
     * @Route("/v1/user-stories/{id}", name="get_user_stories", methods={"GET"})
     */
    public function getUserStoriesAction(int $id)
    {
        $cache = new FilesystemAdapter();
        try {
            $storiesCache = $cache->getItem('stories.get.' . $id);
            if (!$storiesCache->isHit()) {
                $user = $this->em->getRepository('App:User')->findOneBy(array('id' => $id));
                $stories = $user->getStories();
                $stories = $this->serializer->serialize($stories, "json", ['groups' => ['story']]);
                $storiesCache->set($stories);
                $cache->save($storiesCache);
            } else {
                $stories = $storiesCache->get();
            }
            return new Response($stories);
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al obtener las historias del usuario - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Route("/v1/story/{id}", name="get_story", methods={"GET"})
     */
    public function getStoryAction(int $id)
    {
        $user = $this->getUser();
        $this->accessChecker->checkAccess($user);
        try {
            $story = $this->em->getRepository('App:Story')->findOneBy(array('id' => $id));
            if (!is_null($story)) {
                return new Response($this->serializer->serialize($story, "json", ['groups' => ['story']]));
            } else {
                throw new HttpException(400, "Historia no encontrada");
            }
        } catch (Exception $ex) {
            throw new HttpException(400, "Historia no encontrada - Error: {$ex->getMessage()}");
        }
    }


    /**
     * @Route("/v1/story-upload", name="story_upload", methods={"POST"})
     */
    public function upload(Request $request)
    {
        $fromUser = $this->getUser();
        $this->accessChecker->checkAccess($fromUser);
        try {
            $cache = new FilesystemAdapter();
            $cache->deleteItem('stories.get.' . $fromUser->getId());
            $story = new Story();
            $imageFile = $request->files->get('image');
            $text = $request->request->get("text");

            $story->setText($text);
            $story->setUser($fromUser);
            $mentions = array_unique(json_decode($request->request->get("mentions"), true));
            if ($mentions) {
                $story->setMentions($mentions);
            }

            $filename = microtime(true);
            $absolutePath = '/var/www/vhosts/frikiradar.com/app.frikiradar.com/images/stories/';
            $server = "https://app.frikiradar.com";
            $uploader = new FileUploaderService($absolutePath . $fromUser->getId() . "/", $filename);
            $image = $uploader->upload($imageFile, true, 80);
            $src = str_replace("/var/www/vhosts/frikiradar.com/app.frikiradar.com", $server, $image);
            $story->setImage($src);
            $story->setTimeCreation();
            $this->em->persist($story);
            $this->em->flush();

            if (count((array) $mentions) > 0) {
                $url = "/tabs/community/story/" . $story->getId();
                foreach ($mentions as $mention) {
                    $toUser = $this->em->getRepository('App:User')->findOneBy(array('username' => $mention));
                    $title = $fromUser->getName() . ' te ha mencionado en una historia.';
                    $this->notification->push($fromUser, $toUser, $title, $text, $url, 'story');
                }
            }

            return new Response($this->serializer->serialize($story, "json"));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al subir el archivo - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Route("/v1/view-story", name="view_story", methods={"PUT"})
     */
    public function putViewAction(Request $request)
    {
        try {
            $user = $this->getUser();
            /**
             * @var Story
             */
            $story = $this->em->getRepository('App:Story')->findOneBy(array('id' => $this->request->get($request, 'story')));
            $cache = new FilesystemAdapter();
            $cache->deleteItem('stories.get.' . $story->getUser()->getId());

            $viewed = false;
            foreach ($story->getViewStories() as $view) {
                if ($view->getUser()->getId() == $user->getId()) {
                    $viewed = true;
                }
            }
            if (!$viewed && $story->getUser()->getId() !== $user->getId()) {
                $view = new ViewStory();
                $view->setDate(new \DateTime);
                $view->setStory($story);
                $view->setUser($user);
                $this->em->persist($view);
                $this->em->flush();

                return new Response($this->serializer->serialize("Historia vista correctamente", "json"));
            } else {
                throw new HttpException(404, "No puedes marcar como vista tu propia historia o ver la misma historia dos veces.");
            }
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al ver la historia - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Route("/v1/delete-story/{id}", name="delete_story", methods={"DELETE"})
     */
    public function removeStoryAction(int $id)
    {
        try {
            /**
             * @var Story
             */
            $story = $this->em->getRepository('App:Story')->findOneBy(array('id' => $id));
            $cache = new FilesystemAdapter();
            $cache->deleteItem('stories.get.' . $story->getUser()->getId());

            if ($story->getUser()->getId() === $this->getUser()->getId()) {
                $image = $story->getImage();
                if ($image) {
                    $file = "/var/www/vhosts/frikiradar.com/app.frikiradar.com/images/rooms/" . $image;
                    unlink($file);
                }

                $this->em->remove($story);
                $this->em->flush();

                $data = [
                    'code' => 200,
                    'message' => "Historia eliminada correctamente",
                ];
                return new JsonResponse($data, 200);
            } else {
                throw new HttpException(404, "No se puede eliminar la historia de otro usuario.");
            }
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al eliminar la historia - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Route("/v1/like-story", name="like_story", methods={"PUT"})
     */
    public function putLikeAction(Request $request)
    {
        $user = $this->getUser();
        $this->accessChecker->checkAccess($user);
        try {
            $story = $this->em->getRepository('App:Story')->findOneBy(array('id' => $this->request->get($request, 'story')));
            $like = $this->em->getRepository('App:LikeStory')->findOneBy(array('story' => $story, 'user' => $user));

            if (empty($like)) {
                $newLike = new LikeStory();
                $newLike->setUser($user);
                $newLike->setStory($story);
                $newLike->setDate();
                $this->em->persist($newLike);
                $this->em->flush();

                $title = $user->getName();
                $text = "A " . $user->getName() . " le ha gustado tu historia ❤️.";
                $url = "/tabs/community/story/" . $story->getId();

                $this->notification->push($user, $story->getUser(), $title, $text, $url, "story");
            }

            $story = $this->em->getRepository('App:Story')->findOneBy(array('id' => $this->request->get($request, 'story')));
            $cache = new FilesystemAdapter();
            $cache->deleteItem('stories.get.' . $story->getUser()->getId());

            return new Response($this->serializer->serialize($story, "json", ['groups' => 'story']));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al entregar kokoro a una historia - Error: {$ex->getMessage()}");
        }
    }


    /**
     * @Route("/v1/like-story/{id}", name="unlike_story", methods={"DELETE"})
     */
    public function removeLikeAction(int $id)
    {
        $user = $this->getUser();
        $this->accessChecker->checkAccess($user);

        try {
            $story = $this->em->getRepository('App:Story')->findOneBy(array('id' => $id));
            $like = $this->em->getRepository('App:LikeStory')->findOneBy(array('story' => $story, 'user' => $user));
            if (!empty($like)) {
                $this->em->remove($like);
                $this->em->flush();
            }

            $story = $this->em->getRepository('App:Story')->findOneBy(array('id' => $id));
            $cache = new FilesystemAdapter();
            $cache->deleteItem('stories.get.' . $story->getUser()->getId());

            return new Response($this->serializer->serialize($story, "json", ['groups' => 'story']));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al retirarle tu kokoro a una historia - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Route("/v1/comment-story", name="comment_story", methods={"PUT"})
     */
    public function putCommentAction(Request $request)
    {
        $user = $this->getUser();

        $this->accessChecker->checkAccess($user);
        try {
            $story = $this->em->getRepository('App:Story')->findOneBy(array('id' => $this->request->get($request, 'story')));

            $comment = new Comment();

            if (empty($this->em->getRepository('App:BlockUser')->isBlocked($user, $story->getUser()))) {
                $comment->setStory($story);
                $comment->setUser($user);

                $text = $this->request->get($request, "text", false);

                $comment->setText($text);
                $comment->setTimeCreation();

                $mentions = array_unique($this->request->get($request, "mentions", false));
                if ($mentions) {
                    $comment->setMentions($mentions);
                }

                $this->em->persist($comment);
                $this->em->flush();

                $url = "/tabs/community/story/" . $story->getId();
                if (count((array) $mentions) > 0) {
                    foreach ($mentions as $mention) {
                        $toUser = $this->em->getRepository('App:User')->findOneBy(array('username' => $mention));
                        $title = $user->getUsername() . ' te ha mencionado en una historia.';
                        $this->notification->push($user, $toUser, $title, $text, $url, 'story');
                    }
                } else {
                    $title = $user->getName() . ' ha comentado tu historia.';
                    $this->notification->push($user, $story->getUser(), $title, $text, $url, "story");
                }

                $story = $this->em->getRepository('App:Story')->findOneBy(array('id' => $this->request->get($request, 'story')));
                $cache = new FilesystemAdapter();
                $cache->deleteItem('stories.get.' . $story->getUser()->getId());

                return new Response($this->serializer->serialize($story, "json", ['groups' => 'story']));
            } else {
                throw new HttpException(400, "No se puede comentar a este usuario, estás bloqueado - Error");
            }
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al comentar la historia - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Route("/v1/like-comment", name="like_comment", methods={"PUT"})
     */
    public function putLikeCommentAction(Request $request)
    {
        $user = $this->getUser();
        $this->accessChecker->checkAccess($user);
        try {
            /**
             * @var Comment
             */
            $comment = $this->em->getRepository('App:Comment')->findOneBy(array('id' => $this->request->get($request, 'comment')));
            $likes = $comment->getLikes();
            $liked = false;
            foreach ($likes as $like) {
                if ($like->getId() === $user->getId()) {
                    $liked = true;
                }
            }

            if (!$liked) {
                $comment->addLike($user);
                $this->em->persist($comment);
                $this->em->flush();

                if ($user->getId() !== $comment->getUser()->getId()) {
                    $title = $user->getName();
                    $text = "A " . $user->getName() . " le ha gustado tu comentario ❤️.";
                    $url = "/tabs/community/story/" . $comment->getStory()->getId();

                    $this->notification->push($user, $comment->getUser(), $title, $text, $url, "story");
                }
            }

            $story = $comment->getStory();
            $cache = new FilesystemAdapter();
            $cache->deleteItem('stories.get.' . $story->getUser()->getId());

            return new Response($this->serializer->serialize($story, "json", ['groups' => 'story']));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al entregar kokoro a una historia - Error: {$ex->getMessage()}");
        }
    }


    /**
     * @Route("/v1/like-comment/{id}", name="unlike_comment", methods={"DELETE"})
     */
    public function removeLikeCommentAction(int $id)
    {
        $user = $this->getUser();
        $this->accessChecker->checkAccess($user);

        try {
            /**
             * @var Comment
             */
            $comment = $this->em->getRepository('App:Comment')->findOneBy(array('id' => $id));
            $likes = $comment->getLikes();
            $liked = false;
            foreach ($likes as $like) {
                if ($like->getId() === $user->getId()) {
                    $liked = true;
                }
            }

            if ($liked) {
                $comment->removeLike($user);
                $this->em->persist($comment);
                $this->em->flush();
            }

            $story = $comment->getStory();
            $cache = new FilesystemAdapter();
            $cache->deleteItem('stories.get.' . $story->getUser()->getId());

            return new Response($this->serializer->serialize($story, "json", ['groups' => 'story']));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al retirarle tu kokoro a una historia - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Route("/v1/delete-comment/{id}", name="delete_comment", methods={"DELETE"})
     */
    public function removeCommentAction(int $id)
    {
        try {
            /**
             * @var Comment
             */
            $comment = $this->em->getRepository('App:Comment')->findOneBy(array('id' => $id));
            if ($comment->getUser()->getId() === $this->getUser()->getId()) {
                $story = $comment->getStory();
                $this->em->remove($comment);
                $this->em->flush();

                $cache = new FilesystemAdapter();
                $cache->deleteItem('stories.get.' . $story->getUser()->getId());

                return new Response($this->serializer->serialize($story, "json", ['groups' => 'story']));
            } else {
                throw new HttpException(404, "No se puede eliminar el comentario de otro usuario.");
            }
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al eliminar el comentario - Error: {$ex->getMessage()}");
        }
    }
}
