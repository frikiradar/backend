<?php
// src/Controller/StoriesController.php
namespace App\Controller;

use App\Entity\Story;
use App\Entity\ViewStory;
use App\Service\AccessCheckerService;
use App\Service\FileUploaderService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Service\RequestService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
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
        AccessCheckerService $accessChecker
    ) {
        $this->em = $entityManager;
        $this->serializer = $serializer;
        $this->request = $request;
        $this->accessChecker = $accessChecker;
    }

    /**
     * @Route("/v1/stories", name="get_stories", methods={"GET"})
     */
    public function getStoriesAction()
    {
        $user = $this->getUser();
        $this->accessChecker->checkAccess($user);
        $rooms = $this->em->getRepository('App:Story')->getStories($user);

        return new Response($this->serializer->serialize($rooms, "json", ['groups' => ['story']]));
    }


    /**
     * @Route("/v1/story-upload", name="story_upload", methods={"POST"})
     */
    public function upload(Request $request)
    {
        $fromUser = $this->getUser();
        $this->accessChecker->checkAccess($fromUser);
        try {
            $story = new Story();
            $imageFile = $request->files->get('image');
            $text = $request->request->get("text");

            $story->setText($text);
            $story->setUser($fromUser);

            $filename = microtime(true);
            $absolutePath = '/var/www/vhosts/frikiradar.com/app.frikiradar.com/images/stories/';
            $server = "https://app.frikiradar.com";
            $uploader = new FileUploaderService($absolutePath . $fromUser->getId() . "/", $filename);
            $image = $uploader->upload($imageFile, true, 50);
            $src = str_replace("/var/www/vhosts/frikiradar.com/app.frikiradar.com", $server, $image);
            $story->setImage($src);
            $story->setTimeCreation();
            $this->em->persist($story);
            $this->em->flush();

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
            $story = $this->em->getRepository('App:Story')->findOneBy(array('id' => $this->request->get($request, 'story')));
            if ($story->getUser()->getId() !== $user->getId()) {
                $view = new ViewStory();
                $view->setDate(new \DateTime);
                $view->setStory($story);
                $view->setUser($user);
                $this->em->persist($view);
                $this->em->flush();

                return new Response($this->serializer->serialize("Historia vista correctamente", "json"));
            } else {
                throw new HttpException(400, "No puedes marcar como vista tu propia historia.");
            }
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al ver la historia - Error: {$ex->getMessage()}");
        }
    }
}
