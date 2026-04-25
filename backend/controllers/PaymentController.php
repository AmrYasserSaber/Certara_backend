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
            $this->fail('Research not found.', 404, 'not_found');
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
                $this->fail('Sample size data not found for this research.', 400, 'bad_request');
            }
            $amount = (float) $sampleSize['fee_amount'];
        } else {
            $this->fail('Research is not in a state that requires payment.', 400, 'invalid_status');
        }

        // Check if there is already a pending or paid payment for this type
        $existingPayment = Payment::where('research_id', $researchId)
            ->where('type', $paymentType)
            ->whereIn('status', [PaymentStatus::PENDING, PaymentStatus::PAID])
            ->first();

        if ($existingPayment && $existingPayment->status === PaymentStatus::PAID) {
            $this->fail('Payment for this stage has already been completed.', 400, 'already_paid');
        }

        $payment = $existingPayment ?: Payment::create([
            'research_id' => $researchId,
            'amount'      => $amount,
            'currency'    => 'EGP',
            'type'        => $paymentType,
            'status'      => PaymentStatus::PENDING,
            'gateway'     => 'fawry',
        ]);

        // Mock Fawry Integration
        $merchantCode = '1tSa67zS02nCOeb0mxp93gu';
        $merchantRefNum = 'REF-' . $payment->id . '-' . time();
        $payment->update(['gateway_ref' => $merchantRefNum]);

        // In a real integration, we would call Fawry API to get a checkout URL or charge reference
        // For now, we mock the redirection URL
        $checkoutUrl = "https://developer.fawrystaging.com/mock-pay?ref={$merchantRefNum}&amount={$amount}";
        $payment->update(['checkout_url' => $checkoutUrl]);

        // For demo purposes, we provide a way to "simulate" success immediately if needed
        // but here we just return the URL
        
        $this->ok([
            'payment_id'   => $payment->id,
            'amount'       => $payment->amount,
            'checkout_url' => $checkoutUrl,
            'status'       => $payment->status,
        ]);
    }

    public function receipt(Request $request): never
    {
        $researchId = (int) $request->param('id');
        $research = Research::find($researchId);

        if (!$research) {
            $this->fail('Research not found.', 404, 'not_found');
        }

        $payments = Payment::where('research_id', $researchId)
            ->where('status', PaymentStatus::PAID)
            ->get();

        if ($payments->isEmpty()) {
            $this->fail('No completed payments found for this research.', 404, 'not_found');
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
            $this->fail('Payment record not found.', 404, 'not_found');
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
