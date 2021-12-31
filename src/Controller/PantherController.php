<?php

namespace App\Controller;

use App\Entity\Article;
use App\Repository\ArticleRepository;
use App\Repository\PlatformRepository;
use Doctrine\ORM\EntityManagerInterface;
use Facebook\WebDriver\WebDriverBy;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Panther\Client;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/panther')]
class PantherController extends AbstractController
{
    private function in_arrayi($needle, $haystack)
    {
        return in_array(strtolower($needle), array_map('strtolower', $haystack));
    }

    #[Route('/monde', name: 'panther_monde')]
    public function monde(PlatformRepository $platformRepository, ArticleRepository $articleRepository, EntityManagerInterface $em): Response
    {
        //? The platform for this function is "Le monde", which id is 2.
        $platform = $platformRepository->find(2);

        //? Initiate article array and positives array. Also a basic filter for words that needs to be better.
        $articles = [];
        $positives = [];
        $filters = ['paix', 'innovation', 'amélioration', 'cadeau', 'cœur', 'création', 'culinaire', 'cuisine', 'art', 'artistique', 'investissements', 'recette', 'apprentissage', 'beau', 'célèbre', 'célébrer'];

        // dd($articles);

        //? Using a chrome client from panther, select every articles from the main page and retrieve their links, title and if it's behind a paywall or not.
        $client = Client::createChromeClient();

        $crawler = $client->request('GET', 'https://www.lemonde.fr/');

        $crawler = $client->waitFor('h1');
        $articleElements = $crawler->findElements(WebDriverBy::CssSelector('.article'));
        foreach ($articleElements as $articleElement) {
            $titleSelector = $articleElement->findElement(WebDriverBy::CssSelector('.article__title'));

            if ($articleElement->findElements(WebDriverBy::CssSelector('.icon__premium'))) {
                $paywall = true;
            }
            if ($articleElement->getAttribute('href')) {
                $link = $articleElement->getAttribute('href');
            } else {
                $linkSelector = $articleElement->findElement(WebDriverBy::tagName('a'));
                $link = $linkSelector->getAttribute('href');
            }
            $title = trim(html_entity_decode(strip_tags($titleSelector->getDomProperty('innerHTML'))));

            $obj = new Article();
            $obj->setTitle($title);
            $obj->setLink($link);
            $paywall = $articleElement->findElements(WebDriverBy::CssSelector('.icon__premium')) ? true : false;
            $obj->setPaywall($paywall);
            $obj->setScore(0);
            $obj->setPlatform($platform);

            $articles[] = $obj;
        }

        //? Loop over articles to filter them (we need to make a better filter).

        foreach ($articles as $article) {
            foreach ($filters as $filter) {
                if (str_contains($article->getTitle(), $filter)) {
                    $positives[] = $article;
                }
            }
        }

        //? Function to filter duplicates
        $positives = array_unique($positives);

        //? Loop over already existing article to filter duplicates
        $articlesInDb = $articleRepository->findAll();

        foreach ($positives as $uniquePositive) {
            if (!$this->in_arrayi($uniquePositive, $articlesInDb)) {
                $em->persist($uniquePositive);
            }
        }

        $em->flush();

        return new JsonResponse("Scanned {$platform->getName()} for new articles and added new ones");
    }
}