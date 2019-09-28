<?php

/**
 * UsersController.php
 *
 * Users Controller
 *
 * @category   Controller
 * @package    FrikiRadar
 * @author     Alberto Merino
 * @copyright  2019 frikiradar.com
 */

namespace App\Controller;

use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\FOSRestController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Swagger\Annotations as SWG;

/**
 * Class TagsController
 *
 * @Route("/api")
 */
class TagsController extends FOSRestController
{
    /**
     * @Rest\Post("/v1/tags", name="tags")
     * 
     * @SWG\Response(
     *     response=201,
     *     description="Tags obtenidas correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al obtener las tags"
     * )
     * 
     * @SWG\Parameter(
     *     name="tag",
     *     in="body",
     *     type="string",
     *     description="Search tags query",
     *     schema={}
     * )
     * 
     * @SWG\Parameter(
     *     name="category",
     *     in="body",
     *     type="string",
     *     description="Tag category",
     *     schema={}
     * )
     *
     * @Rest\View(serializerGroups={"tags"})
     * 
     */
    public function searchTags(Request $request)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        $tags = $em->getRepository('App:Tag')->searchTags($request->request->get('tag'), $request->request->get('category'));

        return new Response($serializer->serialize($tags, "json"));
    }
}
