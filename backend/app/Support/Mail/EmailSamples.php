<?php

namespace App\Support\Mail;

use InvalidArgumentException;

/**
 * Sample view data for every built email template — what `mail:preview`
 * sends to Mailtrap and what the catalog tests render. For the emails the
 * owner mocked (docs/global/email-kit/) it is the kit's own sample data, so a
 * preview lines up with the mockup; values the kit leaves in [square
 * brackets] stay bracketed here, because they have no real source to sample.
 *
 * These are presentation-ready values (formatted strings), the same shape
 * each mailable passes to its template.
 */
final class EmailSamples
{
    /** @return list<string> the types that have a template and sample data */
    public static function types(): array
    {
        return array_keys(self::samples());
    }

    /** @return array<string, mixed> */
    public static function for(string $type): array
    {
        $samples = self::samples();

        if (! isset($samples[$type])) {
            throw new InvalidArgumentException("No template built yet for email type [{$type}].");
        }

        return ($samples[$type])();
    }

    /** @return array<string, callable(): array<string, mixed>> */
    private static function samples(): array
    {
        return [
            // Kit: 01b-quotation.html
            'quotation.sent' => fn () => [
                'name' => 'Aisyah',
                'reference' => 'AXNQ-2026-0042',
                'total' => 'RM 6,500',
                'timeline' => '3 weeks',
                'validUntil' => '10 Nov 2026',
                'items' => [
                    ['label' => 'Business Site', 'detail' => '5 pages, enquiry forms, booking ready', 'amount' => 'RM 5,000'],
                    ['label' => 'CMS', 'detail' => null, 'amount' => 'RM 1,200'],
                    ['label' => 'SEO setup', 'detail' => null, 'amount' => 'RM 300'],
                ],
                'pdfUrl' => 'https://example.com/',
                'whatsappUrl' => 'https://wa.me/',
            ],

            // Kit: 02-inquiry-received.html (the contact variant)
            'inquiry.received' => fn () => [
                'name' => 'Aisyah',
                'variant' => 'contact',
                'partnerName' => null,
                'partnerFounder' => null,
                'rows' => [
                    ['Subject', 'Other'],
                    ['Received', '11 Oct 2026, 8:46 am'],
                ],
                'whatsappUrl' => 'https://wa.me/',
            ],

            // Kit: 03-invoice.html — the kit leaves the account name, number and
            // card/FPX link in [brackets]; real sends read DocumentMapper::BANK and
            // hide the payment-link row until a gateway exists.
            'invoice.sent' => fn () => [
                'name' => 'Aisyah',
                'invoiceNumber' => 'AXNI-2026-0031',
                'projectName' => 'Business Site',
                'amountDue' => 'RM 6,500',
                'dueDate' => '25 Oct 2026',
                'quoteRef' => 'AXNQ-2026-0042',
                'issuedDate' => '11 Oct 2026',
                'bank' => ['name' => 'OCBC Bank', 'holder' => 'Axel Nova Ventures', 'account' => '7051415701'],
                'onlinePaymentLink' => null,
                'pdfUrl' => 'https://example.com/',
                'attached' => true,
            ],

            // Kit: 06-payment-receipt.html — [receipt no] / [payment reference]
            // are real values on a send (the payment's AXNR receipt + reference).
            'payment.receipt' => fn () => [
                'name' => 'Aisyah',
                'amountPaid' => 'RM 3,250',
                'paidDate' => '11 Oct 2026',
                'methodLabel' => 'FPX',
                'receiptNumber' => 'AXNR-2026-0007',
                'paymentReference' => 'FPX-88213402',
                'invoiceNumber' => 'AXNI-2026-0031',
                'balanceLeft' => 'RM 3,250',
                'receiptUrl' => 'https://example.com/',
                'attached' => true,
            ],

            // Kit: 09-project-delivered.html — the review window and handover
            // list are entered when the order is marked Delivered (Phase 3).
            'order.delivered' => fn () => [
                'name' => 'Aisyah',
                'orderNumber' => 'AXNO-2026-0018',
                'packageName' => 'Business Site',
                'handoverItems' => ['Live website', 'CMS admin access', 'Source files'],
                'reviewWindow' => '[review window]', // no source until Phase 3 — stays bracketed
                'balance' => ['amount' => 'RM 3,250', 'invoiceNumber' => 'AXNI-2026-0032', 'dueDate' => '25 Oct 2026'],
                'invoiceUrl' => 'https://example.com/',
            ],

            // ── Existing emails, re-skinned (not mocked in the kit) ─────────
            'feedback.request' => fn () => [
                'name' => 'Aisyah',
                'variant' => 'project_label',
                'projectLabel' => 'Business Site',
                'feedbackUrl' => 'https://example.com/',
            ],

            'referral.received' => fn () => [
                'name' => 'Farid',
                'businessName' => 'Kopi Tepi Jalan Sdn Bhd',
                'tierPct' => 10,
                'cap' => 'RM 1,500',
            ],

            'referral.commission' => fn () => [
                'name' => 'Farid',
                'businessName' => 'Kopi Tepi Jalan Sdn Bhd',
                'ratePct' => 10,
                'cap' => 'RM 1,500',
                'commission' => 'RM 650',
            ],

            'partner.passcode' => fn () => [
                'name' => 'Farid',
                'setupUrl' => 'https://example.com/',
                'expiresIn' => '72 hours',
            ],

            'team.welcome' => fn () => [
                'name' => 'Danial',
                'setupUrl' => 'https://example.com/',
                'expiresIn' => '72 hours',
            ],

            'team.password_reset' => fn () => [
                'name' => 'Danial',
                'resetUrl' => 'https://example.com/',
                'expiresIn' => '60 minutes',
            ],

            'inquiry.new_alert' => fn () => [
                'origin' => 'contact',
                'subjectLabel' => 'General question',
                'name' => 'Aisyah Rahman',
                'partnerName' => 'a partner studio',
                'rows' => [
                    ['Subject', 'General question'],
                    ['Name', 'Aisyah Rahman'],
                    ['Email', 'aisyah@example.com'],
                    ['Phone', '012-345 6789'],
                    ['Submitted', '11 Oct 2026, 8:46 am'],
                ],
                'inquiryMessage' => "Hi, do you also take on maintenance work for an existing site?\nIt's built on WordPress.",
                'adminUrl' => 'https://example.com/',
            ],

            'partner.reset_alert' => fn () => [
                'partnerName' => 'Farid',
                'rows' => [
                    ['Partner', 'Farid'],
                    ['Account type', 'Referrer'],
                    ['Email', 'farid@example.com'],
                    ['Referral code', 'FARID10'],
                ],
            ],

            'team.reset_alert' => fn () => [
                'userName' => 'Danial',
                'rows' => [
                    ['Name', 'Danial'],
                    ['Email', 'danial@example.com'],
                    ['Role', 'engineer'],
                ],
                'usersUrl' => 'https://example.com/',
            ],

            // ── New emails (Phases 2–6): template + sample now, trigger later ──
            // Values whose source is decided in a later phase stay [bracketed].
            'order.confirmed' => fn () => [
                'name' => 'Aisyah',
                'orderNumber' => 'AXNO-2026-0018',
                'packageName' => 'Business Site',
                'total' => 'RM 6,500',
                'depositAmount' => 'RM 3,250',
                'depositDue' => '18 Oct 2026',
                'quotationUrl' => 'https://example.com/',
            ],

            'payment.refund' => fn () => [
                'name' => 'Aisyah',
                'orderNumber' => 'AXNO-2026-0018',
                'amount' => 'RM 1,000',
                'refundDate' => '11 Oct 2026',
                'methodLabel' => 'Bank transfer',
                'originalPayment' => 'RM 3,250 on 2 Oct 2026',
                'refundReference' => 'RFD-20261011-01',
            ],

            'order.started' => fn () => [
                'name' => 'Aisyah',
                'orderNumber' => 'AXNO-2026-0018',
                'packageName' => 'Business Site',
                'expectedDelivery' => '8 Nov 2026',
                'whatsappUrl' => 'https://wa.me/',
            ],

            'order.completed' => fn () => [
                'name' => 'Aisyah',
                'orderNumber' => 'AXNO-2026-0018',
                'packageName' => 'Business Site',
                'feedbackUrl' => 'https://example.com/',
            ],

            'order.cancelled' => fn () => [
                'name' => 'Aisyah',
                'orderNumber' => 'AXNO-2026-0018',
                'packageName' => 'Business Site',
                'cancelledDate' => '11 Oct 2026',
                'refundNote' => 'RM 3,250 refunded to your bank account',
            ],

            'invoice.due_soon' => fn () => [
                'name' => 'Aisyah',
                'invoiceNumber' => 'AXNI-2026-0031',
                'amountDue' => 'RM 3,250',
                'dueDate' => '25 Oct 2026',
                'pdfUrl' => 'https://example.com/',
                'bank' => ['name' => 'OCBC Bank', 'holder' => 'Axel Nova Ventures', 'account' => '7051415701'],
            ],

            'invoice.overdue' => fn () => [
                'name' => 'Aisyah',
                'invoiceNumber' => 'AXNI-2026-0031',
                'amountDue' => 'RM 3,250',
                'dueDate' => '25 Oct 2026',
                'daysOverdue' => 7,
                'pdfUrl' => 'https://example.com/',
                'bank' => ['name' => 'OCBC Bank', 'holder' => 'Axel Nova Ventures', 'account' => '7051415701'],
            ],

            'quotation.expiring' => fn () => [
                'name' => 'Aisyah',
                'reference' => 'AXNQ-2026-0042',
                'total' => 'RM 6,500',
                'validUntil' => '10 Nov 2026',
                'pdfUrl' => 'https://example.com/',
                'whatsappUrl' => 'https://wa.me/',
            ],

            'referral.update' => fn () => [
                'name' => 'Farid',
                'businessName' => 'Kopi Tepi Jalan Sdn Bhd',
                'status' => 'contacted',
                'statusLabel' => 'Contacted',
                'portalUrl' => 'https://example.com/',
            ],

            'partner.reset_confirm' => fn () => [
                'name' => 'Farid',
                'resetUrl' => 'https://example.com/',
                'expiresIn' => '60 minutes',
            ],

            'task.assigned' => fn () => [
                'name' => 'Danial',
                'taskTitle' => 'Build the booking form for Studio Aisyah',
                'deadline' => '18 Oct 2026',
                'priority' => 'High',
                'pay' => 'RM 400',
                'taskUrl' => 'https://example.com/',
            ],

            'payroll.payslip' => fn () => [
                'name' => 'Danial',
                'period' => 'October 2026',
                'gross' => 'RM 2,150',
                'lines' => [
                    ['label' => 'Allowance', 'amount' => 'RM 1,500'],
                    ['label' => 'Task extras', 'amount' => 'RM 400'],
                    ['label' => 'Discretionary', 'amount' => 'RM 250'],
                ],
                'paidDate' => '31 Oct 2026',
                'methodLabel' => 'Bank transfer',
                'payslipUrl' => 'https://example.com/',
            ],

            'referral.new_alert' => fn () => [
                'referrerName' => 'Farid',
                'businessName' => 'Kopi Tepi Jalan Sdn Bhd',
                'rows' => [
                    ['Referrer', 'Farid'],
                    ['Referrer email', 'farid@example.com'],
                    ['Business', 'Kopi Tepi Jalan Sdn Bhd'],
                    ['Contact', 'Hafiz'],
                    ['Email', 'hafiz@example.com'],
                    ['Phone', '013-222 3344'],
                    ['Tier', '10%'],
                ],
                'adminUrl' => 'https://example.com/',
            ],

            'feedback.submitted_alert' => fn () => [
                'name' => 'Aisyah',
                'overall' => 5,
                'rows' => [
                    ['From', 'Aisyah'],
                    ['Project', 'Business Site'],
                    ['Overall', '5 / 5'],
                    ['NPS', '10'],
                    ['Publish consent', 'Yes'],
                ],
                'praise' => 'Clear communication the whole way through, and the site was live a week early.',
                'improve' => 'A shared checklist for the content we needed to send would have helped.',
                'adminUrl' => 'https://example.com/',
            ],

            'quotation.expired_alert' => fn () => [
                'reference' => 'AXNQ-2026-0042',
                'clientName' => 'Aisyah Rahman',
                'total' => 'RM 6,500',
                'expiredDate' => '10 Nov 2026',
                'adminUrl' => 'https://example.com/',
            ],

            'admin.account_recovery' => fn () => [
                'resetUrl' => 'https://example.com/',
                'expiresIn' => '60 minutes',
            ],

            // Kit: 28-delivery-alert.html (the bounced case)
            'system.delivery_alert' => fn () => [
                'emailLabel' => 'Invoice',
                'problem' => 'bounced',
                'audience' => 'client',
                'recordLabel' => 'Invoice AXNI-2026-0031',
                'recipient' => 'aisyah@example.com',
                'sentAt' => '11 Oct 2026, 8:46 am',
                'reason' => 'mailbox does not exist (550 5.1.1)',
                'recordUrl' => 'https://example.com/',
            ],
        ];
    }
}
