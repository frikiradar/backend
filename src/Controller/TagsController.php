<?php

namespace App\Controller;

use App\Repository\TagRepository;
use App\Service\RequestService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Class TagsController
 *
 * @Route(path="/api")
 */
class TagsController extends AbstractController
{
    public function __construct(TagRepository $tagRepository, SerializerInterface $serializer, RequestService $request)
    {
        $this->tagRepository = $tagRepository;
        $this->request = $request;
        $this->serializer = $serializer;
    }


    /**
     * @Route("/v1/tags", name="tags", methods={"POST"})
     */
    public function searchTags(Request $request)
    {
        $tags = $this->tagRepository->searchTags($this->request->get($request, 'tag'), $this->request->get($request, 'category'));

        return new Response($this->serializer->serialize($tags, "json"));
    }
}
