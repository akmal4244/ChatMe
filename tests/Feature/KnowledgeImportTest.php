<?php

namespace Tests\Feature;

use App\Http\Controllers\KnowledgeController;
use App\Models\Chatbot;
use App\Models\KnowledgeItem;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class KnowledgeImportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Chatbot $chatbot;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->chatbot = Chatbot::create([
            'user_id' => $this->user->id,
            'name' => 'Knowledge Bot',
        ]);
        $this->plan = Plan::create([
            'name' => 'Knowledge Test Plan',
            'slug' => 'knowledge-test-plan',
            'price' => 0,
            'knowledge_limit' => 100,
        ]);
        Subscription::create([
            'user_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'provider' => 'system',
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => null,
        ]);
    }

    public function test_valid_json_import_creates_all_rows_and_ignores_unknown_keys(): void
    {
        $response = $this->postImport($this->encodeRows([
            [
                'question' => 'What is ChatMe?',
                'answer' => 'A chatbot platform.',
                'category' => 'Product',
                'tags' => 'chatbot,platform',
                'ignored' => 'not persisted',
            ],
            [
                'question' => 'Is support available?',
                'answer' => 'Yes.',
                'category' => null,
                'tags' => null,
            ],
        ]));

        $response
            ->assertRedirect(route('knowledge.index', $this->chatbot))
            ->assertSessionHas('success', '2 soal jawab berjaya diimport.');

        $this->assertDatabaseCount('knowledge_items', 2);
        $this->assertDatabaseHas('knowledge_items', [
            'chatbot_id' => $this->chatbot->id,
            'question' => 'What is ChatMe?',
            'answer' => 'A chatbot platform.',
            'category' => 'Product',
            'tags' => 'chatbot,platform',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('knowledge_items', [
            'chatbot_id' => $this->chatbot->id,
            'question' => 'Is support available?',
            'answer' => 'Yes.',
            'category' => null,
            'tags' => null,
            'is_active' => true,
        ]);
    }

    public function test_malformed_json_returns_json_data_validation_feedback_without_writes(): void
    {
        $this->postImport('[{"question":"Broken"')
            ->assertSessionHasErrors('json_data');

        $this->assertDatabaseCount('knowledge_items', 0);
    }

    public function test_invalid_later_row_returns_json_data_validation_feedback_without_partial_writes(): void
    {
        $this->postImport($this->encodeRows([
            ['question' => 'Valid first row', 'answer' => 'Must not be written'],
            ['question' => 'Invalid second row'],
        ]))->assertSessionHasErrors('json_data');

        $this->assertDatabaseCount('knowledge_items', 0);
    }

    public function test_non_list_json_returns_json_data_validation_feedback_without_writes(): void
    {
        $this->postImport('{"question":"Root object","answer":"Not a list"}')
            ->assertSessionHasErrors('json_data');

        $this->assertDatabaseCount('knowledge_items', 0);
    }

    public function test_empty_json_list_returns_json_data_validation_feedback_without_writes(): void
    {
        $this->postImport('[]')
            ->assertSessionHasErrors('json_data');

        $this->assertDatabaseCount('knowledge_items', 0);
    }

    public function test_json_list_rejects_non_object_items(): void
    {
        $this->postImport('[["Question","Answer"]]')
            ->assertSessionHasErrors('json_data');

        $this->assertDatabaseCount('knowledge_items', 0);
    }

    public function test_import_rejects_more_than_1000_objects(): void
    {
        $rows = array_fill(0, 1001, [
            'question' => 'Question',
            'answer' => 'Answer',
        ]);

        $this->postImport($this->encodeRows($rows))
            ->assertSessionHasErrors('json_data');

        $this->assertDatabaseCount('knowledge_items', 0);
    }

    public function test_import_rejects_json_data_longer_than_100000_characters(): void
    {
        $json = '[{"question":"Question","answer":"'.str_repeat('a', 100000).'"}]';

        $this->assertGreaterThan(100000, strlen($json));

        $this->postImport($json)
            ->assertSessionHasErrors('json_data');

        $this->assertDatabaseCount('knowledge_items', 0);
    }

    public function test_import_rejects_item_fields_beyond_their_limits(): void
    {
        $this->postImport($this->encodeRows([
            [
                'question' => str_repeat('q', 256),
                'answer' => str_repeat('a', 10001),
                'category' => str_repeat('c', 256),
                'tags' => str_repeat('t', 256),
            ],
        ]))->assertSessionHasErrors('json_data');

        $this->assertDatabaseCount('knowledge_items', 0);
    }

    public function test_import_over_quota_returns_json_data_validation_feedback_without_new_rows(): void
    {
        $this->plan->update(['knowledge_limit' => 2]);
        $this->createKnowledgeItem('Existing question');

        $this->postImport($this->encodeRows([
            ['question' => 'New question one', 'answer' => 'Answer one'],
            ['question' => 'New question two', 'answer' => 'Answer two'],
        ]))->assertSessionHasErrors('json_data');

        $this->assertDatabaseCount('knowledge_items', 1);
        $this->assertDatabaseMissing('knowledge_items', ['question' => 'New question one']);
        $this->assertDatabaseMissing('knowledge_items', ['question' => 'New question two']);
    }

    public function test_single_store_over_quota_returns_validation_feedback_without_a_new_row(): void
    {
        $this->plan->update(['knowledge_limit' => 1]);
        $this->createKnowledgeItem('Existing question');

        $this->actingAs($this->user)
            ->post(route('knowledge.store', $this->chatbot), [
                'question' => 'Over quota question',
                'answer' => 'Must not be written',
            ])
            ->assertSessionHasErrors('question');

        $this->assertDatabaseCount('knowledge_items', 1);
        $this->assertDatabaseMissing('knowledge_items', ['question' => 'Over quota question']);
    }

    public function test_unlimited_plan_allows_import_beyond_the_existing_count(): void
    {
        $this->plan->update(['knowledge_limit' => -1]);
        $this->createKnowledgeItem('Existing question one');
        $this->createKnowledgeItem('Existing question two');
        $this->createKnowledgeItem('Existing question three');

        $this->postImport($this->encodeRows([
            ['question' => 'Unlimited question one', 'answer' => 'Answer one'],
            ['question' => 'Unlimited question two', 'answer' => 'Answer two'],
        ]))->assertRedirect(route('knowledge.index', $this->chatbot));

        $this->assertDatabaseCount('knowledge_items', 5);
    }

    public function test_unlimited_plan_still_obeys_the_absolute_operational_safety_limit(): void
    {
        config()->set('chatme.knowledge.absolute_limit', 3);
        $this->plan->update(['knowledge_limit' => -1]);
        $this->createKnowledgeItem('Existing question one');
        $this->createKnowledgeItem('Existing question two');
        $this->createKnowledgeItem('Existing question three');

        $this->postImport($this->encodeRows([
            ['question' => 'Unsafe extra question', 'answer' => 'Must not be written'],
        ]))->assertSessionHasErrors('json_data');

        $this->assertDatabaseCount('knowledge_items', 3);
        $this->assertDatabaseMissing('knowledge_items', ['question' => 'Unsafe extra question']);
    }

    public function test_user_quota_method_counts_the_complete_requested_batch(): void
    {
        $this->plan->update(['knowledge_limit' => 2]);
        $this->createKnowledgeItem('Existing question');

        $this->assertTrue(
            method_exists($this->user, 'canAddKnowledgeItems'),
            'User::canAddKnowledgeItems() must exist.'
        );
        $this->assertTrue($this->user->canAddKnowledgeItems($this->chatbot));
        $this->assertFalse($this->user->canAddKnowledgeItems($this->chatbot, 2));
    }

    public function test_user_quota_method_rejects_a_chatbot_owned_by_another_user(): void
    {
        $otherOwner = User::factory()->create();
        $otherChatbot = Chatbot::create([
            'user_id' => $otherOwner->id,
            'name' => 'Other Bot',
        ]);

        $this->assertTrue(
            method_exists($this->user, 'canAddKnowledgeItems'),
            'User::canAddKnowledgeItems() must exist.'
        );
        $this->assertFalse($this->user->canAddKnowledgeItems($otherChatbot));
    }

    public function test_admin_import_uses_the_chatbot_owners_quota(): void
    {
        $this->plan->update(['knowledge_limit' => 1]);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->postImport($this->encodeRows([
            ['question' => 'Admin-created question', 'answer' => 'Allowed for owner'],
        ]), $admin)->assertRedirect(route('knowledge.index', $this->chatbot));

        $this->assertDatabaseHas('knowledge_items', [
            'chatbot_id' => $this->chatbot->id,
            'question' => 'Admin-created question',
        ]);
    }

    public function test_quota_protected_writes_lock_the_chatbot_and_recount_inside_the_transaction(): void
    {
        foreach ([
            'store' => 'KnowledgeItem::create(',
            'import' => '$lockedChatbot->knowledgeItems()->create(',
        ] as $method => $writeNeedle) {
            $source = $this->controllerMethodSource($method);
            $transactionPosition = strpos($source, 'DB::transaction(function');
            $authorizationPosition = strpos($source, "Gate::authorize('update', \$chatbot)");
            $validationPosition = strpos($source, '$request->validate(');

            $this->assertNotFalse($transactionPosition, "{$method} must use a database transaction.");
            $this->assertNotFalse($authorizationPosition, "{$method} must preserve authorization.");
            $this->assertNotFalse($validationPosition, "{$method} must preserve request validation.");
            $this->assertTrue(
                $authorizationPosition < $transactionPosition && $validationPosition < $transactionPosition,
                "{$method} must authorize and validate before opening the write transaction."
            );

            $transactionBody = $this->transactionClosureBody($source);

            $this->assertNotNull($transactionBody, "{$method} must have a complete transaction closure.");
            $this->assertMatchesRegularExpression(
                '/Chatbot::query\(\)\s*->whereKey\(\$chatbot->getKey\(\)\)\s*->lockForUpdate\(\)\s*->firstOrFail\(\)/',
                $transactionBody,
                "{$method} must lock the same chatbot parent row."
            );

            $lockPosition = strpos($transactionBody, 'lockForUpdate()');
            $quotaPosition = strpos($transactionBody, 'canAddKnowledgeItems(');
            $writePosition = strpos($transactionBody, $writeNeedle);

            $this->assertNotFalse($lockPosition, "{$method} must lock before checking quota.");
            $this->assertNotFalse($quotaPosition, "{$method} must re-count quota while locked.");
            $this->assertNotFalse($writePosition, "{$method} must write while locked.");
            $this->assertTrue(
                $lockPosition < $quotaPosition && $quotaPosition < $writePosition,
                "{$method} must lock, then check quota, then write in that order."
            );
        }
    }

    public function test_single_store_rejects_question_category_and_tags_beyond_schema_length(): void
    {
        $this->actingAs($this->user)
            ->post(route('knowledge.store', $this->chatbot), [
                'question' => str_repeat('q', 256),
                'answer' => 'Valid answer',
                'category' => str_repeat('c', 256),
                'tags' => str_repeat('t', 256),
            ])
            ->assertSessionHasErrors(['question', 'category', 'tags']);

        $this->assertDatabaseCount('knowledge_items', 0);
    }

    public function test_single_update_rejects_question_category_and_tags_beyond_schema_length(): void
    {
        $item = $this->createKnowledgeItem('Original question');

        $this->actingAs($this->user)
            ->put(route('knowledge.update', [$this->chatbot, $item]), [
                'question' => str_repeat('q', 256),
                'answer' => 'Valid answer',
                'category' => str_repeat('c', 256),
                'tags' => str_repeat('t', 256),
            ])
            ->assertSessionHasErrors(['question', 'category', 'tags']);

        $this->assertDatabaseHas('knowledge_items', [
            'id' => $item->id,
            'question' => 'Original question',
        ]);
    }

    public function test_import_route_is_rate_limited(): void
    {
        $route = Route::getRoutes()->getByName('knowledge.import');

        $this->assertNotNull($route);
        $this->assertContains('throttle:10,1', $route->gatherMiddleware());
    }

    private function postImport(string $json, ?User $actor = null): TestResponse
    {
        return $this->actingAs($actor ?? $this->user)
            ->post(route('knowledge.import', $this->chatbot), [
                'json_data' => $json,
            ]);
    }

    private function encodeRows(array $rows): string
    {
        return json_encode($rows, JSON_THROW_ON_ERROR);
    }

    private function createKnowledgeItem(string $question): KnowledgeItem
    {
        return KnowledgeItem::create([
            'chatbot_id' => $this->chatbot->id,
            'question' => $question,
            'answer' => 'Existing answer',
        ]);
    }

    private function controllerMethodSource(string $method): string
    {
        $reflection = new \ReflectionMethod(KnowledgeController::class, $method);
        $fileName = $reflection->getFileName();

        $this->assertNotFalse($fileName);
        $lines = file($fileName);
        $this->assertIsArray($lines);

        return implode('', array_slice(
            $lines,
            $reflection->getStartLine() - 1,
            $reflection->getEndLine() - $reflection->getStartLine() + 1
        ));
    }

    private function transactionClosureBody(string $source): ?string
    {
        $transactionPosition = strpos($source, 'DB::transaction(function');
        if ($transactionPosition === false) {
            return null;
        }

        $openingBrace = strpos($source, '{', $transactionPosition);
        if ($openingBrace === false) {
            return null;
        }

        $depth = 0;
        for ($position = $openingBrace; $position < strlen($source); $position++) {
            if ($source[$position] === '{') {
                $depth++;
            } elseif ($source[$position] === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($source, $openingBrace + 1, $position - $openingBrace - 1);
                }
            }
        }

        return null;
    }
}
