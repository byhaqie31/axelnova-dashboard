<?php

namespace Tests\Feature\Invoices;

use App\Jobs\SendInvoiceEmail;
use App\Mail\InvoiceMail;
use App\Models\Order;
use App\Models\User;
use App\Services\Quoting\DocumentIssuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Emailing an invoice: the typed recipient is used for that send only (never
 * written back to the client), the queued job attaches the rendered PDF when
 * the frontend render succeeds, and degrades to link-only when it doesn't —
 * a slow Chromium must never block the invoice reaching the client.
 */
class InvoiceSendTest extends TestCase
{
    use RefreshDatabase;

    private function adminHeaders(): array
    {
        $founder = User::factory()->founder()->create();
        $token = $founder->createToken('admin-spa', ['cockpit'])->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_send_queues_the_job_with_the_typed_recipient_only(): void
    {
        Queue::fake();
        $order = Order::factory()->create();
        $originalClientEmail = $order->client->email;
        $invoice = DocumentIssuer::issueInvoice($order, ['amount' => 1300]);

        $this->postJson("/api/v1/admin/invoices/{$invoice->id}/send", [
            'email' => 'typed@example.com',
        ], $this->adminHeaders())->assertStatus(202);

        Queue::assertPushed(SendInvoiceEmail::class);
        // The typed address is for this send only — no client mutation.
        $this->assertSame($originalClientEmail, $order->client->fresh()->email);
    }

    public function test_send_validates_email_and_refuses_void_invoices(): void
    {
        Queue::fake();
        $order = Order::factory()->create();
        $invoice = DocumentIssuer::issueInvoice($order, ['amount' => 1300]);

        $this->postJson("/api/v1/admin/invoices/{$invoice->id}/send", [
            'email' => 'not-an-email',
        ], $this->adminHeaders())->assertUnprocessable();

        $void = DocumentIssuer::issueInvoice($order, ['amount' => 100, 'status' => 'void']);
        $this->postJson("/api/v1/admin/invoices/{$void->id}/send", [
            'email' => 'ok@example.com',
        ], $this->adminHeaders())->assertConflict();

        Queue::assertNothingPushed();
    }

    public function test_the_job_attaches_the_pdf_and_stamps_the_send(): void
    {
        Mail::fake();
        Http::fake(['*' => Http::response('%PDF-1.7 fake', 200, ['Content-Type' => 'application/pdf'])]);
        $order = Order::factory()->create();
        $invoice = DocumentIssuer::issueInvoice($order, ['amount' => 1300]);

        (new SendInvoiceEmail($invoice->id, 'client@example.com', 'Client'))->handle();

        Mail::assertSent(InvoiceMail::class, function (InvoiceMail $mail) {
            return $mail->hasTo('client@example.com')
                && count($mail->attachments()) === 1;
        });

        $invoice->refresh();
        $this->assertNotNull($invoice->emailed_at);
        $this->assertSame('client@example.com', $invoice->emailed_to);
    }

    public function test_the_job_falls_back_to_link_only_when_the_render_fails(): void
    {
        Mail::fake();
        Http::fake(['*' => Http::response('upstream boom', 500)]);
        $order = Order::factory()->create();
        $invoice = DocumentIssuer::issueInvoice($order, ['amount' => 1300]);

        (new SendInvoiceEmail($invoice->id, 'client@example.com'))->handle();

        // Still sent — just without the attachment.
        Mail::assertSent(InvoiceMail::class, function (InvoiceMail $mail) {
            return $mail->hasTo('client@example.com')
                && count($mail->attachments()) === 0;
        });
        $this->assertNotNull($invoice->refresh()->emailed_at);
    }
}
