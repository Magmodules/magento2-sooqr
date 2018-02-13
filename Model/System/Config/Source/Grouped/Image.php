<?php
/**
 * Copyright © 2018 Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magmodules\Sooqr\Model\System\Config\Source\Grouped;

use Magento\Framework\Option\ArrayInterface;

/**
 * Class Image
 *
 * @package Magmodules\Sooqr\Model\System\Config\Source\Grouped
 */
class Image implements ArrayInterface
{

    /**
     * Options array
     *
     * @var array
     */
    public $options = null;

    /**
     * @return array
     */
    public function toOptionArray()
    {
        if (!$this->options) {
            $this->options = [
                ['value' => '0', 'label' => __('No')],
                ['value' => '1', 'label' => __('Yes')],
                ['value' => '2', 'label' => __('Only if Empty (Recommended)')],
            ];
        }
        return $this->options;
    }
}
