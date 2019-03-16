<?php
 // src/Controller/DiscoverController.php
namespace App\Controller;

use Fig\Link\Link;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DiscoverController extends AbstractController
{
    public function __invoke(Request $request): Response
    {
        $hubUrl = $this->getParameter('mercure.default_hub');
        $this->addLink($request, new Link('mercure', $hubUrl));

        $username = $this->getUser()->getUsername(); // Retrieve the username of the current user
        $token = (new Builder())
            // set other appropriate JWT claims, such as an expiration date
            ->set('mercure', ['subscribe' => "http://example.com/user/$username"]) // could also include the security roles, or anything else
            ->sign(new Sha256(), $this->getParameter('mercure_secret_key')) // don't forget to set this parameter! Test value: aVerySecretKey
            ->getToken();

        $response = $this->json(['@id' => '/demo/books/1', 'availability' => 'https://schema.org/InStock']);
        $response->headers->set(
            'set-cookie',
            sprintf('mercureAuthorization=%s; path=/hub; secure; httponly; SameSite=strict', $token)
        );

        return $response;
    }
}

