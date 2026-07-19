<?php

namespace Tests\Feature;

use App\Mail\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Jane Visitor',
            'email' => 'jane@example.com',
            'phone' => '0722 123 456',
            'subject' => 'Reading availability',
            'message' => 'Hello, I would like to book a reading.',
            'rendered_at' => time() - 10,
        ], $overrides);
    }

    public function test_invalid_email_shows_error_and_does_not_send_mail(): void
    {
        Mail::fake();

        $response = $this->from('/en/contact')
            ->post('/en/contact', $this->validPayload(['email' => 'not-an-email']));

        $response->assertRedirect('/en/contact');
        $response->assertSessionHasErrors('email');
        Mail::assertNothingSent();

        $this->get('/en/contact')
            ->assertSee('contact-field--invalid', false);
    }

    public function test_email_without_a_tld_shows_error_and_does_not_send_mail(): void
    {
        Mail::fake();

        $response = $this->from('/en/contact')
            ->post('/en/contact', $this->validPayload(['email' => 'test@test']));

        $response->assertSessionHasErrors('email');
        Mail::assertNothingSent();

        $this->get('/en/contact')
            ->assertSee('contact-field--invalid', false);
    }

    public function test_missing_email_shows_error_and_does_not_send_mail(): void
    {
        Mail::fake();

        $response = $this->from('/en/contact')
            ->post('/en/contact', $this->validPayload(['email' => '']));

        $response->assertSessionHasErrors('email');
        Mail::assertNothingSent();
    }

    public function test_valid_submission_sends_mail_to_office_with_form_subject_and_all_fields(): void
    {
        Mail::fake();

        $response = $this->from('/en/contact')->post('/en/contact', $this->validPayload());

        $response->assertRedirect('/en/contact');
        Mail::assertSent(ContactMessage::class, function (ContactMessage $mail) {
            $rendered = $mail->render();

            return $mail->hasTo('office@astrotherapia.com')
                && $mail->subject === 'Reading availability'
                && str_contains($rendered, 'Name: Jane Visitor<br>')
                && str_contains($rendered, 'Email: jane@example.com<br>')
                && str_contains($rendered, 'Phone: 0722 123 456<br>')
                && str_contains($rendered, 'Subject: Reading availability<br>')
                && str_contains($rendered, 'Message: Hello, I would like to book a reading.');
        });
    }

    public function test_blank_subject_falls_back_to_default_subject(): void
    {
        Mail::fake();

        $this->post('/en/contact', $this->validPayload(['subject' => '']));

        Mail::assertSent(ContactMessage::class, function (ContactMessage $mail) {
            $mail->render();

            return $mail->subject === 'New contact message — '.config('app.name');
        });
    }

    public function test_blank_phone_omits_phone_line_from_mail_body(): void
    {
        Mail::fake();

        $this->post('/en/contact', $this->validPayload(['phone' => '']));

        Mail::assertSent(ContactMessage::class, function (ContactMessage $mail) {
            return ! str_contains($mail->render(), 'Phone:');
        });
    }

    public function test_form_is_hidden_and_only_confirmation_shows_after_successful_submission(): void
    {
        Mail::fake();

        $this->from('/en/contact')->post('/en/contact', $this->validPayload());

        $this->get('/en/contact')
            ->assertSee('contact-alert--success', false)
            ->assertSee('Thanks — your message is on its', false)
            ->assertDontSee('id="email"', false);
    }

    public function test_contact_page_has_no_breadcrumb(): void
    {
        $this->get('/en/contact')
            ->assertOk()
            ->assertDontSee('aria-label="Breadcrumb"', false);
    }

    public function test_contact_page_lede_text_updated(): void
    {
        $this->get('/en/contact')
            ->assertSee('Prefer e-mail or a quick message? Either works — or find us on social.');
    }

    public function test_contact_page_facebook_link_matches_footer(): void
    {
        $this->get('/en/contact')
            ->assertSee('https://www.facebook.com/astrotherapia.ro', false);
    }
}
