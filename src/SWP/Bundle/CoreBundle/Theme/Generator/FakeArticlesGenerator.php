<?php

declare(strict_types=1);

/*
 * This file is part of the Superdesk Web Publisher Core Bundle.
 *
 * Copyright 2017 Sourcefabric z.ú. and contributors.
 *
 * For the full copyright and license information, please see the
 * AUTHORS and LICENSE files distributed with this source code.
 *
 * @copyright 2017 Sourcefabric z.ú
 * @license http://www.superdesk.org/license
 */

namespace SWP\Bundle\CoreBundle\Theme\Generator;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use SWP\Bundle\ContentBundle\Factory\ArticleFactoryInterface;
use SWP\Bundle\ContentBundle\Factory\MediaFactoryInterface;
use SWP\Bundle\ContentBundle\Manager\MediaManagerInterface;
use SWP\Bundle\ContentBundle\Model\ImageRendition;
use SWP\Bundle\CoreBundle\Model\ArticleInterface;
use SWP\Bundle\CoreBundle\Model\ArticleMediaInterface;
use SWP\Bundle\CoreBundle\Model\Image;
use SWP\Bundle\CoreBundle\Repository\ArticleRepositoryInterface;
use Faker;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FakeArticlesGenerator implements FakeArticlesGeneratorInterface
{
    /**
     * @var ArticleFactoryInterface
     */
    protected $articleFactory;

    /**
     * @var MediaManagerInterface
     */
    protected $mediaManager;

    /**
     * @var MediaFactoryInterface
     */
    protected $articleMediaFactory;

    /**
     * @var ArticleRepositoryInterface
     */
    protected $articleRepository;

    /**
     * FakeArticlesGenerator constructor.
     *
     * @param ArticleFactoryInterface    $articleFactory
     * @param MediaManagerInterface      $mediaManager
     * @param MediaFactoryInterface      $articleMediaFactory
     * @param ArticleRepositoryInterface $articleRepository
     */
    public function __construct(ArticleFactoryInterface $articleFactory, MediaManagerInterface $mediaManager, MediaFactoryInterface $articleMediaFactory, ArticleRepositoryInterface $articleRepository)
    {
        $this->articleFactory = $articleFactory;
        $this->mediaManager = $mediaManager;
        $this->articleMediaFactory = $articleMediaFactory;
        $this->articleRepository = $articleRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function generate(int $numberOfArticles): array
    {
        $articles = [];
        for (; $numberOfArticles > 0; --$numberOfArticles) {
            /** @var ArticleInterface $article */
            $article = $this->articleFactory->create();
            $faker = Faker\Factory::create();
            $article->setTitle($faker->catchPhrase());
            $article->setBody($faker->paragraph(20));
            $article->setLead($faker->paragraph(3));
            $article->setLocale('en');
            $article->setStatus(ArticleInterface::STATUS_PUBLISHED);
            $article->setPublishedAt(new \DateTime());
            $article->setPublishable(true);
            $article->setCode($faker->uuid);
            $this->articleRepository->persist($article);
            $article->setMedia($this->createArticleMedia($article));

            $articles[] = $article;
        }
        $this->articleRepository->flush();

        return $articles;
    }

    /**
     * @param ArticleInterface $article
     *
     * @return Collection
     */
    protected function createArticleMedia(ArticleInterface $article): Collection
    {
        $mediaId = uniqid();
        $faker = Faker\Factory::create();
        $fakeImage = $faker->image(sys_get_temp_dir(), 800, 800, 'cats', true, true, $article->getSlug());
        $uploadedFile = new UploadedFile($fakeImage, $mediaId, 'image/jpeg', filesize($fakeImage), null, true);
        /** @var Image $image */
        $image = $this->mediaManager->handleUploadedFile($uploadedFile, $mediaId);
        /** @var ArticleMediaInterface $articleMedia */
        $articleMedia = $this->articleMediaFactory->createEmpty();
        $articleMedia->setImage($image);
        $articleMedia->setArticle($article);
        $articleMedia->setKey('embedded'.uniqid());
        $articleMedia->setBody('This is very nice image caption...');
        $articleMedia->setByLine('By Best Editor');
        $articleMedia->setLocated('Porto');
        $articleMedia->setDescription('Media description');
        $articleMedia->setUsageTerms('Some super open terms');
        $articleMedia->setMimetype('image/jpeg');
        $article->setFeatureMedia($articleMedia);
        $this->articleRepository->persist($articleMedia);

        $imageRendition = new ImageRendition();
        $imageRendition->setImage($image);
        $imageRendition->setHeight(800);
        $imageRendition->setWidth(800);
        $imageRendition->setName('original');
        $imageRendition->setMedia($articleMedia);
        $articleMedia->addRendition($imageRendition);
        $this->articleRepository->persist($imageRendition);

        return new ArrayCollection([$articleMedia]);
    }
}
