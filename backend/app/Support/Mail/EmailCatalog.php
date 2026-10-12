<?php

namespace App\Support\Mail;

use Closure;
use InvalidArgumentException;

/**
 * Every email the dashboard can send, keyed by its stable type (spec §5.13:
 * 33 templates — email 1 is split into estimate + quotation). The single
 * source for each email's label, audience, template and subject line:
 * mailables, `mail:preview`, the email log and the admin history panel all
 * read from here. Admin-audience emails use the internal layout; everything
 * else the client layout.
 *
 * Subjects are closures over the template's view data, so the mailable and
 * the preview build the same subject from the same values.
 */
final class EmailCatalog
{
    /** @var array<string, array{label: string, audience: string, view: string, subject: Closure}>|null */
    private static ?array $definitions = null;

    /** @return array<string, array{label: string, audience: string, view: string, subject: Closure}> */
    public static function all(): array
    {
        return self::$definitions ??= self::define();
    }

    public static function has(string $type): bool
    {
        return isset(self::all()[$type]);
    }

    /** @return array{label: string, audience: string, view: string, subject: Closure} */
    public static function get(string $type): array
    {
        return self::all()[$type] ?? throw new InvalidArgumentException("Unknown email type [{$type}].");
    }

    public static function label(string $type): string
    {
        return self::get($type)['label'];
    }

    public static function audience(string $type): string
    {
        return self::get($type)['audience'];
    }

    public static function view(string $type): string
    {
        return self::get($type)['view'];
    }

    public static function layout(string $type): string
    {
        return self::audience($type) === 'admin' ? 'internal' : 'client';
    }

    /** @param  array<string, mixed>  $data  the template's view data */
    public static function subject(string $type, array $data): string
    {
        return (self::get($type)['subject'])($data);
    }

