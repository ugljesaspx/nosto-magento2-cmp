<?php
/**
 * Copyright (c) 2020, Nosto Solutions Ltd
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 * this list of conditions and the following disclaimer in the documentation
 * and/or other materials provided with the distribution.
 *
 * 3. Neither the name of the copyright holder nor the names of its contributors
 * may be used to endorse or promote products derived from this software without
 * specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @author Nosto Solutions Ltd <contact@nosto.com>
 * @copyright 2020 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 *
 */

namespace Nosto\Cmp\Model\Service\Facet;

use Exception;
use Magento\Catalog\Model\CategoryRepository;
use Magento\Catalog\Model\Layer\Filter\Item;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\CatalogSearch\Model\Layer\Filter\Category;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\LayeredNavigation\Block\Navigation\State;
use Magento\Store\Model\Store;
use Nosto\Cmp\Exception\FacetValueException;
use Nosto\Cmp\Model\Facet\Facet;
use Nosto\Cmp\Utils\Traits\LoggerTrait;
use Nosto\Operation\Recommendation\ExcludeFilters;
use Nosto\Operation\Recommendation\IncludeFilters;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Logger\Logger;
use Nosto\Tagging\Model\Service\Product\Category\DefaultCategoryService as NostoCategoryBuilder;

class BuildWebFacetService
{
    use LoggerTrait {
        LoggerTrait::__construct as loggerTraitConstruct; // @codingStandardsIgnoreLine
    }

    /** @var State */
    private $state;

    /** @var NostoCategoryBuilder */
    private $nostoCategoryBuilder;

    /** @var CategoryRepository */
    private $categoryRepository;

    /** @var NostoHelperData */
    private $nostoHelperData;

    /** @var NostoHelperScope */
    private $nostoHelperScope;

    /** @var string */
    private $brand;

    /**
     * BuildWebFacetService constructor.
     * @param NostoCategoryBuilder $nostoCategoryBuilder
     * @param CategoryRepository $categoryRepository
     * @param NostoHelperData $nostoHelperData
     * @param NostoHelperScope $nostoHelperScope
     * @param State $state
     * @param Logger $logger
     */
    public function __construct(
        NostoCategoryBuilder $nostoCategoryBuilder,
        CategoryRepository $categoryRepository,
        NostoHelperData $nostoHelperData,
        NostoHelperScope $nostoHelperScope,
        State $state,
        Logger $logger
    ) {
        $this->loggerTraitConstruct(
            $logger
        );
        $this->nostoCategoryBuilder = $nostoCategoryBuilder;
        $this->categoryRepository = $categoryRepository;
        $this->nostoHelperData = $nostoHelperData;
        $this->nostoHelperScope = $nostoHelperScope;
        $this->state = $state;
    }

    /**
     * @return Facet
     */
    public function getFacets(): Facet
    {
        $includeFilters = new IncludeFilters();
        $excludeFilters = new ExcludeFilters();

        try {
            $this->populateFilters($includeFilters);
        } catch (Exception $e) {
            $this->exception($e);
        }

        return new Facet($includeFilters, $excludeFilters);
    }

    /**
     * @param IncludeFilters $includeFilters
     * @throws FacetValueException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function populateFilters(IncludeFilters &$includeFilters): void
    {
        $filters = $this->state->getActiveFilters();
        // Current store id value is unavailable
        $store = $this->nostoHelperScope->getStore();
        foreach ($filters as $filter) {
            $this->mapIncludeFilter($store, $includeFilters, $filter);
        }
    }

    /**
     * @param Store $store
     * @param IncludeFilters $includeFilters
     * @param Item $item
     * @throws FacetValueException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function mapIncludeFilter(Store $store, IncludeFilters &$includeFilters, Item $item)
    {
        if ($item->getFilter() instanceof Category) {
            $categoryId = $item->getData('value');
            $category = $this->getCategoryName($store, $categoryId);
            if ($category == null) {
                $this->debugWithSource('Could not get category from filters');
                return;
            }
            $this->mapValueToFilter($includeFilters, $store, 'category', $category);
            return;
        }

        //Magento\CatalogSearch\Model\Layer\Filter\Attribute
        $filter = $item->getFilter();
        if ($filter === null) {
            return;
        }

        /** @var Attribute $attributeModel */
        $attributeModel = $filter->getData('attribute_model');
        if ($attributeModel === null) {
            return;
        }

        /** @var string $frontendInput */
        $frontendInput = $attributeModel->getData('frontend_input');
        if ($frontendInput === null) {
            return;
        }

        $value = '';
        switch ($frontendInput) {
            case 'price':
                $value = $item->getData('value');
                break;
            case 'select':
            case 'multiselect':
                $value = $item->getData('label');
                break;
            case 'date':
                break;
            case 'boolean':
                $value = (bool)$item->getData('value');
                break;
            default:
                $this->debugWithSource('Cannot build include filter for "%s" frontend input type', [$frontendInput]);
                return;
        }

        $attributeCode = $attributeModel->getAttributeCode();
        if (!is_string($attributeCode)) {
            $this->debugWithSource('Cannot build include filter for "%s" attribute ', [$attributeModel->getName()]);
            return;
        }
        $this->mapValueToFilter($includeFilters, $store, $attributeCode, $value);
    }

    /**
     * @param Store $store
     * @param $categoryId
     * @return string|null
     * @throws NoSuchEntityException
     */
    private function getCategoryName(Store $store, $categoryId): ?string
    {
        //@phan-suppress-next-next-line PhanTypeMismatchArgument
        $category = $this->categoryRepository->get($categoryId, $store->getId());
        return $this->nostoCategoryBuilder->getCategory($category, $store);
    }

    /**
     * @param IncludeFilters $includeFilters
     * @param Store $store
     * @param string $name
     * @param string|array $value
     * @throws FacetValueException
     * @noinspection PhpParameterByRefIsNotUsedAsReferenceInspection
     */
    private function mapValueToFilter(IncludeFilters &$includeFilters, Store $store, string $name, $value)
    {
        if ($this->brand == null) {
            $this->brand = $this->nostoHelperData->getBrandAttribute($store);
        }

        switch (strtolower($name)) {
            case 'price':
                $includeFilters->setPrice(min($value), max($value));
                break;
            case 'new':
                $value = (bool) $value ? 'yes' : 'no';
                $includeFilters->setCustomFields($name, $this->makeArrayFromValue($store, $name, $value));
                break;
            case 'category':
                $includeFilters->setCategories([$value]);
                break;
            case $this->brand:
                $includeFilters->setBrands($this->makeArrayFromValue($store, $name, $value));
                break;
            default:
                $includeFilters->setCustomFields($name, $this->makeArrayFromValue($store, $name, $value));
                break;
        }
    }

    /**
     * @param Store $store
     * @param $name
     * @param $value
     * @return array
     * @throws FacetValueException
     */
    private function makeArrayFromValue(Store $store, $name, $value): array
    {
        if (is_string($value) || is_numeric($value)) {
            $value = [$value];
        }

        if (is_bool($value)) {
            // bool Yes/No attributes are stored as text in Nosto
            $value = $value ? "Yes" : "No";
            $value = [$value];
        }

        if (is_array($value)) {
            return $value;
        }

        throw new FacetValueException($store, $name, $value);
    }
}
