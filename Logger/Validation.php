<?php
/**
 * Copyright © 2019 Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magmodules\Sooqr\Logger;

use Monolog\Logger;

/**
 * Class Validation
 *
 * @package Magmodules\Sooqr\Logger
 */
class Validation extends Logger implements ValidationLoggerInterface
{

    /**
     * {@inheritDoc}
     */
    public function add($type, $data)
    {
        if (is_array($data) || is_object($data)) {
            $this->addInfo($type . ':' . json_encode($data));
        } else {
            $this->addInfo($type . ':' . $data);
        }
    }
}
