<?php
// src/Controller/AdsController.php
namespace App\Controller;

use App\Service\FileUploaderService;
use App\Service\RequestService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\SerializerInterface;

#[Route(path: '/api')]
class AdsController extends AbstractController
{
    private $em;
    private $serializer;
    private $request;

    public function __construct(
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer,
        RequestService $request
    ) {
        $this->em = $entityManager;
        $this->serializer = $serializer;
        $this->request = $request;
    }

    #[Route('/v1/ads', name: 'get_ads', methods: ['GET'])]
    public function getAdsAction()
    {
        $ads = $this->em->getRepository(\App\Entity\Ad::class)->getActiveAds();

        return new JsonResponse($this->serializer->serialize($ads, "json", ['groups' => ['ads']]), Response::HTTP_OK, [], true);
    }

    #[Route('/v1/ads', name: 'create_ad', methods: ['POST'])]
    public function setAdAction(Request $request)
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $url = $this->request->get($request, 'url');
        $title = $this->request->get($request, 'title', false);
        $description = $this->request->get($request, 'description', false);
        $startDate = $this->request->get($request, 'start_date', false);
        $endDate = $this->request->get($request, 'end_date', false);
        $imageFile = $request->files->get('image');

        $ad = new \App\Entity\Ad();
        $ad->setUrl($url);
        $ad->setTitle($title);
        $ad->setDescription($description);
        $ad->setCreationDate();
        $ad->setStartDate($startDate);
        $ad->setEndDate($endDate);
        $ad->setUser($user);

        if (!empty($imageFile)) {
            $absolutePath = '/var/www/vhosts/frikiradar.com/app.frikiradar.com/images/ads/' . $user->getId() . '/';
            $server = "https://app.frikiradar.com";
            $filename =  microtime();
            $uploader = new FileUploaderService($absolutePath, $filename);
            $image = $uploader->uploadImage($imageFile, false, 90);
            $src = str_replace("/var/www/vhosts/frikiradar.com/app.frikiradar.com", $server, $image);
            $ad->setImageUrl($src);
        }

        $this->em->persist($ad);
        $this->em->flush();

