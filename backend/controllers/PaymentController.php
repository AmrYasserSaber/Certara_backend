<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Models\Research;
use App\Models\Payment;
use App\Enums\ResearchStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\SampleSize;
use App\Helpers\NotificationService;
use App\Enums\NotificationType;

final class PaymentController extends Controller
{
    public function store(Request $request): never
    {
        $researchId = (int) $request->param('id');
        $research = Research::find($researchId);

        if (!$research) {
            $this->fail('البحث غير موجود.', 404, 'not_found');
        }

        $paymentType = null;
        $amount = 0.0;

        if ($research->status === ResearchStatus::AWAITING_PAYMENT_1) {
            $paymentType = PaymentType::FIRST;
            $amount = 500.00; // Fixed fee for application
        } elseif ($research->status === ResearchStatus::AWAITING_PAYMENT_2) {
            $paymentType = PaymentType::SECOND;
            $sampleSize = SampleSize::findByResearch($researchId);
            if (!$sampleSize) {
                $this->fail('بيانات حجم العينة غير موجودة لهذا البحث.', 400, 'bad_request');
            }
            $amount = (float) $sampleSize['fee_amount'];
        } else {
            $this->fail('البحث ليس في حالة تتطلب الدفع.', 400, 'invalid_status');
        }

        // Check if there is already a pending or paid payment for this type
        $existingPayment = Payment::where('research_id', $researchId)
            ->where('type', $paymentType)
            ->whereIn('status', [PaymentStatus::PENDING, PaymentStatus::PAID])
            ->first();

        if ($existingPayment && $existingPayment->status === PaymentStatus::PAID) {
            $this->fail('تم بالفعل استكمال الدفع لهذه المرحلة.', 400, 'already_paid');
        }

        $payment = $existingPayment ?: Payment::create([
            'research_id' => $researchId,
            'amount'      => $amount,
            'currency'    => 'EGP',
            'type'        => $paymentType,
            'status'      => PaymentStatus::PENDING,
            'gateway'     => 'fawry',
        ]);

        // Real Fawry B2B API Integration (Staging)
        $merchantCode = env('FAWRY_MERCH_CODE', '1tSa6uxz2nTwlaAmt38enA==');
        $secureKey = env('FAWRY_SECURE_KEY', '259af31fc2f74453b3a55739b21ae9ef');
        $merchantRefNum = 'CERTARA-' . $payment->id . '-' . time();
        $customerProfileId = (string) $research->student_id;
        $paymentMethod = 'PayAtFawry';
        $amountStr = number_format($amount, 2, '.', '');
        
        // signature = hash('sha256', merchantCode + merchantRefNum + customerProfileId + paymentMethod + amount + merchant_sec_key)
        $signatureStr = $merchantCode . $merchantRefNum . $customerProfileId . $paymentMethod . $amountStr . $secureKey;
        $signature = hash('sha256', $signatureStr);

        $payload = [
            'merchantCode' => $merchantCode,
            'merchantRefNum' => $merchantRefNum,
            'customerName' => $research->student->name ?? 'Student ' . $research->student_id,
            'customerMobile' => $research->student->phone ?? '01000000000',
            'customerEmail' => $research->student->email ?? 'student@example.com',
            'customerProfileId' => $customerProfileId,
            'amount' => $amountStr,
            'paymentExpiry' => (time() + 48 * 3600) * 1000,
            'currencyCode' => 'EGP',
            'language' => 'ar-eg',
            'chargeItems' => [
                [
                    'itemId' => $paymentType,
                    'description' => 'IRB Research Payment - ' . $paymentType,
                    'price' => $amountStr,
                    'quantity' => 1
                ]
            ],
            'signature' => $signature,
            'paymentMethod' => $paymentMethod,
            'description' => 'Research ID ' . $research->id . ' Payment'
        ];

        $ch = curl_init('https://atfawry.fawrystaging.com/ECommerceWeb/Fawry/payments/charge');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$response) {
            $this->fail('فشل التواصل مع خوادم الدفع. يرجى المحاولة لاحقاً', 500, 'gateway_error');
        }

        $fawryData = json_decode($response, true);
        
        if ($httpCode >= 400 || !isset($fawryData['referenceNumber'])) {
            \App\Core\Logger::error('Fawry API Error', ['payload' => $payload, 'response' => $fawryData]);
            $this->fail('فشل التواصل مع خوادم الدفع. يرجى المحاولة لاحقاً', 500, 'gateway_error');
        }

        $payment->update([
            'gateway_ref' => $fawryData['referenceNumber']
        ]);

        $this->ok([
            'payment_id'   => $payment->id,
            'amount'       => $payment->amount,
            'fawry_reference' => $fawryData['referenceNumber'],
            'status'       => $payment->status,
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

    /**
     * Webhook/Callback for payment confirmation (Simulated)
     */
    public function finalize(Request $request): never
    {
        $id = (int) $request->param('payment_id');
        $payment = Payment::find($id);

        if (!$payment) {
            $this->fail('سجل الدفع غير موجود.', 404, 'not_found');
        }

        if ($payment->status === PaymentStatus::PAID) {
            $this->ok($payment);
        }

        $payment->update([
            'status'  => PaymentStatus::PAID,
            'paid_at' => now(),
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

        $this->ok($payment);
    }
}
