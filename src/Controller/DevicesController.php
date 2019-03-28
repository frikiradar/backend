<?php
/**
 * DevicesController.php
 *
 * Devices Controller
 *
 * @category   Controller
 * @package    FrikiRadar
 * @author     Alberto Merino
 * @copyright  2019 frikiradar.com
 */

namespace App\Controller;

use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\FOSRestController;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Swagger\Annotations as SWG;


/**
 * Class DevicesController
 *
 * @Route("/api")
 */
class DevicesController extends FOSRestController
{
    /**
     * @Rest\Get("/v1/devices")
     *
     * @SWG\Response(
     *     response=201,
     *     description="Dispositivos obtenidos correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al obtener los dispositivos"
     * )
     * 
     */
    public function getDevices()
    {
        $serializer = $this->get('jms_serializer');

        try {
            $response = $this->getUser()->getDevices();
        } catch (Exception $ex) {
            $response = [
                'code' => 500,
                'error' => true,
                'data' => "Error al obtener los dispositivos - Error: {$ex->getMessage()}",
            ];
        }

        return new Response($serializer->serialize($response, "json"));
    }

    /**
     * @Rest\Put("/v1/device", name="device")
     *
     * @SWG\Response(
     *     response=201,
     *     description="Dispositivo añadido correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al añadir el dispositivo"
     * )
     * 
     * @SWG\Parameter(
     *     name="token",
     *     in="body",
     *     type="string",
     *     description="Token",
     *     schema={}
     * )
     * 
     * @SWG\Parameter(
     *     name="id",
     *     in="body",
     *     type="string",
     *     description="Device Id",
     *     schema={}
     * )
     * 
     * @SWG\Parameter(
     *     name="name",
     *     in="body",
     *     type="string",
     *     description="Device Name",
     *     schema={}
     * )
     *
     * @Rest\View(serializerGroups={"device"})
     */
    public function setDeviceAction(Request $request)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        try {
            $response = $em->getRepository('App:Device')->set(
                $this->getUser(),
                $request->request->get("token"),
                $request->request->get("id"),
                $request->request->get("name")
            );
        } catch (Exception $ex) {
            $response = [
                'code' => 500,
                'error' => true,
                'data' => "Error al registrar el dispositivo - Error: {$ex->getMessage()}",
            ];
        }

        return new Response($serializer->serialize($response, "json"));
    }
}