<?php

namespace Parsisolution\Gateway\Providers\Zarinpal;

use Parsisolution\Gateway\Exceptions\TransactionException;


class SabaPayException extends TransactionException
{

    /**
     * returns an associative array of `code` => `message`
     *
     * @return array
     */
    protected function getErrors()
    {
        return [
            100 => '100',
            101 => '101',
            102 => '102',
            103 => '103',
            301 => '301',
            200 => '200',
            201 => '201',
            202 => '202',
        ];
    }
}
