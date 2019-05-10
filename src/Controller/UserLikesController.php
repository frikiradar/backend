<?php
// src/Controller/ChatController.php
namespace App\Controller;

use App\Entity\LikeUser;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\FOSRestController;
use JMS\Serializer\SerializationContext;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Swagger\Annotations as SWG;
use App\Service\NotificationService;

/**
 * Class UserLikesController
 *
 * @Route("/api")
 */
class UserLikesController extends FOSRestController
{
    /**
     * @Rest\Put("/v1/like", name="like")
     *
     * @SWG\Response(
     *     response=201,
     *     description="Like guardado correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al guardar el like"
     * )
     * 
     * @SWG\Parameter(
     *     name="user",
     *     in="body",
     *     type="string",
     *     description="To user like",
     *     schema={}
     * )
     *
     */
    public function putLikeAction(Request $request)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        try {
            $toUser = $em->getRepository('App:User')->findOneBy(array('id' => $request->request->get('user')));

            $like = $em->getRepository('App:LikeUser')->findOneBy(array('toUser' => $toUser, 'fromUser' => $this->getUser()));

            if (empty($like)) {
                $newLike = new LikeUser();
                $newLike->setDate(new \DateTime);
                $newLike->setFromUser($this->getUser());
                $newLike->setToUser($toUser);
                $em->persist($newLike);
                $em->flush();

                $title = $newLike->getFromUser()->getUsername();
                $text = "Te ha entregado su kokoro, ya puedes comenzar a chatear.";
                $url = "/profile/" . $newLike->getFromUser()->getId();

                $notification = new NotificationService();
                $notification->push($newLike->getFromuser(), $newLike->getTouser(), $title, $text, $url, "like");
            }

            $user = $em->getRepository('App:User')->findeOneUser($this->getUser(), $toUser);

            return new Response($serializer->serialize($user, "json", SerializationContext::create()->setGroups(array('default'))));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al entregar tu kokoro - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Rest\Delete("/v1/like/{id}", name="unlike")
     *
     * @SWG\Response(
     *     response=201,
     *     description="Like borrado correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al borrar el like"
     * )
     *
     */
    public function removeLikeAction(int $id)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        try {
            $toUser = $em->getRepository('App:User')->findOneBy(array('id' => $id));
            $like = $em->getRepository('App:LikeUser')->findOneBy(array('to_user' => $toUser, 'from_user' => $this->getUser()));
            $em->remove($like);
            $em->flush();

            $user = $em->getRepository('App:User')->findeOneUser($this->getUser(), $toUser);

            return new Response($serializer->serialize($user, "json", SerializationContext::create()->setGroups(array('default'))));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al retirarle tu kokoro - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Rest\Get("/v1/likes")
     * 
     * @SWG\Response(
     *     response=201,
     *     description="Likes obtenidos correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al obtener los likes"
     * )
     * 
     * @SWG\Tag(name="Get Likes")
     */
    public function getLikesAction()
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        try {
            $likes = $em->getRepository('App:LikeUser')->getLikeUsers($this->getUser());

            foreach ($likes as $key => $like) {
                $userId = $like["fromuser"];
                $user = $em->getRepository('App:User')->findOneBy(array('id' => $userId));
                $likes[$key]['user'] = [
                    'id' => $userId,
                    'username' => $user->getUsername(),
                    'description' => $user->getDescription(),
                    'avatar' =>  $user->getAvatar() ?: null
                ];
            }
            return new Response($serializer->serialize($likes, "json"));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al obtener los likes - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Rest\Get("/v1/read-like/{id}")
     * 
     * @SWG\Response(
     *     response=201,
     *     description="Like leido correctamente"
     * )
     *
     * @SWG\Response(
     *     response=500,
     *     description="Error al marcar como leido el like"
     * )
     *
     */
    public function markAsReadAction(int $id)
    {
        $serializer = $this->get('jms_serializer');
        $em = $this->getDoctrine()->getManager();

        try {
            $like = $em->getRepository('App:LikeUser')->findOneBy(array('from_user' => $id, 'to_user' => $this->getUser()->getId()));
            $like->setTimeRead(new \DateTime);
            $em->merge($like);
            $em->flush();

            return new Response($serializer->serialize($like, "json", SerializationContext::create()->setGroups(array('like'))->enableMaxDepthChecks()));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al marcar como leido - Error: {$ex->getMessage()}");
        }
    }
}
