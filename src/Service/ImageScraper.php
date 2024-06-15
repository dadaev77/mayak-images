<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\Exception\TransportException;

class ImageScraper
{
    private $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    public function scrape(string $url): array
    {
        try {
            set_time_limit(300); // Увеличиваем максимальное время выполнения до 300 секунд

            $response = $this->httpClient->request('GET', $url);
            $html = $response->getContent();

            $crawler = new Crawler($html);
            $images = [];

            $crawler->filter('img')->each(function (Crawler $node) use (&$images, $url) {
                $src = $node->attr('src');
                $absoluteUrl = $this->getAbsoluteUrl($url, $src);
                $size = $this->getImageSize($absoluteUrl);

                if ($size !== null) {
                    $images[] = [
                        'url' => $absoluteUrl,
                        'size' => $size,
                    ];
                }
            });

            return $images;

        } catch (TransportException $e) {
            throw new \RuntimeException('Не удалось разрешить хост: ' . $url, 0, $e);
        }
    }

    public function downloadImages(array $images): string
    {
        $zip = new \ZipArchive();
        $zipFilename = tempnam(sys_get_temp_dir(), 'images') . '.zip';

        if ($zip->open($zipFilename, \ZipArchive::CREATE) !== TRUE) {
            throw new \RuntimeException('Не удалось создать zip-архив');
        }

        foreach ($images as $index => $image) {
            $imageContent = $this->httpClient->request('GET', $image['url'])->getContent();
            $zip->addFromString('image' . ($index + 1) . '.' . pathinfo($image['url'], PATHINFO_EXTENSION), $imageContent);
        }

        $zip->close();

        return $zipFilename;
    }

    private function getAbsoluteUrl(string $baseUrl, string $relativeUrl): string
    {
        if (parse_url($relativeUrl, PHP_URL_SCHEME) !== null) {
            return $relativeUrl;
        }

        $baseUrl = rtrim($baseUrl, '/');
        $relativeUrl = ltrim($relativeUrl, '/');

        return $baseUrl . '/' . $relativeUrl;
    }

    private function getImageSize(string $url): ?int
    {
        try {
            $response = $this->httpClient->request('HEAD', $url);
            $headers = $response->getHeaders();
            if (isset($headers['content-length'][0])) {
                return (int) $headers['content-length'][0];
            }
        } catch (\Exception $e) {
            // Обработка исключений
        }

        return null;
    }
}