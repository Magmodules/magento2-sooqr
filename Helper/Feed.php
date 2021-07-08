<?php
/**
 * Copyright © 2019 Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magmodules\Sooqr\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Filesystem;
use Magento\Framework\UrlInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magmodules\Sooqr\Helper\General as GeneralHelper;
use Magmodules\Sooqr\Exceptions\Validation as ValidationException;

/**
 * Class Feed
 *
 * @package Magmodules\Sooqr\Helper
 */
class Feed extends AbstractHelper
{

    const DEFAULT_FILENAME = 'sooqr.xml';
    const DEFAULT_DIRECTORY = 'sooqr';
    const DEFAULT_DIRECTORY_PATH = 'pub/media/sooqr';
    const XPATH_GENERATE_ENABLED = 'magmodules_sooqr/generate/enable';
    const XPATH_FEED_URL = 'magmodules_sooqr/feeds/url';
    const XPATH_FEED_RESULT = 'magmodules_sooqr/feeds/results';
    const XPATH_FEED_FILENAME = 'magmodules_sooqr/generate/filename';

    /**
     * @var General
     */
    private $generalHelper;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var Filesystem\Directory\WriteInterface
     */
    private $directory;

    /**
     * @var
     */
    private $stream;

    /**
     * @var File
     */
    private $filesystemIo;

    /**
     * @var TimezoneInterface
     */
    private $timezone;

    /**
     * @var DateTime
     */
    private $datetime;

    /**
     * @var null|string
     */
    private $baseDir = null;

    /**
     * @var string
     */
    private $tempDir = null;

    /**
     * Feed constructor.
     *
     * @param Context               $context
     * @param StoreManagerInterface $storeManager
     * @param Filesystem            $filesystem
     * @param DirectoryList         $directoryList
     * @param File                  $filesystemIo
     * @param DateTime              $datetime
     * @param TimezoneInterface     $timezone
     * @param General               $generalHelper
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        Filesystem $filesystem,
        DirectoryList $directoryList,
        File $filesystemIo,
        DateTime $datetime,
        TimezoneInterface $timezone,
        GeneralHelper $generalHelper
    ) {
        $this->generalHelper = $generalHelper;
        $this->storeManager = $storeManager;
        $this->directory = $filesystem;
        $this->filesystemIo = $filesystemIo;
        $this->baseDir = $directoryList->getPath(DirectoryList::ROOT);
        $this->tempDir = $directoryList->getPath(DirectoryList::TMP);
        $this->timezone = $timezone;
        $this->datetime = $datetime;
        parent::__construct($context);
    }

    /**
     * @return array
     */
    public function getConfigData()
    {
        $feedData = [];
        $stores = $this->storeManager->getStores();
        foreach ($stores as $store) {
            $storeId = $store->getStoreId();
            $location = $this->getFeedLocation($storeId);
            $feedData[$storeId] = [
                'store_id'  => $storeId,
                'code'      => $store->getCode(),
                'name'      => $store->getName(),
                'is_active' => $store->getIsActive(),
                'status'    => $this->generalHelper->getGenerateEnabled($storeId),
                'feed'      => (!empty($location['url']) ? $location['url'] : ''),
                'full_path' => (!empty($location['full_path']) ? $location['full_path'] : ''),
                'result'    => $this->generalHelper->getUncachedStoreValue(self::XPATH_FEED_RESULT, $storeId),
                'available' => (!empty($location['full_path']) ? file_exists($location['full_path']) : false)
            ];
        }
        return $feedData;
    }

    /**
     * @param        $storeId
     * @param string $type
     *
     * @return array
     */
    public function getFeedLocation($storeId, $type = null)
    {
        $fileName = $this->generalHelper->getStoreValue(self::XPATH_FEED_FILENAME, $storeId);

        if (empty($fileName)) {
            $fileName = self::DEFAULT_FILENAME;
        }

        if ($type == 'preview') {
            $extra = '-' . $storeId . '-preview.xml';
        } else {
            $extra = '-' . $storeId . '.xml';
        }

        if (substr($fileName, -3) != 'xml') {
            $fileName = $fileName . $extra;
        } else {
            $fileName = substr($fileName, 0, -4) . $extra;
        }

        $mediaUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
        $feedUrl = $mediaUrl . self::DEFAULT_DIRECTORY;

        $location = [];
        $location['path'] = self::DEFAULT_DIRECTORY_PATH . '/' . $fileName;
        $location['full_path'] = $this->baseDir . '/' . self::DEFAULT_DIRECTORY_PATH . '/' . $fileName;
        $location['full_temp_path'] = $this->tempDir . '/' . $fileName;
        $location['url'] = $feedUrl . '/' . $fileName;
        $location['file_name'] = $fileName;
        $location['base_dir'] = self::DEFAULT_DIRECTORY_PATH;

        return $location;
    }

