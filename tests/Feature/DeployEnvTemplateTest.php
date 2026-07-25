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

    public function test_the_example_env_documents_both_variables(): void
    {
        $example = (string) file_get_contents(base_path('.env.example'));

        $this->assertStringContainsString('DB_RESTORE_ENABLED=false', $example);
        $this->assertStringContainsString('MEDIA_FALLBACK_URL=', $example);
    }
}