    /** @return array<string, array{label: string, audience: string, view: string, subject: Closure}> */
    private static function define(): array
    {
        $d = fn (string $label, string $audience, string $view, Closure $subject): array => compact('label', 'audience', 'view', 'subject');

        return [
            // ── Clients ────────────────────────────────────────────────────
            'quotation.estimate' => $d('Estimate', 'client', 'emails.quotation.estimate',
                fn (array $v) => "Your quote {$v['reference']} from Axel Nova Ventures"),
            'quotation.sent' => $d('Quotation', 'client', 'emails.quotation.sent',
                fn (array $v) => "Your quote {$v['reference']} from Axel Nova Ventures"),
            'inquiry.received' => $d('Inquiry received', 'client', 'emails.inquiry.received',
                fn (array $v) => 'Thanks for reaching out — Axel Nova Ventures'),
            'invoice.sent' => $d('Invoice', 'client', 'emails.invoice.sent',
                fn (array $v) => "Invoice {$v['invoiceNumber']} from Axel Nova Ventures"),
            'feedback.request' => $d('Feedback request', 'client', 'emails.feedback.request',
                fn (array $v) => 'How did we do? — quick feedback on your Axel Nova project'),
            'order.confirmed' => $d('Order confirmed', 'client', 'emails.order.confirmed',
                fn (array $v) => "Order {$v['orderNumber']} confirmed — Axel Nova Ventures"),
            'payment.receipt' => $d('Payment receipt', 'client', 'emails.payment.receipt',
                fn (array $v) => "Receipt {$v['receiptNumber']} — payment received"),
            'payment.refund' => $d('Refund confirmation', 'client', 'emails.payment.refund',
                fn (array $v) => "Refund of {$v['amount']} processed — Axel Nova Ventures"),
            'order.started' => $d('Project started', 'client', 'emails.order.started',
                fn (array $v) => "Work has started on your project — {$v['orderNumber']}"),
            'order.delivered' => $d('Project delivered', 'client', 'emails.order.delivered',
                fn (array $v) => "Your project is delivered — {$v['orderNumber']}"),
            'order.completed' => $d('Project completed', 'client', 'emails.order.completed',
                fn (array $v) => "Your project is complete — {$v['orderNumber']}"),
            'order.cancelled' => $d('Order cancelled', 'client', 'emails.order.cancelled',
                fn (array $v) => "Order {$v['orderNumber']} cancelled — Axel Nova Ventures"),
            'invoice.due_soon' => $d('Invoice due soon', 'client', 'emails.invoice.due-soon',
                fn (array $v) => "Reminder: invoice {$v['invoiceNumber']} is due {$v['dueDate']}"),
            'invoice.overdue' => $d('Invoice overdue', 'client', 'emails.invoice.overdue',
                fn (array $v) => "Invoice {$v['invoiceNumber']} is overdue"),
            'quotation.expiring' => $d('Quote expiring', 'client', 'emails.quotation.expiring',
                fn (array $v) => "Your quote {$v['reference']} expires on {$v['validUntil']}"),

            // ── Referral partners ──────────────────────────────────────────
            'referral.received' => $d('Referral received', 'partner', 'emails.referral.received',
                fn (array $v) => 'Thanks for the referral — Axel Nova Ventures'),
            'referral.update' => $d('Referral update', 'partner', 'emails.referral.update',
                fn (array $v) => "Update on your referral of {$v['businessName']}"),
            'referral.commission' => $d('Commission earned', 'partner', 'emails.referral.commission',
                fn (array $v) => 'Your referral commission — Axel Nova Ventures'),
            'partner.passcode' => $d('Partner Portal access', 'partner', 'emails.partner.passcode',
                fn (array $v) => 'Your Partner Portal access — Axel Nova Ventures'),
            'partner.reset_confirm' => $d('Passcode reset link', 'partner', 'emails.partner.reset-confirm',
                fn (array $v) => 'Reset your Partner Portal passcode'),

            // ── Team ───────────────────────────────────────────────────────
            'team.welcome' => $d('Team welcome', 'team', 'emails.team.welcome',
                fn (array $v) => 'Welcome to the Axel Nova Ventures Team'),
            'team.password_reset' => $d('Team password reset link', 'team', 'emails.team.password-reset',
                fn (array $v) => 'Reset your Axel Nova Ventures team password'),
            'task.assigned' => $d('Task assigned', 'team', 'emails.team.task-assigned',
                fn (array $v) => "New task: {$v['taskTitle']}"),
            'payroll.payslip' => $d('Payslip', 'team', 'emails.team.payslip',
                fn (array $v) => "Your payslip for {$v['period']}"),

            // ── Owner (internal layout) ────────────────────────────────────
            'quotation.new_lead_alert' => $d('New lead alert', 'admin', 'emails.admin.new-lead',
                fn (array $v) => "New lead: {$v['reference']} — {$v['subjectRange']}"),
            'inquiry.new_alert' => $d('New inquiry alert', 'admin', 'emails.admin.new-inquiry',
                fn (array $v) => "New inquiry: {$v['subjectLabel']} — {$v['name']}"),
            'partner.reset_alert' => $d('Partner passcode changed', 'admin', 'emails.admin.partner-reset',
                fn (array $v) => "Partner passcode changed — {$v['partnerName']}"),
            'team.reset_alert' => $d('Team password changed', 'admin', 'emails.admin.team-reset-request',
                fn (array $v) => "Team password changed — {$v['userName']}"),
            'system.delivery_alert' => $d('Delivery problem alert', 'admin', 'emails.admin.delivery-alert',
                fn (array $v) => "Email delivery problem: {$v['emailLabel']} to {$v['recipient']}"),
            'referral.new_alert' => $d('New referral alert', 'admin', 'emails.admin.new-referral',
                fn (array $v) => "New referral: {$v['businessName']} — from {$v['referrerName']}"),
            'feedback.submitted_alert' => $d('Feedback submitted alert', 'admin', 'emails.admin.feedback-submitted',
                fn (array $v) => "New feedback from {$v['name']} — {$v['overall']}/5"),
            'quotation.expired_alert' => $d('Quote expired alert', 'admin', 'emails.admin.quote-expired',
                fn (array $v) => "Quote expired: {$v['reference']} — {$v['clientName']}"),
            'admin.account_recovery' => $d('Account recovery', 'admin', 'emails.admin.account-recovery',
                fn (array $v) => 'Reset your Axel Nova admin password'),
        ];
    }
}
