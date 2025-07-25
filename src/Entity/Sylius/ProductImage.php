<?php

namespace AppBundle\Entity\Sylius;

use ApiPlatform\Metadata\ApiProperty;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\ReusablePackaging;
use AppBundle\Sylius\Product\ProductInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Sylius\Component\Product\Model\Product as BaseProduct;
use Sylius\Component\Product\Model\ProductOptionValueInterface;
use Sylius\Component\Product\Model\ProductOptionInterface;
use Sylius\Component\Resource\Model\TimestampableTrait;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[Vich\Uploadable]
class ProductImage
{
    use TimestampableTrait;

    private $id;

    private $product;

    /**
     * @var File
     */
    #[Vich\UploadableField(mapping: "product_image", fileNameProperty: "imageName")]
    #[Assert\File(maxSize: '1024k', mimeTypes: ['image/jpg', 'image/jpeg', 'image/png'])]
    private $imageFile;

    /**
     * @var string
     */
    private $imageName;

    /**
     * @var string
     */
    private $ratio = '1:1';

    public function setImageName($imageName)
    {
        $this->imageName = $imageName;

        return $this;
    }

    public function getImageName()
    {
        return $this->imageName;
    }

    /**
     * If manually uploading a file (i.e. not using Symfony Form) ensure an instance
     * of 'UploadedFile' is injected into this setter to trigger the  update. If this
     * bundle's configuration parameter 'inject_on_load' is set to 'true' this setter
     * must be able to accept an instance of 'File' as the bundle will inject one here
     * during Doctrine hydration.
     *
     * @param File|UploadedFile|null $image
     */
    public function setImageFile(?File $image = null)
    {
        $this->imageFile = $image;

        return $this;
    }

    /**
     * @return File|null
     */
    public function getImageFile()
    {
        return $this->imageFile;
    }

    public function setProduct($product)
    {
        $this->product = $product;
    }

    /**
     * @return string
     */
    public function getRatio()
    {
        return $this->ratio;
    }

    public function setRatio($ratio)
    {
        $this->ratio = $ratio;
    }

    public function getProduct()
    {
        return $this->product;
    }
}
