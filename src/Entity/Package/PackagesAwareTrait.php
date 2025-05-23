<?php

namespace AppBundle\Entity\Package;

use AppBundle\Entity\Package;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @template T of PackageWithQuantityInterface
 */
trait PackagesAwareTrait
{

    /**
     * @var ArrayCollection<int, T>
     */
    #[Groups(['package'])]
    protected $packages;

    public function hasPackages()
    {
        return count($this->getPackages()) > 0;
    }

    public function hasPackage(Package $package)
    {
        foreach ($this->getPackages() as $p) {
            if ($p->getPackage() === $package) {
                return true;
            }
        }

        return false;
    }

    public function getQuantityForPackage(Package $package)
    {
        foreach ($this->getPackages() as $p) {
            if ($p->getPackage() === $package) {
                return $p->getQuantity();
            }
        }

        return 0;
    }
}
