<?php

namespace Tests\Feature;

use Tests\TestCase;

class DeployEnvTemplateTest extends TestCase
{
    private function script(): string
    {
        return (string) file_get_contents(base_path('.github/scripts/make-env.sh'));
    }

    public function test_the_deploy_template_emits_the_restore_flag_defaulting_to_false(): void
    {
        $this->assertStringContainsString('DB_RESTORE_ENABLED=${DB_RESTORE_ENABLED:-false}', $this->script());
    }

    public function test_the_deploy_template_emits_the_media_fallback_url_defaulting_to_empty(): void
    {
        $this->assertStringContainsString('MEDIA_FALLBACK_URL="${MEDIA_FALLBACK_URL:-}"', $this->script());
    }

    public function test_the_deploy_template_falls_back_to_the_real_contact_form_sender(): void
    {
        // The placeholder default shipped every contact email with a From address
        // on example.com, whose owner publishes "v=spf1 -all" and DMARC p=reject
        // — so any filter evaluating the sender is told to reject the message.
        // Delivery only survived because it stayed local (dovecot_virtual_delivery),
        // which skips sender policy; it would break the moment mail leaves the box.
        $script = $this->script();

        $this->assertStringContainsString(
            'MAIL_FROM_ADDRESS="${MAIL_FROM_ADDRESS:-contact-form@astrotherapia.com}"',
            $script
        );
        $this->assertStringNotContainsString('no-reply@example.com', $script);
    }

    public function test_the_example_env_documents_both_variables(): void
    {
        $example = (string) file_get_contents(base_path('.env.example'));

        $this->assertStringContainsString('DB_RESTORE_ENABLED=false', $example);
        $this->assertStringContainsString('MEDIA_FALLBACK_URL=', $example);
    }
}
