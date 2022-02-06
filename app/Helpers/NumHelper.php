<?php

namespace App\Helpers;


class NumHelper {
    /**
     *
     * @param float $number
     * @param number $precision
     * @return float
     */
    public static function numberFormatWithRightCeil($number, $precision)
    {
        $numberParts = explode('.', $number);

        if(isset($numberParts[1]))
        {
            $precisionPartOrigin = $numberParts[1];

            $precisionPart = substr($precisionPartOrigin, 0, $precision);

            if(strlen($precisionPartOrigin) > strlen($precisionPart))
            {
                $precisionPart = (int)$precisionPart + 1;
            }

            $numberString = $numberParts[0].'.'.$precisionPart;
        }
        else
        {
            $numberString = $number;
        }

        return (float) $numberString;
    }
}