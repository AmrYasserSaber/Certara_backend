<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Logger;
use App\Models\Research;
use App\Models\Payment;
use App\Enums\ResearchStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\SampleSize;
use App\Helpers\NotificationService;
use App\Enums\NotificationType;
use App\Services\PaymobService;

final class PaymentController extends Controller
{
    public function store(Request $request): never
    {
        $researchId = (int) $request->param('id');
        $research = Research::find($researchId);

        if (!$research) {
            $this->fail('البحث غير موجود.', 404, 'not_found');
        }

        $type = (string) $request->input('type');

        if (!in_array($type, PaymentType::ALL, true)) {
            $this->fail('طريقة دفع غير صالحة.', 400, 'invalid_type');
        }

        if ($type === PaymentType::SECOND) {
            $isFirstPaid = Payment::where('research_id', $researchId)
                ->where('type', PaymentType::FIRST)
                ->where('status', PaymentStatus::PAID)
                ->exists();
            if (!$isFirstPaid) {
                $this->fail('يجب إتمام الدفعة الأولى قبل دفع الدفعة الثانية.', 409, 'invalid_state');
            }
        }

        $payment = Payment::where('research_id', $researchId)
            ->where('type', $type)
            ->where('status', PaymentStatus::PENDING)
            ->orderByDesc('id')
            ->first();

        if (!$payment) {
            $latest = Payment::where('research_id', $researchId)
                ->where('type', $type)
                ->orderByDesc('id')
                ->first();

            if (!$latest) {
                $this->fail('
لم يتم تجهيز رابط الدفع من قبل الإدارة بعد.', 409, 'invalid_state');
           }

            if ($latest->status === PaymentStatus::PAID) {
                $this->fail('هذه العملية تمت مسبقا.', 409, 'already_paid');
            }

            $this->fail('الدفع غير قابل للتنفيذ. يرجى الاتصال بالمسؤول لتجديده.', 409, 'invalid_state');
        }

        $merchantRefNum = (string) ($payment->gateway_ref ?? '');
        if ($merchantRefNum === '') {
            $merchantRefNum = PaymobService::buildMerchantReference((int) $payment->id);
            $payment->update(['gateway_ref' => $merchantRefNum, 'gateway' => 'paymob']);
        }

        $payLink = (string) ($payment->checkout_url ?? '');
        if ($payLink === '') {
            try {
                $payLink = PaymobService::createQuickLink([
                    'amount' => (float) $payment->amount,
                    'currency' => (string) ($payment->currency ?? 'EGP'),
                    'merchant_reference' => $merchantRefNum,
                    'description' => "Research Payment - {$type} - {$research->serial_number}",
                    'customer' => [
                        'email' => (string) ($research->student->email ?? 'student@example.com'),
                        'first_name' => (string) ($research->student->name ?? 'Student'),
                        'last_name' => 'N/A',
                        'phone_number' => (string) ($research->student->phone ?? '01000000000'),
                        'country' => 'EG',
                        'city' => 'NA',
                        'state' => 'NA',
                        'street' => 'NA',
                        'postal_code' => 'NA',
                    ],
                ]);
            } catch (\Throwable $err) {
                Logger::error('Paymob link generation failed', ['error_message' => $err->getMessage()]);
                $this->fail('فشل إنشاء رابط الدفع. يرجى المحاولة لاحقاً', 500, 'gateway_error');
            }

            $payment->update([
                'gateway' => 'paymob',
                'checkout_url' => $payLink,
            ]);
        }

        $this->ok([
            'payment_id'   => $payment->id,
            'checkout_url' => $payLink,
            'amount'       => (float) $payment->amount,
            'status'       => $payment->status,
        ]);
    }

    public function callback(Request $request): never
    {
        $hmac = (string) $request->query('hmac', '');
        $body = $request->input();

        Logger::info('Paymob callback received', [
            'path' => $request->path(),
            'method' => $request->method(),
        ]);

        if (!PaymobService::verifyTransactionHmac($body, $hmac)) {
            Logger::error('Paymob callback HMAC mismatch');
            $this->fail('Invalid signature', 400, 'auth_error');
        }

        $parsed = PaymobService::parseTransactionCallback($body);
        $obj = $body['obj'] ?? null;
        $order = is_array($obj) ? ($obj['order'] ?? null) : null;
        $orderData = is_array($order) ? ($order['data'] ?? null) : null;
        $merchantReferenceCandidates = [
            'obj.order.data.merchant_reference' => is_array($orderData) ? (string) ($orderData['merchant_reference'] ?? '') : '',
            'obj.order.merchant_order_id' => is_array($order) ? (string) ($order['merchant_order_id'] ?? '') : '',
            'obj.merchant_order_id' => is_array($obj) ? (string) ($obj['merchant_order_id'] ?? '') : '',
            'obj.special_reference' => is_array($obj) ? (string) ($obj['special_reference'] ?? '') : '',
            'obj.order.special_reference' => is_array($order) ? (string) ($order['special_reference'] ?? '') : '',
            'obj.data.merchant_reference' => is_array($obj) && is_array(($obj['data'] ?? null)) ? (string) (($obj['data']['merchant_reference'] ?? '') ?: '') : '',
        ];
        $merchantReference = '';
        foreach ($merchantReferenceCandidates as $keyPath => $value) {
            if ($value !== '') {
                $merchantReference = $value;
                break;
            }
        }

        $paymentId = PaymobService::parsePaymentIdFromReference($merchantReference);

        Logger::info('Paymob callback received', [
            'payment_id' => $paymentId,
            'merchant_reference' => $merchantReference,
            'merchant_reference_candidates' => $merchantReferenceCandidates,
            'paymob_parsed_keys' => $parsed,
            'paymob_ids' => [
                'obj.transaction_id' => is_array($obj) ? ($obj['id'] ?? null) : null,
                'obj.order_id' => is_array($order) ? ($order['id'] ?? null) : null,
                'obj.integration_id' => is_array($obj) ? ($obj['integration_id'] ?? null) : null,
                'obj.amount_cents' => is_array($obj) ? ($obj['amount_cents'] ?? null) : null,
                'obj.success' => is_array($obj) ? ($obj['success'] ?? null) : null,
            ],
            'parsed' => $parsed,
        ]);

        if ($merchantReference === '') {
            Logger::error('Paymob callback missing or invalid merchant reference', [
                'details' => [
                    'merchant_reference' => null,
                    'merchant_reference_candidates' => $merchantReferenceCandidates,
                ],
            ]);
            $this->fail('Invalid merchant reference', 400, 'validation_error');
        }

        $payment = null;
        if ($paymentId > 0) {
            $payment = Payment::find($paymentId);
        }
        if ($payment === null) {
            $payment = Payment::where('gateway_ref', $merchantReference)->orderByDesc('id')->first();
        }
        Logger::info('Payment found', [
            'payment' => $payment,
        ]);

        if ($payment === null) {
            $orderId = (int) ($parsed['order_id'] ?? 0);
            if ($orderId > 0) {
                $payment = Payment::where('paymob_order_id', $orderId)->first();
                Logger::info('Payment found by order id', [
                    'payment' => $payment,
                ]);
            }
        }

        if ($payment === null) {
            Logger::error('Payment not found for Paymob callback', [
                'details' => [
                    'merchant_reference' => $merchantReference !== '' ? $merchantReference : null,
                    'paymob_order_id' => $parsed['order_id'] ?? null,
                    'paymob_transaction_id' => $parsed['transaction_id'] ?? null,
                ],
            ]);
            $this->fail('Payment not found', 404, 'not_found');
        }

        $gatewayReference = (string) ($payment->gateway_ref ?? '');
        if ($gatewayReference === '') {
            Logger::error('Paymob callback payment has no gateway_ref', [
                'details' => [
                    'payment_id' => $payment->id,
                    'merchant_reference' => $merchantReference,
                ],
            ]);
            $this->fail('Payment gateway reference not set', 409, 'invalid_state');
        }

        if ($gatewayReference !== $merchantReference) {
            Logger::error('Paymob merchant reference mismatch for payment', [
                'details' => [
                    'payment_id' => $payment->id,
                    'gateway_ref' => $gatewayReference,
                    'merchant_reference' => $merchantReference,
                ],
            ]);
            $this->fail('Payment reference mismatch', 409, 'validation_error');
        }

        if ($payment->status === PaymentStatus::PAID) {
            $this->ok(['status' => 'already_processed']);
        }

        $expectedAmountCents = (int) round(((float) $payment->amount) * 100);
        $reportedAmountCents = (int) ($parsed['amount_cents'] ?? 0);

        $payment->update([
            'paymob_order_id' => (int) ($parsed['order_id'] ?? 0) ?: null,
            'paymob_transaction_id' => (int) ($parsed['transaction_id'] ?? 0) ?: null,
            'paymob_integration_id' => (int) ($parsed['integration_id'] ?? 0) ?: null,
            'paymob_method' => $parsed['method'] ?? null,
            'amount_cents_reported' => $reportedAmountCents > 0 ? $reportedAmountCents : null,
            'paymob_callback_payload' => $body,
        ]);

        if (($parsed['success'] ?? false) !== true) {
            $payment->update(['status' => PaymentStatus::FAILED]);
            Logger::warning('Paymob payment declined', ['details' => ['payment_id' => $payment->id]]);
            $this->ok(['status' => 'processed']);
        }

        if ($reportedAmountCents !== $expectedAmountCents) {
            Logger::warning('Paymob amount mismatch', [
                'details' => [
                    'payment_id' => $payment->id,
                    'expected_amount_cents' => $expectedAmountCents,
                    'reported_amount_cents' => $reportedAmountCents,
                ],
            ]);
            $this->ok(['status' => 'mismatch']);
        }

        $payment->update([
            'status' => PaymentStatus::PAID,
            'paid_at' => date('Y-m-d H:i:s'),
        ]);

        $research = $payment->research;
        if ($payment->type === PaymentType::FIRST) {
            $research->update(['status' => ResearchStatus::AWAITING_SAMPLE_SIZE]);
        } elseif ($payment->type === PaymentType::SECOND) {
            $research->update(['status' => ResearchStatus::IN_REVIEW]);
        }

        NotificationService::notify(
            $research->student_id,
            NotificationType::PAYMENT_CONFIRMED,
            'تم تأكيد الدفع',
            "تم استلام دفعة ({$payment->type}) للبحث: {$research->title}",
            $research->id
        );

        Logger::info('Payment confirmed via Paymob callback', ['details' => ['payment_id' => $payment->id]]);

        $this->ok(['status' => 'processed']);
    }

    /**
     * Paymob Transaction Response callback (GET redirect).
     *
     * Webhooks (POST) are the source of truth; this endpoint exists to prevent
     * user-facing 404s after payment and to provide a simple UX confirmation.
     */
    public function responseCallback(Request $request): never
    {
        $success = $request->query('success');
        $orderId = $request->query('order') ?? $request->query('order_id');
        $transactionId = $request->query('id');
        $this->ok([
            'success' => $success,
            'order_id' => $orderId,
            'transaction_id' => $transactionId,
        ]);
    }


    public function receipt(Request $request): never
    {
        $researchId = (int) $request->param('id');
        $research = Research::find($researchId);

        if (!$research) {
            $this->fail('البحث غير موجود.', 404, 'not_found');
        }

        $payments = Payment::where('research_id', $researchId)
            ->where('status', PaymentStatus::PAID)
            ->get();

        if ($payments->isEmpty()) {
            $this->fail('لم يتم العثور على دفعات مكتملة لهذا البحث.', 404, 'not_found');
        }

        $sampleSize = SampleSize::findByResearch($researchId);

        $this->ok([
            'research' => [
                'id'            => $research->id,
                'title'         => $research->title,
                'serial_number' => $research->serial_number,
                'student'       => $research->student->name ?? 'N/A',
            ],
            'payments' => $payments->map(fn($p) => [
                'id'          => $p->id,
                'type'        => $p->type,
                'amount'      => $p->amount,
                'currency'    => $p->currency,
                'gateway_ref' => $p->gateway_ref,
                'paid_at'     => $p->paid_at ? $p->paid_at->toDateTimeString() : null,
            ]),
            'sample_size' => $sampleSize ? [
                'calculated_size' => $sampleSize['calculated_size'],
                'fee_amount'      => $sampleSize['fee_amount'],
            ] : null,
            'generated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
