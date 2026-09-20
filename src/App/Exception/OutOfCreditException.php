<?php

namespace App\Exception;

use Throwable;

class OutOfCreditException extends \Exception
{
    public function __construct(float $newCredit, float $creditLimit, ?Throwable $previous = null)
    {
        parent::__construct('Krediit on otsas, su limiit on ' . $creditLimit . '. Sinu uus krediit oleks olnud ' . $newCredit, 0, $previous);
    }
}
