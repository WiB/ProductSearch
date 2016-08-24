<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\ProductSearch\Business\Saver;

use Generated\Shared\Search\PageIndexMap;
use Generated\Shared\Transfer\ProductAttributeKeyTransfer;
use Generated\Shared\Transfer\ProductSearchPreferencesTransfer;
use Orm\Zed\ProductSearch\Persistence\SpyProductSearchAttributeMap;
use Spryker\Zed\ProductSearch\Dependency\Facade\ProductSearchToProductInterface;
use Spryker\Zed\ProductSearch\Persistence\ProductSearchQueryContainerInterface;

class SearchPreferencesSaver implements SearchPreferencesSaverInterface
{

    /**
     * @var \Spryker\Zed\ProductSearch\Persistence\ProductSearchQueryContainerInterface
     */
    protected $productSearchQueryContainer;

    /**
     * @var \Spryker\Zed\ProductSearch\Dependency\Facade\ProductSearchToProductInterface
     */
    protected $productFacade;

    /**
     * @param \Spryker\Zed\ProductSearch\Persistence\ProductSearchQueryContainerInterface $productSearchQueryContainer
     * @param \Spryker\Zed\ProductSearch\Dependency\Facade\ProductSearchToProductInterface $productFacade
     */
    public function __construct(
        ProductSearchQueryContainerInterface $productSearchQueryContainer,
        ProductSearchToProductInterface $productFacade
    ) {
        $this->productSearchQueryContainer = $productSearchQueryContainer;
        $this->productFacade = $productFacade;
    }

    /**
     * @param \Generated\Shared\Transfer\ProductSearchPreferencesTransfer $productSearchPreferencesTransfer
     *
     * @throws \Exception
     *
     * @return void
     */
    public function create(ProductSearchPreferencesTransfer $productSearchPreferencesTransfer)
    {
        $this->productSearchQueryContainer
            ->getConnection()
            ->beginTransaction();

        $productAttributeKeyTransfer = $this->findOrCreateProductAttributeKey($productSearchPreferencesTransfer);

        try {
            $this
                ->addFullText($productSearchPreferencesTransfer, $productAttributeKeyTransfer->getIdProductAttributeKey())
                ->addFullTextBoosted($productSearchPreferencesTransfer, $productAttributeKeyTransfer->getIdProductAttributeKey())
                ->addSuggestionTerms($productSearchPreferencesTransfer, $productAttributeKeyTransfer->getIdProductAttributeKey())
                ->addCompletionTerms($productSearchPreferencesTransfer, $productAttributeKeyTransfer->getIdProductAttributeKey());

            $this->productSearchQueryContainer
                ->getConnection()
                ->commit();
        } catch (\Exception $e) {
            $this->productSearchQueryContainer
                ->getConnection()
                ->rollBack();

            throw $e;
        }
    }

    /**
     * @param \Generated\Shared\Transfer\ProductSearchPreferencesTransfer $productSearchPreferencesTransfer
     *
     * @throws \Exception
     *
     * @return void
     */
    public function update(ProductSearchPreferencesTransfer $productSearchPreferencesTransfer)
    {
        $this->productSearchQueryContainer->getConnection()->beginTransaction();

        try {
            $idProductAttributeKey = $productSearchPreferencesTransfer
                ->requireIdProductAttributeKey()
                ->getIdProductAttributeKey();

            $this->cleanProductSearchAttributeMap($idProductAttributeKey);

            $this
                ->addFullText($productSearchPreferencesTransfer, $idProductAttributeKey)
                ->addFullTextBoosted($productSearchPreferencesTransfer, $idProductAttributeKey)
                ->addSuggestionTerms($productSearchPreferencesTransfer, $idProductAttributeKey)
                ->addCompletionTerms($productSearchPreferencesTransfer, $idProductAttributeKey);

            $this->productSearchQueryContainer
                ->getConnection()
                ->commit();
        } catch (\Exception $e) {
            $this->productSearchQueryContainer
                ->getConnection()
                ->rollBack();

            throw $e;
        }
    }

    /**
     * @param \Generated\Shared\Transfer\ProductSearchPreferencesTransfer $productSearchPreferencesTransfer
     *
     * @return void
     */
    public function clean(ProductSearchPreferencesTransfer $productSearchPreferencesTransfer)
    {
        $idProductAttributeKey = $productSearchPreferencesTransfer
            ->requireIdProductAttributeKey()
            ->getIdProductAttributeKey();

        $this->cleanProductSearchAttributeMap($idProductAttributeKey);
    }

