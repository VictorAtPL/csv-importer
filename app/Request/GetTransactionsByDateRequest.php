<?php

declare(strict_types=1);
/**
 * GetAccountRequest.php
 * Copyright (c) 2020 james@firefly-iii.org.
 *
 * This file is part of the Firefly III CSV importer
 * (https://github.com/firefly-iii/csv-importer).
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace App\Request;

use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiException;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;
use GrumpyDictator\FFIIIApiSupport\Request\Request;
use App\Response\GetTransactionsResponse;
use GrumpyDictator\FFIIIApiSupport\Response\Response;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Class GetTransactionsByDateRequest.
 *
 * Returns a set of transactions with date between specified range.
 */
class GetTransactionsByDateRequest extends Request
{
    /** @var string */
    private $start;
    /** @var string */
    private $end;

    /**
     * GetTransactionsByDateRequest constructor.
     *
     * @param string $url
     * @param string $token
     */
    public function __construct(string $url, string $token)
    {
        $this->setBase($url);
        $this->setToken($token);
        $this->setUri('transactions');
    }

    /**
     * {@inheritdoc}
     */
    public function put(): Response
    {
        // TODO: Implement put() method.
    }

    /**
     * @throws ApiHttpException
     * @return Response
     */
    public function get(): Response
    {
        try {
            $data = $this->authenticatedGet();
        } catch (ApiException | GuzzleException $e) {
            throw new ApiHttpException($e->getMessage());
        }

        return new GetTransactionsResponse($data['data']);
    }

    /**
     * @return Response
     */
    public function post(): Response
    {
        // TODO: Implement post() method.
    }

    /**
     * @param string $start
     */
    public function setStart(string $start): void
    {
        $this->start = $start;
        $this->setUri(sprintf('transactions?start=%s&end=%s', $start, $this->end));
    }


    /**
     * @param string $end
     */
    public function setEnd(string $end): void
    {
        $this->end = $end;
        $this->setUri(sprintf('transactions?start=%s&end=%s', $this->start, $end));
    }
}
