<?php
 // src/Controller/ChatController.php
namespace App\Controller;

use App\Entity\User;
use App\Entity\Chat;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\FOSRestController;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Nelmio\ApiDocBundle\Annotation\Model;
use Swagger\Annotations as SWG;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\Mercure\Publisher;
use Symfony\Component\Mercure\Update;

/**
 * Class ChatController
 *
 * @Route("/api")
 */
class ChatController extends FOSRestController
{
    /**
     * @Rest\Put("/v1/chat")
     * 
     * @SWG\Response(
     *     response=201,
     *     description="Usuario actualizado correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al actualizar el usuario"
     * )
     * 
     * @SWG\Parameter(
     *     name="touser",
     *     in="body",
     *     type="string",
     *     description="To user id",
     *     schema={}
     * )
     *
     * 
     * @SWG\Parameter(
     *     name="text",
     *     in="body",
     *     type="string",
     *     description="Text",
     *     schema={}
     * )
     */
    public function putAction(Request $request, Publisher $publisher)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        $newChat = new Chat();
        $toUser = $em->getRepository('App:User')->findOneBy(array('id' => $request->request->get("touser")));
        $newChat->setTouser($toUser);
        $newChat->setFromuser($this->getUser());
        $newChat->setText($request->request->get("text"));
        $newChat->setTimeCreation(new \DateTime);
        $em->merge($newChat);
        $em->flush();

        $chat = [
            "fromuser" => $newChat->getFromuser()->getId(),
            "touser" => $newChat->getTouser()->getId(),
            "text" => $newChat->getText(),
            "time_creation" => $newChat->getTimeCreation()
        ];

        $topic = $newChat->getFromuser()->getId() . "_" . $newChat->getTouser()->getId();

        $update = new Update($topic, $serializer->serialize($chat, "json"));
        $publisher($update);

        return new Response($serializer->serialize($chat, "json"));
    }

    public function getAction()
    { }
}
