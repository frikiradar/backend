<?php
// src/Controller/AdsController.php
namespace App\Controller;

use App\Entity\ClickAd;
use App\Repository\AdRepository;
use App\Repository\ClickAdRepository;
use App\Repository\ViewAdRepository;
use App\Service\FileUploaderService;
use App\Service\RequestService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\SerializerInterface;

#[Route(path: '/api')]
class AdsController extends AbstractController
{
    private $serializer;
    private $request;
    private $adRepository;
    private $clickAdRepository;
    private $viewAdRepository;

    public function __construct(
        SerializerInterface $serializer,
        RequestService $request,
        AdRepository $adRepository,
        ClickAdRepository $clickAdRepository,
        ViewAdRepository $viewAdRepository
    ) {
        $this->serializer = $serializer;
        $this->request = $request;
        $this->adRepository = $adRepository;
        $this->clickAdRepository = $clickAdRepository;
        $this->viewAdRepository = $viewAdRepository;
    }

    #[Route('/v1/ads', name: 'get_ads', methods: ['GET'])]
    public function getAdsAction()
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $country = $user->getIpCountry();
        $ads = $this->adRepository->getActiveAds($country);

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

        $this->adRepository->save($ad);

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

        $ad = $this->adRepository->findOneBy(['id' => $id, 'user' => $user]);

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

        $this->adRepository->save($ad);

        return new JsonResponse($this->serializer->serialize($ad, "json"), Response::HTTP_OK, [], true);
    }

    #[Route('/v1/ads/{id}', name: 'delete_ad', methods: ['DELETE'])]
    public function deleteAdAction(int $id)
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $ad = $this->adRepository->findOneBy(['id' => $id, 'user' => $user]);

        if (!$ad) {
            return new JsonResponse(['error' => 'Ad not found'], Response::HTTP_NOT_FOUND);
        }

        $this->adRepository->remove($ad);

        return new JsonResponse(['message' => 'Ad deleted'], Response::HTTP_OK);
    }

    #[Route('/v1/ads/{id}', name: 'get_ad', methods: ['GET'])]
    public function getAdAction(int $id)
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $ad = $this->adRepository->findOneBy(['id' => $id, 'user' => $user]);

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

        $ad = $this->adRepository->findOneBy(['id' => $id]);

        if (!$ad) {
            return new JsonResponse(['error' => 'Ad not found'], Response::HTTP_NOT_FOUND);
        }

        $clickAd = new ClickAd();
        $clickAd->setAd($ad);
        $clickAd->setUser($user);
        $clickAd->setDate();

        $this->clickAdRepository->save($clickAd);

        return new JsonResponse(['message' => 'Ad clicked'], Response::HTTP_OK);
    }

    #[Route('/v1/ads/{id}/view', name: 'view_ad', methods: ['POST'])]
    public function viewAdAction(int $id)
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $ad = $this->adRepository->findOneBy(['id' => $id]);

        if (!$ad) {
            return new JsonResponse(['error' => 'Ad not found'], Response::HTTP_NOT_FOUND);
        }

        $viewAd = new \App\Entity\ViewAd();
        $viewAd->setAd($ad);
        $viewAd->setUser($user);
        $viewAd->setDate();

        $this->viewAdRepository->save($viewAd);

        return new JsonResponse(['message' => 'Ad viewed'], Response::HTTP_OK);
    }

    #[Route('/v1/ads/{id}/stats', name: 'get_ad_stats', methods: ['GET'])]
    public function getAdStatsAction(int $id)
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        /** @var \App\Entity\Ad $ad */
        try {
            $ad = $this->adRepository->findOneBy(['id' => $id]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Ad not found'], Response::HTTP_NOT_FOUND);
        }

        if ($ad->getUser() !== $user || !$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['error' => 'Ad not found'], Response::HTTP_NOT_FOUND);
        }

        $clicks = $this->clickAdRepository->findBy(['ad' => $ad]);
        $views = $this->viewAdRepository->findBy(['ad' => $ad]);

        $startDate = $ad->getStartDate() ?? $ad->getCreationDate();
        $endDate = $ad->getEndDate() ?? new \DateTime();

        $period = new \DatePeriod(
            $startDate,
            new \DateInterval('P1D'),
            (new \DateTime())->setTimestamp($endDate->getTimestamp())->modify('+1 day')
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

        /** @var \App\Entity\Ad $ads */
        $ads = $this->adRepository->findBy(['user' => $id]);

        if ($ads->getUser() !== $user || !$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['error' => 'Ad not found'], Response::HTTP_NOT_FOUND);
        }

        if (!$ads) {
            return new JsonResponse(['error' => 'Ads not found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($this->serializer->serialize($ads, "json"), Response::HTTP_OK, [], true);
    }
}
