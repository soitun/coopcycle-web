<?php

namespace AppBundle\DataType;

class NumRange
{
    /**
     * Don't use PHP INF constant to avoid error:
     * "Inf and NaN cannot be JSON encoded"
     * https://github.com/api-platform/core/pull/2386
     */
    const UPPER_INF = 'INF';

    private $lower = 0;

    private $upper = 1;

    /**
     * @return mixed
     */
    public function getLower()
    {
        return $this->lower;
    }

    /**
     * @param mixed $lower
     *
     * @return self
     */
    public function setLower($lower)
    {
        $this->lower = $lower;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getUpper()
    {
        return $this->upper;
    }

    /**
     * @param mixed $upper
     *
     * @return self
     */
    public function setUpper($upper)
    {
        $this->upper = $upper;

        return $this;
    }

    public function isUpperInfinite()
    {
        return $this->upper === self::UPPER_INF;
    }
}
