<?php

namespace Tests\Unit;

use App\AI\AiCompletion;
use App\AI\OpenAiProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiProviderTest extends TestCase
{
    public function test_openai_response_is_normalized_to_a_reviewable_payload(): void
    {
        $payload = [
            'summary' => 'Evento corporativo',
            'facts' => [['key' => 'público', 'value' => '800', 'evidence' => 'transcrição']],
            'missing_questions' => [],
            'risks' => [],
            'suggested_changes' => [],
        ];

        Http::fake([
            'api.openai.test/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode($payload, JSON_UNESCAPED_UNICODE)],
                ]],
                'usage' => ['prompt_tokens' => 31, 'completion_tokens' => 19],
            ], 200),
        ]);

        $completion = (new OpenAiProvider('test-key', 'test-model', 'https://api.openai.test/v1'))
            ->analyzeBriefing('Precisamos de um evento para 800 pessoas.');

        $this->assertSame(AiCompletion::SUCCESS, $completion->status);
        $this->assertSame('openai', $completion->provider);
        $this->assertSame('Evento corporativo', $completion->payload['summary']);
        $this->assertSame(31, $completion->inputTokens);
        $this->assertSame(19, $completion->outputTokens);
        Http::assertSent(function (Request $request): bool {
            return $request->hasHeader('Authorization', 'Bearer test-key')
                && $request['response_format']['type'] === 'json_schema'
                && $request['response_format']['json_schema']['strict'] === true
                && $request->url() === 'https://api.openai.test/v1/chat/completions';
        });
    }

    public function test_invalid_json_shape_is_rejected_before_it_reaches_the_domain(): void
    {
        Http::fake([
            'api.openai.test/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['summary' => 'incompleto'])]]],
            ], 200),
        ]);

        $completion = (new OpenAiProvider('test-key', 'test-model', 'https://api.openai.test/v1'))
            ->analyzeBriefing('Texto');

        $this->assertSame(AiCompletion::FAILED, $completion->status);
        $this->assertStringContainsString('Campo obrigatório', $completion->error);
    }

    public function test_nested_diff_objects_and_unknown_fields_are_rejected(): void
    {
        foreach ([
            ['facts' => [['key' => 'scope', 'value' => ['malformed'], 'evidence' => 'source']]],
            ['suggested_changes' => [['field' => 'approved_price', 'current' => '', 'suggested' => '900', 'reason' => 'unsafe']]],
            ['risks' => [false]],
        ] as $invalid) {
            $payload = array_replace(['summary' => 'Contexto', 'facts' => [], 'missing_questions' => [], 'risks' => [], 'suggested_changes' => []], $invalid);
            Http::fake(['api.openai.test/*' => Http::response(['choices' => [['message' => ['content' => json_encode($payload)]]]])]);
            $completion = (new OpenAiProvider('test-key', 'test-model', 'https://api.openai.test/v1'))->analyzeBriefing('Texto');
            $this->assertSame(AiCompletion::FAILED, $completion->status);
        }
    }
}