        return new JsonResponse($this->serializer->serialize($ad, "json"), Response::HTTP_OK, [], true);
    }

    #[Route('/v1/ads/{id}', name: 'update_ad', methods: ['PUT'])]
    public function updateAdAction(Request $request, int $id)
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $url = $this->request->get($request, 'url');
        $title = $this->request->get($request, 'title', false);
        $description = $this->request->get($request, 'description', false);
        $startDate = $this->request->get($request, 'start_date', false);
        $endDate = $this->request->get($request, 'end_date', false);
        $imageFile = $request->files->get('image');

        $ad = $this->em->getRepository(\App\Entity\Ad::class)->findOneBy(['id' => $id, 'user' => $user]);

        if (!$ad) {
            return new JsonResponse(['error' => 'Ad not found'], Response::HTTP_NOT_FOUND);
        }

        $ad->setUrl($url);
        $ad->setTitle($title);
        $ad->setDescription($description);
        $ad->setStartDate($startDate);
        $ad->setEndDate($endDate);

        if (!empty($imageFile)) {
            $absolutePath = '/var/www/vhosts/frikiradar.com/app.frikiradar.com/images/ads/' . $user->getId() . '/';
            $server = "https://app.frikiradar.com";
            $filename =  microtime();
            $uploader = new FileUploaderService($absolutePath, $filename);

            // Borrar la imagen antigua
            $oldImage = $ad->getImageUrl();
            if ($oldImage) {
                $oldImagePath = str_replace($server, "/var/www/vhosts/frikiradar.com/app.frikiradar.com", $oldImage);
                if (file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                }
            }

            // Subir la nueva imagen
            $image = $uploader->uploadImage($imageFile, false, 90);
            $src = str_replace("/var/www/vhosts/frikiradar.com/app.frikiradar.com", $server, $image);
            $ad->setImageUrl($src);
        }

        $this->em->persist($ad);
        $this->em->flush();

        return new JsonResponse($this->serializer->serialize($ad, "json"), Response::HTTP_OK, [], true);
    }

    #[Route('/v1/ads/{id}', name: 'delete_ad', methods: ['DELETE'])]
    public function deleteAdAction(int $id)
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $ad = $this->em->getRepository(\App\Entity\Ad::class)->findOneBy(['id' => $id, 'user' => $user]);

        if (!$ad) {
            return new JsonResponse(['error' => 'Ad not found'], Response::HTTP_NOT_FOUND);
        }

        $this->em->remove($ad);
        $this->em->flush();

        return new JsonResponse(['message' => 'Ad deleted'], Response::HTTP_OK);
    }

    #[Route('/v1/ads/{id}', name: 'get_ad', methods: ['GET'])]
    public function getAdAction(int $id)
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $ad = $this->em->getRepository(\App\Entity\Ad::class)->findOneBy(['id' => $id, 'user' => $user]);

        if (!$ad) {
            return new JsonResponse(['error' => 'Ad not found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($this->serializer->serialize($ad, "json"), Response::HTTP_OK, [], true);
    }

    #[Route('/v1/ads/{id}/click', name: 'click_ad', methods: ['POST'])]
    public function clickAdAction(int $id)
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $ad = $this->em->getRepository(\App\Entity\Ad::class)->findOneBy(['id' => $id]);

        if (!$ad) {
            return new JsonResponse(['error' => 'Ad not found'], Response::HTTP_NOT_FOUND);
        }

        $clickAd = new \App\Entity\ClickAd();
        $clickAd->setAd($ad);
        $clickAd->setUser($user);
        $clickAd->setDate();

        $this->em->persist($clickAd);
        $this->em->flush();

        return new JsonResponse(['message' => 'Ad clicked'], Response::HTTP_OK);
    }

    #[Route('/v1/ads/{id}/view', name: 'view_ad', methods: ['POST'])]
    public function viewAdAction(int $id)
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $ad = $this->em->getRepository(\App\Entity\Ad::class)->findOneBy(['id' => $id]);

        if (!$ad) {
            return new JsonResponse(['error' => 'Ad not found'], Response::HTTP_NOT_FOUND);
        }

        $viewAd = new \App\Entity\ViewAd();
        $viewAd->setAd($ad);
        $viewAd->setUser($user);
        $viewAd->setDate();

        $this->em->persist($viewAd);
        $this->em->flush();

        return new JsonResponse(['message' => 'Ad viewed'], Response::HTTP_OK);
    }

    #[Route('/v1/ads/{id}/stats', name: 'get_ad_stats', methods: ['GET'])]
    public function getAdStatsAction(int $id)
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        /** @var \App\Entity\Ad $ad */
        try {
            $ad = $this->em->getRepository(\App\Entity\Ad::class)->findOneBy(['id' => $id]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Ad not found'], Response::HTTP_NOT_FOUND);
        }

        if ($ad->getUser() !== $user || !$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['error' => 'Ad not found'], Response::HTTP_NOT_FOUND);
        }

        $clicks = $this->em->getRepository(\App\Entity\ClickAd::class)->findBy(['ad' => $ad]);
        $views = $this->em->getRepository(\App\Entity\ViewAd::class)->findBy(['ad' => $ad]);

        $startDate = $ad->getStartDate() ?? $ad->getCreationDate();
        $endDate = $ad->getEndDate() ?? new \DateTime();
        $period = new \DatePeriod(
            $startDate,
            new \DateInterval('P1D'),
            $endDate->modify('+1 day')
        );

        $adData = [];
        foreach ($period as $date) {
            $dateString = $date->format('Y-m-d');

            $dayClicks = array_filter($clicks, function ($click) use ($dateString) {
                return $click->getDate()->format('Y-m-d') === $dateString;
            });

            $dayViews = array_filter($views, function ($view) use ($dateString) {
                return $view->getDate()->format('Y-m-d') === $dateString;
            });

            $adData[$dateString] = [
                'clicks' => count($dayClicks),
                'views' => count($dayViews),
            ];
        }
    }

    //get ads by user
    #[Route('/v1/ads/user/{id}', name: 'get_ads_by_user', methods: ['GET'])]
    public function getAdsByUserAction(int $id)
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $ads = $this->em->getRepository(\App\Entity\Ad::class)->findBy(['user' => $id]);

        if ($ads->getUser() !== $user || !$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['error' => 'Ad not found'], Response::HTTP_NOT_FOUND);
        }

        if (!$ads) {
            return new JsonResponse(['error' => 'Ads not found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($this->serializer->serialize($ads, "json"), Response::HTTP_OK, [], true);
    }
}
