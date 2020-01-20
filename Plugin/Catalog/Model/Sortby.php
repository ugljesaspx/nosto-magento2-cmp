<?php
/**
 * Copyright (c) 2019, Nosto Solutions Ltd
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
 * @copyright 2019 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 *
 */


namespace Nosto\Cmp\Plugin\Catalog\Model;

use Magento\Catalog\Model\Category\Attribute\Source\Sortby as MagentoSortby;
use Nosto\Cmp\Helper\CategorySorting as NostoHelperSorting;
use Nosto\Cmp\Helper\Data as NostoCmpHelperData;
use Magento\Backend\Block\Template\Context;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\NoSuchEntityException;

class Sortby extends Template
{

    /** @var NostoCmpHelperData */
    private $nostoCmpHelperData;

    /** @var NostoHelperSorting */
    private $nostoHelperSorting;

    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var Http $request */
    private $request;

    /**
     * Sortby constructor.
     * @param NostoCmpHelperData $nostoCmpHelperData
     * @param NostoHelperSorting $nostoHelperSorting
     * @param Context $context
     * @param Http $request
     * @param array $data
     */
    public function __construct(
        NostoCmpHelperData $nostoCmpHelperData,
        NostoHelperSorting $nostoHelperSorting,
        Context $context,
        Http $request,
        array $data = []
    ) {
        $this->nostoCmpHelperData = $nostoCmpHelperData;
        $this->nostoHelperSorting = $nostoHelperSorting;
        $this->storeManager = $context->getStoreManager();
        $this->request = $request;
        parent::__construct($context, $data);
    }

    /**
     * @param MagentoSortby $sortBy
     * @param $options
     * @return array
     * @throws NoSuchEntityException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetAllOptions(MagentoSortby $sortBy, $options)
    {
        $id = (int)$this->request->getParam('store');
        $store = $this->storeManager->getStore($id);

        if ($this->nostoHelperSorting->canUseCategorySorting($id) &&
            $this->nostoCmpHelperData->isCategorySortingEnabled($store)
        ) {
            // new option
            $customOption = [
              ['label' => __('Relevance'), 'value' => NostoHelperSorting::NOSTO_PERSONALIZED_KEY]
            ];

            // merge default sorting options with custom options
            $options = array_merge($options, $customOption);
        }
        return $options;
    }
}
