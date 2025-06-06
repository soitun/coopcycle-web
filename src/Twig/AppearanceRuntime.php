<?php

namespace AppBundle\Twig;

use AppBundle\Service\SettingsManager;
use Intervention\Image\ImageManager;
use League\Flysystem\Filesystem;
use Liip\ImagineBundle\Service\FilterService;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Twig\Extension\RuntimeExtensionInterface;

class AppearanceRuntime implements RuntimeExtensionInterface
{
    private $settingsManager;
    private $assetsFilesystem;
    private $imagineFilter;
    private $projectCache;
    private $logoFallback;

    public function __construct(
        SettingsManager $settingsManager,
        Filesystem $assetsFilesystem,
        FilterService $imagineFilter,
        CacheInterface $projectCache,
        string $logoFallback
    ) {
        $this->settingsManager = $settingsManager;
        $this->assetsFilesystem = $assetsFilesystem;
        $this->imagineFilter = $imagineFilter;
        $this->projectCache = $projectCache;
        $this->logoFallback = $logoFallback;
    }

    public function logo()
    {
        $companyLogo = $this->settingsManager->get('company_logo');
        if (!empty($companyLogo) && $this->assetsFilesystem->fileExists($companyLogo)) {

            return $this->imagineFilter->getUrlOfFilteredImage($companyLogo, 'logo_thumbnail');
        }
    }

    public function companyLogo()
    {
        return $this->projectCache->get('content.company_logo.base_64', function (ItemInterface $item) {

            $item->expiresAfter(60 * 60 * 24);

            $companyLogo = $this->settingsManager->get('company_logo');

            $imageManager = ImageManager::gd();

            if (!empty($companyLogo) && $this->assetsFilesystem->fileExists($companyLogo)) {
                $image = $imageManager->read($this->assetsFilesystem->read($companyLogo));
            } else {
                $image = $imageManager->read(file_get_contents($this->logoFallback));
            }

            return $image->toPng()->toDataUri();
        });
    }

    public function hasAboutUs()
    {
        return $this->projectCache->get('content.about_us.exists', function (ItemInterface $item) {

            $item->expiresAfter(60 * 60 * 24);

            return $this->assetsFilesystem->fileExists('about_us.md');
        });
    }
}
