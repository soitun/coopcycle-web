<?php

namespace AppBundle\Api\Dto;

use ApiPlatform\Core\Annotation\ApiResource;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ApiResource(
 *   collectionOperations={
 *     "post"={
 *       "path"="/me/remote_push/ios",
 *       "swagger_context"={
 *         "summary": "Creates a RemotePushToken resource."
 *       }
 *     },
 *   },
 *   itemOperations={},
 * )
 */
final class CreateRemotePushIosRequest
{
    /**
     * @Assert\NotBlank
     */
    public $token;
}