    /**
     * @param $storeId
     * @param $processed
     * @param $time
     * @param $date
     * @param $type
     * @param $pages
     */
    public function updateResult($storeId, $processed, $time, $date, $type, $pages)
    {
        if (empty($type)) {
            $type = 'manual';
        }

        if ($type != 'preview') {
            if ($pages > 1) {
                $html = __(
                    'Date: %1 (%2) - Products: %3 (%4 pages) - Time: %5',
                    $date,
                    $type,
                    $processed,
                    $pages,
                    $time
                );
            } else {
                $html = __('Date: %1 (%2) - Products: %3 - Time: %4', $date, $type, $processed, $time);
            }
            $this->generalHelper->setConfigData($html, self::XPATH_FEED_RESULT, $storeId);
        }
    }

    /**
     * @param $row
     *
     * @throws LocalizedException
     */
    public function writeRow($row)
    {
        $row = $this->stripInvalidXml($row);
        $this->getStream()->write($row);
    }

    /**
     *
     * @param $value
     *
     * @return string
     */
    public function stripInvalidXml($value)
    {
        $regex = '/(
            [\xC0-\xC1]
            | [\xF5-\xFF]
            | \xE0[\x80-\x9F]
            | \xF0[\x80-\x8F]
            | [\xC2-\xDF](?![\x80-\xBF])
            | [\xE0-\xEF](?![\x80-\xBF]{2})
            | [\xF0-\xF4](?![\x80-\xBF]{3})
            | (?<=[\x0-\x7F\xF5-\xFF])[\x80-\xBF]
            | (?<![\xC2-\xDF]|[\xE0-\xEF]|[\xE0-\xEF][\x80-\xBF]|
            [\xF0-\xF4]|[\xF0-\xF4][\x80-\xBF]|[\xF0-\xF4][\x80-\xBF]{2})[\x80-\xBF]
            | (?<=[\xE0-\xEF])[\x80-\xBF](?![\x80-\xBF])
            | (?<=[\xF0-\xF4])[\x80-\xBF](?![\x80-\xBF]{2})
            | (?<=[\xF0-\xF4][\x80-\xBF])[\x80-\xBF](?![\x80-\xBF])
        )/x';
        $value = preg_replace($regex, '', $value);
        $return = '';
        $length = strlen($value);
        for ($i = 0; $i < $length; $i++) {
            $current = ord($value[$i]);
            if (($current == 0x9) ||
                ($current == 0xA) ||
                ($current == 0xD) ||
                (($current >= 0x20) && ($current <= 0xD7FF)) ||
                (($current >= 0xE000) && ($current <= 0xFFFD)) ||
                (($current >= 0x10000) && ($current <= 0x10FFFF))
            ) {
                $return .= chr($current);
            } else {
                $return .= ' ';
            }
        }
        return $return;
    }

    /**
     * @return mixed
     * @throws LocalizedException
     */
    public function getStream()
    {
        if ($this->stream) {
            return $this->stream;
        } else {
            throw new LocalizedException(__('File handler unreachable'));
        }
    }

    /**
     * @param $config
     * @param $headerConfig
     *
     * @throws LocalizedException
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function createFeed($config, $headerConfig)
    {
        $filename = $config['feed_locations']['file_name'];
        $this->stream = $this->directory->getDirectoryWrite(DirectoryList::TMP)->openFile($filename);

        $header = '<?xml version="1.0" encoding="utf-8"?>' . PHP_EOL;
        $header .= '<rss xmlns:sqr="http://base.sooqr.com/ns/1.0" version="2.0" encoding="utf-8">' . PHP_EOL;
        $header .= $headerConfig;
        $header .= ' <products>' . PHP_EOL;

        $this->getStream()->write($header);
    }

    /**
     * @param $summary
     *
     * @throws LocalizedException
     */
    public function writeFooter($summary)
    {
        $footer = ' </products>' . PHP_EOL;
        $footer .= $summary;
        $footer .= '</rss>' . PHP_EOL;
        $this->getStream()->write($footer);
    }

    /**
     * @param $config
     * @param $type
     *
     * @throws ValidationException
     */
    public function validateAndMove($config, $type)
    {
        $src = $config['feed_locations']['full_temp_path'];
        $destination = $config['feed_locations']['full_path'];

        libxml_use_internal_errors(true);

        $doc = new \DOMDocument;
        $validationErrrors = [];
        if (!$doc->load($src)) {
            foreach (libxml_get_errors() as $error) {
                $validationErrrors[] = $error->message;
            }

            libxml_clear_errors();
        }

        libxml_use_internal_errors(false);

        if (empty($validationErrrors)) {
            $this->filesystemIo->setAllowCreateFolders(true);
            $this->filesystemIo->createDestinationDir(dirname($destination));
            $this->filesystemIo->cp($src, $destination);
        } else {
            $this->generalHelper->addTolog($type, $validationErrrors, 'validation');
            throw new ValidationException(__('Unable to genrate feed due to validation errors, please check var/sooqr/validation.log'));
        }
    }

    /**
     * @return array
     */
    public function getFeedHeader()
    {
        $summary = [];
        $summary['system'] = 'Magento 2';
        $summary['extension'] = GeneralHelper::MODULE_CODE;
        $summary['extension_version'] = $this->generalHelper->getExtensionVersion();
        $summary['token'] = $this->generalHelper->getToken();
        $summary['magento_version'] = $this->generalHelper->getMagentoVersion();
        $summary['base_url'] = $this->generalHelper->getBaseUrl();

        return $summary;
    }

    /**
     * @param $timeStart
     * @param $processed
     * @param $limit
     *
     * @return array
     */
    public function getFeedResults($timeStart, $processed, $limit)
    {
        $summary = [];
        $summary['products_total'] = $processed;
        $summary['products_limit'] = $limit;
        $summary['processing_time'] = $this->getTimeUsage($timeStart);
        $summary['date_created'] = $this->timezone->formatDateTime(null, 1);
        return $summary;
    }

    /**
     * @return array
     */
    public function getInstallation()
    {
        $json = [];
        $storeIds = $this->generalHelper->getEnabledArray('magmodules_sooqr/general/enable');

        foreach ($storeIds as $sId) {
            $feedLocacation = $this->getFeedLocation($sId);
            if (isset($feedLocacation['url'])) {
                $feedUrl = $feedLocacation['url'];
            } else {
                $feedUrl = '';
            }
            $json['feeds'][$sId]['name'] = $this->generalHelper->getStoreName($sId);
            $json['feeds'][$sId]['feed_url'] = $feedUrl;
            $json['feeds'][$sId]['currency'] = $this->generalHelper->getCurrecyCode($sId);
            $json['feeds'][$sId]['locale'] = $this->generalHelper->getStoreValue('general/locale/code', $sId);
            $json['feeds'][$sId]['country'] = $this->generalHelper->getStoreValue('general/country/default', $sId);
            $json['feeds'][$sId]['timezone'] = $this->generalHelper->getStoreValue('general/locale/timezone', $sId);
            $json['feeds'][$sId]['extension'] = 'Magmodules_Sooqr';
            $json['feeds'][$sId]['platform_version'] = $this->generalHelper->getMagentoVersion();
            $json['feeds'][$sId]['extension_version'] = $this->generalHelper->getExtensionVersion();
        }
        return $json;
    }

    /**
     * @param $timeStart
     *
     * @return float|string
     */
    public function getTimeUsage($timeStart)
    {
        $time = round((microtime(true) - $timeStart));
        if ($time > 120) {
            $time = round($time / 60, 1) . ' ' . __('minutes');
        } else {
            $time = round($time) . ' ' . __('seconds');
        }

        return $time;
    }

    /**
     * @param $page
     * @param $pages
     * @param $storeId
     */
    public function addLog($page, $pages, $storeId)
    {
        $msg = $page . '/' . $pages . ': ' . $this->getMemoryUsage();
        $this->generalHelper->addTolog('Feed Generation StoreId: ' . $storeId, $msg);
    }

    /**
     * @return string
     */
    public function getMemoryUsage()
    {
        $memoryUsage = memory_get_usage(true);
        if ($memoryUsage < 1024) {
            $usage = $memoryUsage . ' b';
        } elseif ($memoryUsage < 1048576) {
            $usage = round($memoryUsage / 1024, 2) . ' KB';
        } else {
            $usage = round($memoryUsage / 1048576, 2) . ' MB';
        }

        return $usage;
    }
}
