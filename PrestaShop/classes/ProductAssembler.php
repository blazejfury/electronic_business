<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchContext;

/**
 * Class ProductAssemblerCore.
 */
class ProductAssemblerCore
{
    private $context;
    private $searchContext;

    /**
     * ProductAssemblerCore constructor.
     *
     * @param \Context $context
     */
    public function __construct(Context $context)
    {
        $this->context = $context;
        $this->searchContext = new ProductSearchContext($context);
    }

    /**
     * Add missing product fields.
     *
     * @param array $rawProduct
     *
     * @return array
     *
     * @throws PrestaShopDatabaseException
     */
    private function addMissingProductFields(array $rawProduct): array
    {
        $idShop = $this->searchContext->getIdShop();
        $idShopGroup = $this->searchContext->getIdShopGroup();
        $isStockSharingBetweenShopGroupEnabled = $this->searchContext->isStockSharingBetweenShopGroupEnabled();
        $idLang = $this->searchContext->getIdLang();
        $idProduct = (int) $rawProduct['id_product'];
        $prefix = _DB_PREFIX_;

        $nbDaysNewProduct = (int) Configuration::get('PS_NB_DAYS_NEW_PRODUCT');
        if (!Validate::isUnsignedInt($nbDaysNewProduct)) {
            $nbDaysNewProduct = 20;
        }

        $now = date('Y-m-d') . ' 00:00:00';

        $sql = "SELECT
                    p.*,
                    ps.*,
                    pl.*,
                    sa.out_of_stock,
                    IFNULL(sa.quantity, 0) as quantity,
                    (DATEDIFF(
                        p.`date_add`,
                        DATE_SUB(
                            '$now',
                            INTERVAL $nbDaysNewProduct DAY
                        )
                    ) > 0) as new
                FROM {$prefix}product p
                LEFT JOIN {$prefix}product_lang pl
                    ON pl.id_product = p.id_product
                    AND pl.id_shop = $idShop
                    AND pl.id_lang = $idLang
                LEFT JOIN {$prefix}stock_available sa ";

        if ($isStockSharingBetweenShopGroupEnabled) {
            $sql .= "  ON sa.id_product = p.id_product
			        AND sa.id_shop = 0
			        AND sa.id_shop_group = $idShopGroup ";
        } else {
            $sql .= "  ON sa.id_product = p.id_product
			        AND sa.id_shop = $idShop ";
        }
        $sql .= "LEFT JOIN {$prefix}product_shop ps
			        ON ps.id_product = p.id_product
			        AND ps.id_shop = $idShop
			    WHERE p.id_product = $idProduct
			    LIMIT 1";

        $rows = Db::getInstance()->executeS($sql);
        if ($rows === false) {
            return $rawProduct;
        }

        return array_merge($rows[0], $rawProduct);
    }

    /**
     * Assemble Product.
     *
     * @param array $rawProduct
     *
     * @return mixed
     *
     * @throws PrestaShopDatabaseException
     */
    public function assembleProduct(array $rawProduct)
    {
        $enrichedProduct = $this->addMissingProductFields($rawProduct);

        return Product::getProductProperties(
            $this->searchContext->getIdLang(),
            $enrichedProduct,
            $this->context
        );
    }
}
