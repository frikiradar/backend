<?php
// src/Controller/ChatController.php
namespace App\Controller;

use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Swagger\Annotations as SWG;
use JMS\Serializer\SerializerInterface;

/**
 * Class ConfigController
 *
 * @Route("/api")
 */
class ConfigController extends AbstractFOSRestController
{
    public function __construct(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }

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
        $em = $this->getDoctrine()->getManager();

        try {
            $config['maintenance'] = (bool) $em->getRepository('App:Config')->findOneBy(['name' => 'maintenance'])->getValue();
            $config['min_version'] = $em->getRepository('App:Config')->findOneBy(['name' => 'min_version'])->getValue();
            $config['chat'] = (bool) $em->getRepository('App:Config')->findOneBy(['name' => 'chat'])->getValue();
            $config['push_url'] = $em->getRepository('App:Config')->findOneBy(['name' => 'push_url'])->getValue();

            return new Response($this->serializer->serialize($config, "json"));
        } catch (Exception $ex) {
            throw new HttpException(500, "No se puede obtener la configuración - Error: {$ex->getMessage()}");
        }
    }
}
