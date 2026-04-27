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

        $type = (string) $request->input('type');
        $amount = (float) $request->input('amount');

        if (!in_array($type, PaymentType::ALL, true)) {
            $this->fail('Invalid payment type.', 400, 'invalid_type');
        }

        if ($amount <= 0) {
            $this->fail('Invalid amount.', 400, 'invalid_amount');
        }

        // Check if there is already a paid payment for this type
        $paidPayment = Payment::where('research_id', $researchId)
            ->where('type', $type)
            ->where('status', PaymentStatus::PAID)
            ->first();

        if ($paidPayment) {
            $this->fail('Payment for this stage has already been completed.', 400, 'already_paid');
        }

        $payment = Payment::create([
            'research_id' => $researchId,
            'amount'      => $amount,
            'currency'    => 'EGP',
            'type'        => $type,
            'status'      => PaymentStatus::PENDING,
            'gateway'     => 'paymob',
        ]);

        $merchantRefNum = 'CERTARA-' . $payment->id . '-' . time();
        
        $payLink = \App\Services\KashierService::generatePaymentLink([
            'amount'       => $amount,
            'reference_id' => $merchantRefNum,
            'email'        => $research->student->email ?? 'student@example.com',
            'phone_number' => $research->student->phone ?? '01000000000',
            'full_name'    => $research->student->name ?? 'Student ' . $research->student_id,
            'description'  => "Research Payment - {$type} - {$research->serial_number}",
        ]);

        if (!$payLink) {
            $payment->delete();
            $this->fail('فشل إنشاء رابط الدفع. يرجى المحاولة لاحقاً', 500, 'gateway_error');
        }

        $payment->update([
            'gateway_ref'  => $merchantRefNum,
            'checkout_url' => $payLink,
        ]);

        $this->ok([
            'payment_id'   => $payment->id,
            'checkout_url' => $payLink,
            'amount'       => $amount,
            'status'       => $payment->status,
        ]);
    }

    public function callback(Request $request): never
    {
        $data = $request->input(); // JSON body
        $hmac = $request->query('hmac');
        Logger::info('Paymob Callback Received', ['data' => $data, 'hmac' => $hmac]);

        if (!\App\Services\KashierService::verifyCallback($data, $hmac === null ? null : (string)$hmac)) {
            Logger::error('Paymob Callback Signature Mismatch');
            $this->fail('Invalid signature', 400, 'auth_error');
        }

        $merchantRefNum = (string) ($data['obj']['order']['merchant_order_id'] ?? '');
        $payment = Payment::where('gateway_ref', $merchantRefNum)->first();

        if (!$payment) {
            Logger::error('Payment record not found for callback', ['merchantRefNum' => $merchantRefNum]);
            $this->fail('Payment not found', 404, 'not_found');
        }

        if ($payment->status === PaymentStatus::PAID) {
            $this->ok(['status' => 'already_processed']);
        }

        $success = filter_var($data['obj']['success'] ?? false, FILTER_VALIDATE_BOOL);
        $pending = filter_var($data['obj']['pending'] ?? false, FILTER_VALIDATE_BOOL);
        
        if ($success) {
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
            
            Logger::info('Payment confirmed via callback', ['payment_id' => $payment->id, 'research_id' => $research->id]);
        } elseif (!$pending) {
            $payment->update(['status' => PaymentStatus::FAILED]);
            Logger::warning('Payment failed via callback', ['payment_id' => $payment->id]);
        }

        $this->ok(['status' => 'processed']);
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
}
