<?php

namespace App\Services;

use Midtrans\Config;
use Midtrans\CoreApi;
use Midtrans\Transaction;

class MidtransService
{
    public function __construct()
    {
        Config::$serverKey =
            config('midtrans.server_key');

        Config::$isProduction =
            config('midtrans.is_production');

        Config::$isSanitized = true;

        Config::$is3ds = true;
    }

    /**
     * @return mixed
     */
    public function createQrisTransaction(
        array $data
    ) {

        return CoreApi::charge([
            "payment_type" => "qris",

            "transaction_details" => [
                "order_id" =>
                $data['order_id'],

                "gross_amount" =>
                $data['gross_amount'],
            ],
        ]);
    }

    /**
     * @return mixed
     */
    public function createBankTransfer(
        array $data,
        string $bank
    ) {

        return CoreApi::charge([
            "payment_type" =>
            "bank_transfer",

            "transaction_details" => [
                "order_id" =>
                $data['order_id'],

                "gross_amount" =>
                $data['gross_amount'],
            ],

            "bank_transfer" => [
                "bank" => $bank,
            ],
        ]);
    }

    /**
     * @return mixed
     */
    public function createMandiriBill(array $data)
    {
        return CoreApi::charge([
            'payment_type' => 'echannel',
            'transaction_details' => [
                'order_id' => $data['order_id'],
                'gross_amount' => $data['gross_amount'],
            ],
            'echannel' => [
                'bill_info1' => 'Pembayaran',
                'bill_info2' => 'Lucky Pet Market',
            ],
        ]);
    }

    /**
     * @return mixed
     */
    public function getTransactionStatus(
        string $orderId
    ) {

        return Transaction::status(
            $orderId
        );
    }
}