    /**
     * @param \Generated\Shared\Transfer\ProductSearchPreferencesTransfer $productSearchPreferencesTransfer
     *
     * @return \Generated\Shared\Transfer\ProductAttributeKeyTransfer
     */
    protected function findOrCreateProductAttributeKey(ProductSearchPreferencesTransfer $productSearchPreferencesTransfer)
    {
        if ($this->productFacade->hasProductAttributeKey($productSearchPreferencesTransfer->getKey())) {
            $productAttributeKeyTransfer = $this->productFacade->getProductAttributeKey($productSearchPreferencesTransfer->getKey());

            return $productAttributeKeyTransfer;
        }

        $productAttributeKeyTransfer = new ProductAttributeKeyTransfer();
        $productAttributeKeyTransfer->setKey($productSearchPreferencesTransfer->getKey());
        $productAttributeKeyTransfer = $this->productFacade->createProductAttributeKey($productAttributeKeyTransfer);

        return $productAttributeKeyTransfer;
    }

    /**
     * @param int $idProductAttributeKey
     *
     * @return void
     */
    protected function cleanProductSearchAttributeMap($idProductAttributeKey)
    {
        $this
            ->productSearchQueryContainer
            ->queryProductSearchAttributeMapByFkProductAttributeKey($idProductAttributeKey)
            ->delete();
    }

    /**
     * @param \Generated\Shared\Transfer\ProductSearchPreferencesTransfer $productSearchPreferencesTransfer
     * @param int $idProductAttributeKey
     *
     * @return $this
     */
    protected function addFullText(ProductSearchPreferencesTransfer $productSearchPreferencesTransfer, $idProductAttributeKey)
    {
        if ($productSearchPreferencesTransfer->getFullText() === true) {
            $this->createNewProductSearchAttributeMapRecord($idProductAttributeKey, PageIndexMap::FULL_TEXT);
        }

        return $this;
    }

    /**
     * @param \Generated\Shared\Transfer\ProductSearchPreferencesTransfer $productSearchPreferencesTransfer
     * @param int $idProductAttributeKey
     *
     * @return $this
     */
    protected function addFullTextBoosted(ProductSearchPreferencesTransfer $productSearchPreferencesTransfer, $idProductAttributeKey)
    {
        if ($productSearchPreferencesTransfer->getFullTextBoosted() === true) {
            $this->createNewProductSearchAttributeMapRecord($idProductAttributeKey, PageIndexMap::FULL_TEXT_BOOSTED);
        }

        return $this;
    }

    /**
     * @param \Generated\Shared\Transfer\ProductSearchPreferencesTransfer $productSearchPreferencesTransfer
     * @param int $idProductAttributeKey
     *
     * @return $this
     */
    protected function addSuggestionTerms(ProductSearchPreferencesTransfer$productSearchPreferencesTransfer, $idProductAttributeKey)
    {
        if ($productSearchPreferencesTransfer->getSuggestionTerms() === true) {
            $this->createNewProductSearchAttributeMapRecord($idProductAttributeKey, PageIndexMap::SUGGESTION_TERMS);
        }

        return $this;
    }

    /**
     * @param \Generated\Shared\Transfer\ProductSearchPreferencesTransfer $productSearchPreferencesTransfer
     * @param int $idProductAttributeKey
     *
     * @return $this
     */
    protected function addCompletionTerms(ProductSearchPreferencesTransfer$productSearchPreferencesTransfer, $idProductAttributeKey)
    {
        if ($productSearchPreferencesTransfer->getCompletionTerms() === true) {
            $this->createNewProductSearchAttributeMapRecord($idProductAttributeKey, PageIndexMap::COMPLETION_TERMS);
        }

        return $this;
    }

    /**
     * @param int $idProductAttributeKey
     * @param string $targetField
     *
     * @return void
     */
    protected function createNewProductSearchAttributeMapRecord($idProductAttributeKey, $targetField)
    {
        $entity = new SpyProductSearchAttributeMap();
        $entity
            ->setFkProductAttributeKey($idProductAttributeKey)
            ->setTargetField($targetField);

        $entity->save();
    }

}
