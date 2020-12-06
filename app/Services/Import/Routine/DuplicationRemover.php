<?php
declare(strict_types=1);
/**
 * DuplicationRemover.php
 * Copyright (c) 2020 kontakt@piotrpodbielski.pl
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

namespace App\Services\Import\Routine;


use App\Exceptions\ImportException;
use App\Request\GetTransactionsByDateRequest;
use App\Services\CSV\Configuration\Configuration;
use App\Services\Import\Support\ProgressInformation;
use App\Support\Token;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException as GrumpyApiHttpException;
use GrumpyDictator\FFIIIApiSupport\Model\TransactionGroup;
use App\Response\GetTransactionsResponse;
use Log;

/**
 * Class DuplicationRemover
 */
class DuplicationRemover
{
    use ProgressInformation;

    private Configuration $configuration;

    /**
     * DuplicationRemover constructor.
     *
     * @param Configuration $configuration
     */
    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * @param array $lines
     *
     * @return array
     */
    public function processPseudo(array $lines): array
    {
        Log::debug(sprintf('Now in %s', __METHOD__));

        Log::debug('Now checking start and end date');
        $dates = array();
        foreach ($lines as $line) {
            foreach ($line['transactions'] as $transaction) {
                $dates[] = $transaction['date'];
            }
        }

        if (count($dates) > 1) {
            usort($dates, [$this, 'sortDates']);

            $start = explode(" ", $dates[0])[0];
            $end = explode(" ", $dates[count($dates) - 1])[0];
        }
        else {
            $start = explode(" ", $dates[0])[0];
            $end = $start;
        }
        Log::debug(sprintf('Start and end dates: "%s", "%s"', $start, $end));

        Log::debug('Now fetching transactions between start and end date');
        $transactions = $this->listTransactionsByDates($start, $end);
        Log::debug(sprintf('Found %d transactions between start and end dates: "%s", "%s"', count($transactions), $start, $end));

        Log::debug('Now mapping found transactions to their External ID');
        $externalIds = array_column($transactions, 'externalId');
        Log::debug(sprintf('Following External IDs found "%s"', json_encode($externalIds)));

        $count = count($lines);
        $processed = [];
        Log::info(sprintf('Deduplicating %d lines.', $count));
        /** @var array $line */
        foreach ($lines as $line) {
            $line['transactions'] = array_filter($line['transactions'],
                function($transaction) use($externalIds) {
                    return $this->notExists($transaction, $externalIds);
                });


            if (count($line['transactions']) > 0) {
                $processed[] = $line;
            }
        }
        Log::info(sprintf('Done deduplicating %d lines (%d skipped).', $count, $count - count($processed)));

        return $processed;
    }

    public function sortDates($a, $b): int
    {
        return (strtotime($a) < strtotime($b)) ? -1 : 1;
    }

    /**
     * @param array $transaction
     *
     * @param array $externalIds
     *
     * @return bool
     */
    private function notExists(array $transaction, array $externalIds): bool
    {
        Log::debug(sprintf('Now in %s', __METHOD__));

        if (isset($transaction['external_id']) and in_array($transaction['external_id'], $externalIds)) {
            return false;
        }

        return true;
    }


    /**
     * @param string $start
     *
     * @param string $end
     *
     * @return array
     * @throws ImportException
     */
    private function listTransactionsByDates(string $start, string $end): array
    {
        Log::debug(sprintf('Going to list transaction with date from "%s" to "%s"', $start, $end));
        $url = Token::getURL();
        $token = Token::getAccessToken();
        $request = new GetTransactionsByDateRequest($url, $token);
        $request->setVerify(config('csv_importer.connection.verify'));
        $request->setTimeOut(config('csv_importer.connection.timeout'));
        $request->setStart($start);
        $request->setEnd($end);

        /** @var GetTransactionsResponse $response */
        try {
            $response = $request->get();
        } catch (GrumpyApiHttpException $e) {
            throw new ImportException($e->getMessage());
        }

        $transactions = [];
        if ($response->count() > 0) {
            while ($response->valid()) {
                /** @var TransactionGroup $transactionGroup */
                $transactionGroup = $response->current();
                $response->next();

                $transactions = array_merge($transactions, $transactionGroup->transactions);
            }
        }

        return $transactions;
    }
}
