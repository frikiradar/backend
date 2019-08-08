<?php
// src/Controller/ChatController.php
namespace App\Controller;

use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\FOSRestController;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Swagger\Annotations as SWG;

/**
 * Class ConfigController
 *
 * @Route("/api")
 */
class ConfigController extends FOSRestController
{
    /**
     * @Rest\Get("/config", name="config")
     *
     * @SWG\Response(
     *     response=201,
     *     description="Configuración obtenida correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al obtener la configuración"
     * )
     * 
     */
    public function getConfig()
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        try {
            $config['maintenance'] = (bool) $em->getRepository('App:Config')->findOneBy(['name' => 'maintenance'])->getValue();
            $config['min_version'] = $em->getRepository('App:Config')->findOneBy(['name' => 'min_version'])->getValue();
            $config['chat'] = (bool) $em->getRepository('App:Config')->findOneBy(['name' => 'chat'])->getValue();

            return new Response($serializer->serialize($config, "json"));
        } catch (Exception $ex) {
            throw new HttpException(500, "No se puede obtener la configuración - Error: {$ex->getMessage()}");
        }
    }
}
