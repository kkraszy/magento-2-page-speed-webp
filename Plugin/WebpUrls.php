<?php

declare(strict_types=1);

namespace Kkkonrad\PageSpeed\Plugin;

use Kkkonrad\PageSpeed\Model\Webp;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\Layout;
use Magento\Store\Model\StoreManagerInterface;

class WebpUrls
{
    public function __construct(
        private readonly Webp $webp,
        private readonly StoreManagerInterface $storeManager,
        private readonly Filesystem $filesystem
    ) {
    }

    public function afterRenderResult(ResultInterface $subject, $result, ResponseInterface $response)
    {
        if (!$subject instanceof Layout || !$response instanceof Http) {
            return $result;
        }
        $type = $response->getHeader('Content-Type');
        if ($type && !str_contains($type->getFieldValue(), 'html')) {
            return $result;
        }
        $response->setBody($this->webp->rewrite(
            (string)$response->getBody(),
            $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA),
            $this->filesystem->getDirectoryRead(DirectoryList::MEDIA)->getAbsolutePath()
        ));
        return $result;
    }
}
