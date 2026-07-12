<?php

namespace Database\Seeders;

use App\Models\Chatbot;
use App\Models\KnowledgeItem;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class HomepageChatbotSeeder extends Seeder
{
    private const CHATBOT_ROLE = 'homepage_chatbot';

    private const ENTITLEMENT_REFERENCE = 'homepage-chatbot-system';

    private const OWNER_EMAIL = 'homepage-bot@chatme.invalid';

    private const OWNER_ROLE = 'homepage_owner';

    private const SOURCE_PREFIX = 'homepage:';

    public function run(): void
    {
        $knowledge = require database_path('data/homepage_chatbot_knowledge.php');
        $this->validateKnowledge($knowledge);

        DB::transaction(function () use ($knowledge): void {
            $slug = (string) config('chatme.homepage_chatbot.slug', 'chatme-homepage');
            $allowedDomains = (string) config(
                'chatme.homepage_chatbot.allowed_domains',
                'chatme.akmalmarvis.com',
            );
            $legacyChatbotId = $this->legacyChatbotId();
            $owner = $this->resolveOwner();
            [$chatbot, $adoptedLegacy] = $this->resolveChatbot(
                $owner,
                $slug,
                $legacyChatbotId,
            );

            $this->provisionEntitlement($owner);

            $chatbot->forceFill([
                'user_id' => $owner->id,
                'system_role' => self::CHATBOT_ROLE,
                'developer_api_token_hash' => null,
                'developer_api_token_prefix' => null,
                'name' => 'ChatMe Assistant',
                'slug' => $slug,
                'avatar_url' => 'akmal3d.png',
                'primary_color' => '#4F46E5',
                'secondary_color' => '#ffffff',
                'position' => 'bottom-right',
                'welcome_message' => 'Helo! Selamat datang ke ChatMe. Apakah yang boleh saya bantu?',
                'placeholder_text' => 'Taip mesej anda...',
                'bot_name' => 'Pembantu ChatMe',
                'system_prompt' => 'Gunakan Bahasa Melayu Malaysia yang jelas, sopan dan ringkas.',
                'fallback_message' => 'Maaf, maklumat itu belum tersedia. Cuba tanya tentang pelan, pembayaran atau fungsi ChatMe.',
                'is_active' => true,
                'domain_whitelist' => $allowedDomains,
            ])->save();

            $this->reconcileKnowledge($chatbot, $knowledge, $adoptedLegacy);
        });
    }

    private function legacyChatbotId(): ?int
    {
        $value = config('chatme.homepage_chatbot.legacy_chatbot_id');

        if ($value === null || $value === '') {
            return null;
        }

        if ((! is_int($value) && (! is_string($value) || ! ctype_digit($value)))
            || (int) $value < 1) {
            throw new RuntimeException('The configured legacy chatbot ID must be a positive integer.');
        }

        return (int) $value;
    }

    private function resolveOwner(): User
    {
        $ownerByRole = User::query()
            ->where('system_role', self::OWNER_ROLE)
            ->lockForUpdate()
            ->first();
        $ownerByEmail = User::query()
            ->whereRaw('LOWER(email) = ?', [self::OWNER_EMAIL])
            ->lockForUpdate()
            ->first();

        if ($ownerByRole) {
            if (Str::lower(trim((string) $ownerByRole->email)) !== self::OWNER_EMAIL
                || ($ownerByEmail && $ownerByEmail->isNot($ownerByRole))) {
                throw new RuntimeException('The marked homepage owner conflicts with the reserved homepage owner identity.');
            }

            if ($ownerByRole->is_admin) {
                $ownerByRole->forceFill(['is_admin' => false])->save();
            }

            return $ownerByRole;
        }

        if ($ownerByEmail) {
            throw new RuntimeException('An unmarked account has preclaimed the reserved homepage owner email.');
        }

        $owner = new User;
        $owner->forceFill([
            'name' => 'ChatMe Homepage',
            'email' => self::OWNER_EMAIL,
            'password' => Str::password(64),
            'is_admin' => false,
            'system_role' => self::OWNER_ROLE,
        ])->save();

        return $owner;
    }

    /** @return array{Chatbot, bool} */
    private function resolveChatbot(User $owner, string $slug, ?int $legacyChatbotId): array
    {
        $markedChatbot = Chatbot::query()
            ->where('system_role', self::CHATBOT_ROLE)
            ->lockForUpdate()
            ->first();

        if ($markedChatbot) {
            if ($markedChatbot->slug !== $slug) {
                throw new RuntimeException('The marked homepage chatbot does not use the official slug.');
            }

            if ($legacyChatbotId !== null && $markedChatbot->id !== $legacyChatbotId) {
                throw new RuntimeException('The configured legacy chatbot ID conflicts with the marked homepage chatbot.');
            }

            if ($markedChatbot->user_id !== $owner->id) {
                throw new RuntimeException('The marked homepage chatbot is not owned by the marked homepage owner.');
            }

            return [$markedChatbot, false];
        }

        if ($legacyChatbotId !== null) {
            $legacyChatbot = Chatbot::query()
                ->whereKey($legacyChatbotId)
                ->lockForUpdate()
                ->first();

            if (! $legacyChatbot) {
                throw new RuntimeException('The configured legacy chatbot ID does not exist.');
            }

            if ($legacyChatbot->slug !== $slug) {
                throw new RuntimeException('The configured legacy chatbot ID does not use the official slug.');
            }

            if ($legacyChatbot->system_role !== null) {
                throw new RuntimeException('The configured legacy chatbot already has a different system role.');
            }

            $legacyChatbot->forceFill([
                'user_id' => $owner->id,
                'system_role' => self::CHATBOT_ROLE,
            ])->save();

            return [$legacyChatbot, true];
        }

        if (Chatbot::query()->where('slug', $slug)->lockForUpdate()->exists()) {
            throw new RuntimeException('An unmarked official slug requires an explicit legacy chatbot ID.');
        }

        $chatbot = new Chatbot;
        $chatbot->forceFill([
            'user_id' => $owner->id,
            'system_role' => self::CHATBOT_ROLE,
            'name' => 'ChatMe Assistant',
            'slug' => $slug,
        ])->save();

        return [$chatbot, false];
    }

    private function provisionEntitlement(User $owner): void
    {
        $enterprise = Plan::query()
            ->where('slug', 'enterprise')
            ->lockForUpdate()
            ->firstOrFail();
        $subscription = Subscription::query()
            ->where('provider_reference', self::ENTITLEMENT_REFERENCE)
            ->lockForUpdate()
            ->first();

        if ($subscription && $subscription->user_id !== $owner->id) {
            throw new RuntimeException('The homepage entitlement conflicts with the marked homepage owner.');
        }

        $subscription ??= new Subscription;
        $subscription->forceFill([
            'user_id' => $owner->id,
            'plan_id' => $enterprise->id,
            'provider' => 'system',
            'provider_reference' => self::ENTITLEMENT_REFERENCE,
            'status' => 'active',
            'starts_at' => $subscription->starts_at ?? now(),
            'ends_at' => $subscription->ends_at && $subscription->ends_at->isFuture()
                ? $subscription->ends_at
                : now()->addYears(100),
        ])->save();
    }

    /** @param array<int, array<string, mixed>> $knowledge */
    private function reconcileKnowledge(Chatbot $chatbot, array $knowledge, bool $adoptedLegacy): void
    {
        $sourceKeys = [];

        foreach ($knowledge as $attributes) {
            $sourceKey = $attributes['source_key'];
            $sourceKeys[] = $sourceKey;
            $item = $chatbot->knowledgeItems()
                ->where('source_key', $sourceKey)
                ->lockForUpdate()
                ->first();

            if (! $item && $adoptedLegacy) {
                $legacyMatches = $chatbot->knowledgeItems()
                    ->whereNull('source_key')
                    ->where('question', $attributes['question'])
                    ->lockForUpdate()
                    ->limit(2)
                    ->get();

                if ($legacyMatches->count() === 1) {
                    $item = $legacyMatches->first();
                }
            }

            $item ??= new KnowledgeItem;
            $item->forceFill([
                'chatbot_id' => $chatbot->id,
                'source_key' => $sourceKey,
                'question' => $attributes['question'],
                'answer' => $attributes['answer'],
                'category' => $attributes['category'] ?? null,
                'tags' => $attributes['tags'] ?? null,
                'is_active' => true,
            ])->save();
        }

        $chatbot->knowledgeItems()
            ->where('source_key', 'like', self::SOURCE_PREFIX.'%')
            ->whereNotIn('source_key', $sourceKeys)
            ->delete();
    }

    private function validateKnowledge(mixed $knowledge): void
    {
        if (! is_array($knowledge) || ! array_is_list($knowledge) || count($knowledge) !== 33) {
            throw new RuntimeException('The homepage chatbot must contain exactly 33 knowledge items.');
        }

        $sourceKeys = [];

        foreach ($knowledge as $item) {
            $sourceKey = is_array($item) ? ($item['source_key'] ?? null) : null;

            if (! is_string($sourceKey)
                || preg_match('/^homepage:\d{3}$/', $sourceKey) !== 1
                || in_array($sourceKey, $sourceKeys, true)) {
                throw new RuntimeException('Homepage knowledge source keys must be explicit and unique.');
            }

            $sourceKeys[] = $sourceKey;
        }
    }
}
