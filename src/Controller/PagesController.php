<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Tag;
use App\Repository\TagRepository;
use App\Service\AccessCheckerService;
use App\Service\RequestService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Class PagesController
 *
 * @Route(path="/api")
 */
class PagesController extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        RequestService $request,
        AccessCheckerService $accessChecker,
        EntityManagerInterface $entityManager
    ) {
        $this->request = $request;
        $this->serializer = $serializer;
        $this->accessChecker = $accessChecker;
        $this->em = $entityManager;
    }


    /**
     * @Route("/v1/page/{slug}", name="page", methods={"GET"})
     */
    public function getPage(string $slug)
    {
        $user = $this->getUser();
        $this->accessChecker->checkAccess($user);

        try {
            $page = $this->em->getRepository('App:Page')->findOneBy(array('slug' => $slug));
            return new Response($this->serializer->serialize($page, "json", ['groups' => 'default']));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al obtener la página - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Route("/v1/page", name="set_page", methods={"POST"})
     */
    public function setPage(Request $request)
    {
        $user = $this->getUser();
        $this->accessChecker->checkAccess($user);

        try {
            $page = $this->em->getRepository('App:Page')->findOneBy(array('slug' => $this->request->get($request, 'slug')));
            return new Response($this->serializer->serialize($page, "json", ['groups' => 'default']));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al crear la página - Error: {$ex->getMessage()}");
        }
    }
}
