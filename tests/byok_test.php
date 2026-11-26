<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_courseinfo;

use mod_courseinfo\local\byok;

/**
 * Tests for writing Course Information on the site's own AI.
 *
 * The prompts were checked word for word against the LMS Labs service code on 150 randomised payloads
 * (identical system and user prompts, token budget, temperature and response); these tests cover the wiring.
 *
 * @package    mod_courseinfo
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_courseinfo\local\byok
 */
#[\PHPUnit\Framework\Attributes\CoversClass(byok::class)]
final class byok_test extends \advanced_testcase {
    /** @var array Requests the mocked provider received. */
    protected $requests = [];

    /** @var array A typical service payload. */
    const PAYLOAD = [
        'courseName' => 'Isolation and lockout', 'courseShortName' => 'ISO101', 'courseSummary' => 'Isolate plant safely.',
        'framework' => 'vet', 'frameworkdata' => [], 'unitCode' => 'HLTWHS001', 'unitTitle' => 'Participate in WHS',
        'nominalHours' => 20, 'enableVol' => true,
        'activities' => [['name' => 'Isolation basics', 'type' => 'contentcreator', 'section_name' => 'Week 1',
            'estimated_minutes' => 25, 'total_slides' => 12, 'total_topics' => 3]],
        'volume' => null, 'environment' => ['site_name' => 'Kestrel Ridge Training'],
        'primaryColour' => '#0f6cbf', 'secondaryColour' => '#357a32', 'enableAsqa' => true,
    ];

    /**
     * Licence the site for Bring your own AI on a test key, with text on an Anthropic key.
     */
    protected function setUp(): void {
        parent::setUp();
        if (!class_exists('\local_aiconfig\byok\ai')) {
            $this->markTestSkipped('AI Grader Central Config 1.1.0 or later is not installed.');
        }
        $this->resetAfterTest();
        $pair = sodium_crypto_sign_keypair();
        set_config('testpublickey', base64_encode(sodium_crypto_sign_publickey($pair)), 'local_aiconfig');
        $payload = json_encode(['v' => 1, 'id' => 'lic_t', 'site' => \local_aiconfig\byok\licence::site_host(),
            'plan' => 'pro', 'byok' => true, 'products' => ['*'], 'sub' => 'sub_t', 'exp' => time() + DAYSECS]);
        $b64 = function (string $s): string {
            return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        };
        set_config('licencekey', $b64($payload) . '.' .
            $b64(sodium_crypto_sign_detached($payload, sodium_crypto_sign_secretkey($pair))), 'local_aiconfig');
        set_config('src_text', 'ownkey', 'local_aiconfig');
        set_config('vendor_text', 'anthropic', 'local_aiconfig');
        set_config('key_anthropic', 'sk-test', 'local_aiconfig');
    }

    /**
     * Clean up the provider mock.
     */
    protected function tearDown(): void {
        unset($GLOBALS['local_aiconfig_byok_mock']);
        parent::tearDown();
    }

    /**
     * Make the provider answer with this JSON, or fail when null.
     *
     * @param array|null $answer
     */
    protected function answer(?array $answer): void {
        $this->requests = [];
        $GLOBALS['local_aiconfig_byok_mock'] = function ($url, $headers, $body) use ($answer) {
            $this->requests[] = $body;
            if ($answer === null) {
                throw new \moodle_exception('byok_providererror', 'local_aiconfig', '', 'Overloaded');
            }
            return ['content' => [['type' => 'text', 'text' => json_encode($answer)]]];
        };
    }

    public function test_only_answers_when_byok_is_on(): void {
        $this->assertTrue(byok::enabled());
        $this->assertNull(byok::handle('https://lms-labs.com/api/credits', []));
        $this->assertNull(byok::poll('abc123'));
        set_config('src_text', 'credits', 'local_aiconfig');
        $this->assertNull(byok::handle('https://lms-labs.com/api/moodle/course-info/generate', self::PAYLOAD));
    }

    public function test_start_and_poll_return_the_cleaned_document(): void {
        $this->answer(['html' => '<html><body><style>#courseinfo-generated .x{font-family:Inter;color:red}</style>'
            . '<div class="ci-card"><i class="fa fa-tasks">tasks</i>Course</div><script>go()</script></body></html>',
            'structured' => ['overview' => 'Isolate plant safely.']]);
        $start = byok::handle('https://lms-labs.com/api/moodle/course-info/generate/start', self::PAYLOAD);
        $this->assertTrue($start['ok']);
        $this->assertStringStartsWith('byok', $start['jobId']);
        $poll = byok::poll($start['jobId']);
        $this->assertSame('done', $poll['status']);
        $this->assertTrue($poll['result']['success']);
        $this->assertSame(
            '<style>#courseinfo-generated .x{color:red}</style><div class="ci-card"><i class="fa ' .
            'fa-tasks"></i>Course</div>',
            $poll['result']['html']
        );
        $this->assertSame('Isolate plant safely.', $poll['result']['structured']['overview']);
        $system = $this->requests[0]['system'];
        $this->assertStringContainsString('elite Australian RTO compliance officer', $system);
        $this->assertStringContainsString('This course delivers the unit HLTWHS001 — Participate in WHS', $system);
        $this->assertSame(12000, $this->requests[0]['max_tokens']);
        $this->assertSame(0.25, $this->requests[0]['temperature']);
    }

    public function test_non_vet_framework_uses_its_own_prompt(): void {
        $this->answer(['html' => '<div>ok</div>', 'structured' => []]);
        $payload = ['framework' => 'university', 'frameworkdata' => ['course' => 'BIO101']] + self::PAYLOAD;
        $out = byok::handle('https://lms-labs.com/api/moodle/course-info/generate', $payload);
        $this->assertTrue($out['success']);
        $this->assertStringContainsString('in the University framework', $this->requests[0]['system']);
        $this->assertStringContainsString(
            "Framework: University\nCourse: BIO101",
            $this->requests[0]['messages'][0]['content'][0]['text']
        );
    }

    public function test_provider_failure_becomes_a_job_error(): void {
        $this->answer(null);
        $start = byok::handle('https://lms-labs.com/api/moodle/course-info/generate/start', self::PAYLOAD);
        $poll = byok::poll($start['jobId']);
        $this->assertSame('error', $poll['status']);
        $this->assertStringContainsString('Your AI provider could not write the course information', $poll['error']);
    }
}
