<?php

namespace Tests\Unit;

use App\Support\SelfieChallenge;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SelfieChallengeTest extends TestCase
{
    #[Test]
    public function it_has_fifty_prompts_across_five_categories(): void
    {
        $this->assertCount(5, SelfieChallenge::categories());
        $this->assertCount(50, SelfieChallenge::texts());
        $this->assertSame(50, SelfieChallenge::count());

        foreach (SelfieChallenge::categories() as $category) {
            $this->assertCount(10, SelfieChallenge::textsOf($category), "หมวด {$category} ต้องมี 10 โจทย์");
        }
    }

    #[Test]
    public function prompts_are_unique(): void
    {
        $this->assertCount(50, array_unique(SelfieChallenge::texts()));
    }

    #[Test]
    public function random_returns_a_prompt_from_a_known_category(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $challenge = SelfieChallenge::random();

            $this->assertContains($challenge['category'], SelfieChallenge::categories());
            $this->assertContains($challenge['text'], SelfieChallenge::textsOf($challenge['category']));
            $this->assertNotSame('', $challenge['label']);
        }
    }

    #[Test]
    public function it_exposes_labels_and_prompts_for_the_frontend(): void
    {
        $payload = SelfieChallenge::toArray();

        $this->assertSame(SelfieChallenge::categoryLabels(), $payload['labels']);
        $this->assertCount(5, $payload['prompts']);
        $this->assertCount(50, array_merge(...array_values($payload['prompts'])));
    }
}
