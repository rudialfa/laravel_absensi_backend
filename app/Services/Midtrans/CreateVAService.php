<?php

namespace App\Services\Midtrans;

use App\Models\SubscriptionInvoice;
use App\Models\VaPayment;
use Midtrans\CoreApi;

class CreateVAService extends Midtrans
{
    protected SubscriptionInvoice $invoice;
    protected string $bank; // 'bca' | 'mandiri'

    public function __construct(SubscriptionInvoice $invoice, string $bank)
    {
        parent::__construct();

        $this->invoice = $invoice;
        $this->bank    = $bank;
    }

    /**
     * Buat transaksi VA di Midtrans, lalu simpan/refresh baris di table va_payments.
     */
    public function getVA(): VaPayment
    {
        $params = $this->_buildParams();

        // Charge ke Midtrans Core API
        $response = CoreApi::charge($params);

        return $this->_storeVaPayment($response);
    }

    protected function _buildParams(): array
    {
        $company = $this->invoice->company;

        $customerDetails = [
            'first_name' => $company->name,
            'email'      => $company->email,
        ];

        $base = [
            // order_id Midtrans = invoice_number (harus unik)
            'transaction_details' => [
                'order_id'     => $this->invoice->invoice_number,
                'gross_amount' => (int) round($this->invoice->total_amount),
            ],
            'item_details' => [[
                'id'       => 'SUB-' . $this->invoice->plan_id,
                'price'    => (int) round($this->invoice->total_amount),
                'quantity' => 1,
                'name'     => 'Langganan ' . ($this->invoice->plan->name ?? '-'),
            ]],
            'customer_details' => $customerDetails,
        ];

        if ($this->bank === 'mandiri') {
            // Mandiri Bill Payment di Midtrans pakai payment_type "echannel"
            // (bukan bank_transfer). bill_key otomatis di-generate Midtrans.
            return array_merge($base, [
                'payment_type' => 'echannel',
                'echannel'     => [
                    'bill_info1' => 'Payment For:',
                    'bill_info2' => 'Langganan ' . ($this->invoice->plan->name ?? '-'),
                ],
            ]);
        }

        // BCA / BNI / BRI / Permata pakai payment_type "bank_transfer"
        return array_merge($base, [
            'payment_type'  => 'bank_transfer',
            'bank_transfer' => [
                'bank' => $this->bank,
            ],
        ]);
    }

    protected function _storeVaPayment(object $response): VaPayment
    {
        $vaNumber = null;
        $billKey  = null;
        $bizCode  = null;

        if ($this->bank === 'mandiri') {
            $billKey  = $response->bill_key ?? null;
            $bizCode  = $response->biller_code ?? null;
            $vaNumber = $billKey; // ditampilkan sebagai "nomor VA" ke Flutter
        } else {
            $vaNumber = $response->va_numbers[0]->va_number ?? null;
        }

        return VaPayment::updateOrCreate(
            ['invoice_id' => $this->invoice->id],
            [
                'company_id'         => $this->invoice->company_id,
                'bank'               => $this->bank,
                'va_number'          => $vaNumber,
                'va_name'            => $this->invoice->company->name,
                'amount'             => $this->invoice->total_amount,
                'status'             => 'pending',
                'partner_service_id' => null,
                'customer_no'        => null,
                'inquiry_request_id' => null,
                'payment_request_id' => $bizCode,
                'bank_reference_no'  => $response->transaction_id ?? null,
                'created_va_at'      => now(),
                'expired_at'         => $this->invoice->due_at,
                'bank_response'      => json_encode($response),
            ]
        );
    }
}
