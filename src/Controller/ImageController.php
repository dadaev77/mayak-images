<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\ImageScraper;

class ImageController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(): Response
    {
        return $this->render('image/index.html.twig');
    }

    #[Route('/scrape', name: 'scrape', methods: ['POST'])]
    public function scrape(Request $request, ImageScraper $imageScraper): Response
    {
        $url = $request->request->get('url');
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            $images = $imageScraper->scrape($url);
            $totalSize = array_sum(array_column($images, 'size'));

            return $this->render('image/result.html.twig', [
                'images' => $images,
                'totalSize' => $totalSize,
                'count' => count($images),
                'url' => $url
            ]);
        }

        return $this->redirectToRoute('home');
    }

    #[Route('/download', name: 'download_images', methods: ['POST'])]
    public function downloadImages(Request $request, ImageScraper $imageScraper): Response
    {
        $images = json_decode($request->request->get('images', '[]'), true);
        $zipFilename = $imageScraper->downloadImages($images);

        $response = new BinaryFileResponse($zipFilename);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'images.zip');

        return $response;
    }
}

