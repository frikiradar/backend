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
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Class TagsController
 *
 * @Route(path="/api")
 */
class TagsController extends AbstractController
{
    private $tagRepository;
    private $request;
    private $serializer;
    private $accessChecker;
    private $em;

    public function __construct(
        TagRepository $tagRepository,
        SerializerInterface $serializer,
        RequestService $request,
        AccessCheckerService $accessChecker,
        EntityManagerInterface $entityManager
    ) {
        $this->tagRepository = $tagRepository;
        $this->request = $request;
        $this->serializer = $serializer;
        $this->accessChecker = $accessChecker;
        $this->em = $entityManager;
    }


    /**
     * @Route("/v1/tags", name="tags", methods={"POST"})
     */
    public function searchTags(Request $request)
    {
        $tags = $this->tagRepository->searchTags($this->request->get($request, 'tag'), $this->request->get($request, 'category'));

        return new JsonResponse($this->serializer->serialize($tags, "json"), Response::HTTP_OK, [], true);
    }

    /**
     * @Route("/v1/add-tag", name="add_tag", methods={"PUT"})
     */
    public function addTag(Request $request)
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $this->accessChecker->checkAccess($user);
        $cache = new FilesystemAdapter();
        $cache->deleteItem('users.get.' . $user->getId() . '.' . $user->getId());
        $cache->deleteItem('users.get.' . $user->getId());
        try {
            $name = $this->request->get($request, 'name');
            $slug = $this->request->get($request, 'slug', false);
            $categoryName = $this->request->get($request, 'category');
            $category = $this->em->getRepository(\App\Entity\Category::class)->findOneBy(array('name' => $categoryName));
            $oldTag = $this->em->getRepository(\App\Entity\Tag::class)->findOneBy(array('name' => $name, 'user' => $user->getId(), 'category' => !empty($category) ? $category->getId() : null));

            if (empty($oldTag)) {
                $tag = new Tag();
                $tag->setUser($user);
                $tag->setName($name);
                $tag->setCategory($category);

                if (!empty($category)) {
                    $tag->setCategory($category);

                    if (empty($slug) && in_array($categoryName, ['games', 'films'])) {
                        // Creamos página
                        $page = $this->em->getRepository(\App\Entity\Page::class)->setPage($tag);
                        if (!empty($page)) {
                            $tag->setSlug($page->getSlug());
                        } else {
                            $tag->setSlug($slug);
                        }
                    } else {
                        $tag->setSlug($slug);
                    }
                } else {
                    $newCategory = new Category();
                    $newCategory->setName($categoryName);
                    $tag->setCategory($newCategory);
                    $this->em->persist($newCategory);
                }

                $this->em->persist($tag);
                $this->em->flush();
                return new JsonResponse($this->serializer->serialize($tag, "json", ['groups' => 'default']), Response::HTTP_OK, [], true);
            } else {
                if (!$oldTag->getSlug()) {
                    $oldTag->setSlug($this->request->get($request, 'slug', false));
                    $this->em->persist($oldTag);
                    $this->em->flush();
                }
                throw new HttpException(400, "Error al añadir tag. Ya estaba añadida.");
            }
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al añadir tag - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Route("/v1/tag/{id}", name="remove_tag", methods={"DELETE"})
     */
    public function removeTag($id)
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $this->accessChecker->checkAccess($user);
        $cache = new FilesystemAdapter();
        $cache->deleteItem('users.get.' . $user->getId() . '.' . $user->getId());
        $cache->deleteItem('users.get.' . $user->getId());
        try {
            if (!is_numeric($id)) { // TODO: Eliminar medida provisional
                $username = $id;
                $tag = $this->em->getRepository(\App\Entity\Tag::class)->findOneBy(array('name' => $username, 'user' => $user));
            } else {
                $tag = $this->em->getRepository(\App\Entity\Tag::class)->findOneBy(array('id' => $id));
            }

            if ($tag->getUser()->getId() == $user->getId()) {
                $this->em->remove($tag);
                $this->em->flush();

                $data = [
                    'code' => 200,
                    'message' => "Tag eliminada correctamente",
                ];
                return new JsonResponse($data, 200);
            } else {
                throw new HttpException(401, "El usuario no eres tu, ¿intentando hacer trampa?");
            }
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al añadir tag - Error: {$ex->getMessage()}");
        }
    }
}
