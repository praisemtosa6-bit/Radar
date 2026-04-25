<?php

namespace App\Console\Commands;

use App\Models\Opportunity;
use App\Models\User;
use App\Models\UserOpportunity;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

#[Signature('opportunities:scrape')]
#[Description('Scrapes job APIs (RemoteOK, Remotive, Himalayas) and sends WhatsApp alerts for matching opportunities')]
class ScrapeJobApis extends Command
{
    public function handle()
    {
        $isBaseline = !Opportunity::whereIn('source', ['remoteok', 'remotive', 'himalayas', 'arbeitnow', 'jobicy'])->exists();

        if ($isBaseline) {
            $this->info("Empty database detected — running silent baseline scrape (no notifications).");
            Log::info("Radar: Baseline scrape started — notifications suppressed");
        } else {
            $this->info("Starting job API scrape...");
            Log::info("Radar: Starting job API scrape");
        }

        $newOpportunities = array_merge(
            $this->scrapeRemoteOK(),
            $this->scrapeRemotive(),
            $this->scrapeHimalayas(),
            $this->scrapeArbeitnow(),
            $this->scrapeJobicy()
        );

        $this->info("Total new opportunities saved: " . count($newOpportunities));
        Log::info("Radar: Total new opportunities saved: " . count($newOpportunities));

        if (empty($newOpportunities) || $isBaseline) {
            $this->info($isBaseline ? "Baseline complete. Next run will send notifications." : "Nothing new to match or notify.");
            return;
        }

        $this->notifyAdmin($newOpportunities);
        $this->runMatchingEngine($newOpportunities);
    }

    // -------------------------------------------------------------------------
    // Scrapers
    // -------------------------------------------------------------------------

    private function scrapeRemoteOK(): array
    {
        try {
            $response = $this->fetchWithRetry('https://remoteok.com/api');
            if (!$response) {
                $this->warn("RemoteOK: all retries exhausted, skipping.");
                Log::warning("Radar: RemoteOK all retries exhausted");
                return [];
            }

            $jobs = $response->json();
            array_shift($jobs); // first element is metadata

            $new = [];
            foreach ($jobs as $job) {
                $url = $job['url'] ?? null;
                if (!$url || Opportunity::where('url', $url)->exists()) {
                    continue;
                }

                $salary = null;
                if (!empty($job['salary_min']) && !empty($job['salary_max'])) {
                    $salary = '$' . number_format((int) $job['salary_min'])
                        . ' – $' . number_format((int) $job['salary_max']);
                }

                $new[] = Opportunity::create([
                    'title'     => $job['position'] ?? $job['title'] ?? 'Unknown',
                    'company'   => $job['company'] ?? 'Unknown',
                    'description' => strip_tags($job['description'] ?? ''),
                    'tags'      => $job['tags'] ?? [],
                    'salary'    => $salary,
                    'location'  => $job['location'] ?? 'Remote',
                    'url'       => $url,
                    'source'    => 'remoteok',
                    'posted_at' => isset($job['date']) ? \Carbon\Carbon::parse($job['date']) : now(),
                ]);
            }

            $this->info("RemoteOK: " . count($new) . " new.");
            Log::info("Radar: RemoteOK " . count($new) . " new opportunities");
            return $new;
        } catch (\Exception $e) {
            $this->error("RemoteOK failed: " . $e->getMessage());
            Log::error("Radar: RemoteOK scrape failed: " . $e->getMessage());
            return [];
        }
    }

    private function scrapeRemotive(): array
    {
        try {
            $response = $this->fetchWithRetry('https://remotive.com/api/remote-jobs');
            if (!$response) {
                $this->warn("Remotive: all retries exhausted, skipping.");
                Log::warning("Radar: Remotive all retries exhausted");
                return [];
            }

            $jobs = $response->json('jobs') ?? [];
            $new = [];

            foreach ($jobs as $job) {
                $url = $job['url'] ?? null;
                if (!$url || Opportunity::where('url', $url)->exists()) {
                    continue;
                }

                $new[] = Opportunity::create([
                    'title'       => $job['title'] ?? 'Unknown',
                    'company'     => $job['company_name'] ?? 'Unknown',
                    'description' => strip_tags($job['description'] ?? ''),
                    'tags'        => $job['tags'] ?? [],
                    'salary'      => $job['salary'] ?: null,
                    'location'    => $job['candidate_required_location'] ?? 'Remote',
                    'url'         => $url,
                    'source'      => 'remotive',
                    'posted_at'   => isset($job['publication_date'])
                        ? \Carbon\Carbon::parse($job['publication_date'])
                        : now(),
                ]);
            }

            $this->info("Remotive: " . count($new) . " new.");
            Log::info("Radar: Remotive " . count($new) . " new opportunities");
            return $new;
        } catch (\Exception $e) {
            $this->error("Remotive failed: " . $e->getMessage());
            Log::error("Radar: Remotive scrape failed: " . $e->getMessage());
            return [];
        }
    }

    private function scrapeHimalayas(): array
    {
        try {
            $response = $this->fetchWithRetry('https://himalayas.app/jobs/api');
            if (!$response) {
                $this->warn("Himalayas: all retries exhausted, skipping.");
                Log::warning("Radar: Himalayas all retries exhausted");
                return [];
            }

            $jobs = $response->json('jobs') ?? [];
            $new = [];

            foreach ($jobs as $job) {
                $url = $job['applicationUrl'] ?? $job['url'] ?? null;
                if (!$url || Opportunity::where('url', $url)->exists()) {
                    continue;
                }

                // Tags may be strings or objects with 'name'
                $rawTags = $job['tags'] ?? [];
                $tags = array_values(array_filter(array_map(
                    fn($tag) => is_array($tag) ? ($tag['name'] ?? '') : (string) $tag,
                    $rawTags
                )));

                $company = $job['company']['name']
                    ?? $job['companyName']
                    ?? $job['company'] ?? 'Unknown';

                $new[] = Opportunity::create([
                    'title'       => $job['title'] ?? 'Unknown',
                    'company'     => is_string($company) ? $company : 'Unknown',
                    'description' => strip_tags($job['description'] ?? ''),
                    'tags'        => $tags,
                    'salary'      => $job['salary'] ?? null,
                    'location'    => $job['location'] ?? 'Remote',
                    'url'         => $url,
                    'source'      => 'himalayas',
                    'posted_at'   => isset($job['createdAt'])
                        ? \Carbon\Carbon::parse($job['createdAt'])
                        : now(),
                ]);
            }

            $this->info("Himalayas: " . count($new) . " new.");
            Log::info("Radar: Himalayas " . count($new) . " new opportunities");
            return $new;
        } catch (\Exception $e) {
            $this->error("Himalayas failed: " . $e->getMessage());
            Log::error("Radar: Himalayas scrape failed: " . $e->getMessage());
            return [];
        }
    }

    private function scrapeArbeitnow(): array
    {
        try {
            $response = $this->fetchWithRetry('https://www.arbeitnow.com/api/job-board-api');
            if (!$response) {
                $this->warn("Arbeitnow: all retries exhausted, skipping.");
                Log::warning("Radar: Arbeitnow all retries exhausted");
                return [];
            }

            $jobs = $response->json('data') ?? [];
            $new = [];

            foreach ($jobs as $job) {
                $url = $job['url'] ?? null;
                if (!$url || Opportunity::where('url', $url)->exists()) {
                    continue;
                }

                $new[] = Opportunity::create([
                    'title'       => $job['title'] ?? 'Unknown',
                    'company'     => $job['company_name'] ?? 'Unknown',
                    'description' => strip_tags($job['description'] ?? ''),
                    'tags'        => $job['tags'] ?? [],
                    'salary'      => null,
                    'location'    => $job['location'] ?? ($job['remote'] ? 'Remote' : 'Unknown'),
                    'url'         => $url,
                    'source'      => 'arbeitnow',
                    'posted_at'   => isset($job['created_at'])
                        ? \Carbon\Carbon::parse($job['created_at'])
                        : now(),
                ]);
            }

            $this->info("Arbeitnow: " . count($new) . " new.");
            Log::info("Radar: Arbeitnow " . count($new) . " new opportunities");
            return $new;
        } catch (\Exception $e) {
            $this->error("Arbeitnow failed: " . $e->getMessage());
            Log::error("Radar: Arbeitnow scrape failed: " . $e->getMessage());
            return [];
        }
    }

    private function scrapeJobicy(): array
    {
        try {
            $response = $this->fetchWithRetry('https://jobicy.com/api/v2/remote-jobs');
            if (!$response) {
                $this->warn("Jobicy: all retries exhausted, skipping.");
                Log::warning("Radar: Jobicy all retries exhausted");
                return [];
            }

            $jobs = $response->json('jobs') ?? [];
            $new = [];

            foreach ($jobs as $job) {
                $url = $job['url'] ?? null;
                if (!$url || Opportunity::where('url', $url)->exists()) {
                    continue;
                }

                $tags = array_merge(
                    (array) ($job['jobIndustry'] ?? []),
                    (array) ($job['jobType'] ?? [])
                );

                $new[] = Opportunity::create([
                    'title'       => $job['jobTitle'] ?? 'Unknown',
                    'company'     => $job['companyName'] ?? 'Unknown',
                    'description' => strip_tags($job['jobDescription'] ?? $job['jobExcerpt'] ?? ''),
                    'tags'        => array_values(array_filter($tags)),
                    'salary'      => $job['annualSalaryMin'] ?? null
                        ? '$' . number_format((int) $job['annualSalaryMin']) . ' – $' . number_format((int) ($job['annualSalaryMax'] ?? $job['annualSalaryMin']))
                        : null,
                    'location'    => $job['jobGeo'] ?? 'Remote',
                    'url'         => $url,
                    'source'      => 'jobicy',
                    'posted_at'   => isset($job['pubDate'])
                        ? \Carbon\Carbon::parse($job['pubDate'])
                        : now(),
                ]);
            }

            $this->info("Jobicy: " . count($new) . " new.");
            Log::info("Radar: Jobicy " . count($new) . " new opportunities");
            return $new;
        } catch (\Exception $e) {
            $this->error("Jobicy failed: " . $e->getMessage());
            Log::error("Radar: Jobicy scrape failed: " . $e->getMessage());
            return [];
        }
    }

    // -------------------------------------------------------------------------
    // Admin broadcast — every new opportunity goes to ADMIN_WHATSAPP_NUMBER
    // -------------------------------------------------------------------------

    private function notifyAdmin(array $newOpportunities): void
    {
        $phone = env('ADMIN_WHATSAPP_NUMBER');

        if (!$phone) {
            $this->warn("ADMIN_WHATSAPP_NUMBER not set — skipping admin notifications.");
            Log::warning("Radar: ADMIN_WHATSAPP_NUMBER not set");
            return;
        }

        foreach ($newOpportunities as $opportunity) {
            $timeAgo = $opportunity->posted_at
                ? \Carbon\Carbon::parse($opportunity->posted_at)->diffForHumans()
                : 'recently';

            $tags = !empty($opportunity->tags)
                ? implode(', ', array_slice($opportunity->tags, 0, 5))
                : 'Remote';

            $salaryLine = $opportunity->salary ? "\n💰 *Salary:* {$opportunity->salary}" : '';
            $sourceLabel = strtoupper($opportunity->source);

            $messageBody = "👋 New opportunity on *{$sourceLabel}*!\n\n"
                . "🎯 *{$opportunity->title}*\n"
                . "🏢 *{$opportunity->company}*\n"
                . "📍 *Location:* {$opportunity->location}"
                . $salaryLine . "\n"
                . "🏷️ *Tags:* {$tags}\n"
                . "⏰ *Posted:* {$timeAgo}\n\n"
                . "🔗 *Apply →* {$opportunity->url}\n\n"
                . "─────────────────\n"
                . "*Radar* • finding what's yours";

            try {
                $response = Http::timeout(5)->post('http://localhost:3000/send', [
                    'phone'   => $phone,
                    'message' => $messageBody,
                ]);

                if ($response->successful()) {
                    $this->line("<info>Admin WhatsApp sent for: {$opportunity->title}</info>");
                    Log::info("Radar: Admin WhatsApp sent for opportunity {$opportunity->id}");
                } else {
                    $this->error("Admin WhatsApp Bridge error: " . $response->body());
                    Log::error("Radar: Admin WhatsApp Bridge error for opportunity {$opportunity->id}: " . $response->body());
                }
            } catch (\Exception $e) {
                $this->error("Admin WhatsApp failed for: {$opportunity->title} — " . $e->getMessage());
                Log::error("Radar: Admin WhatsApp failed for opportunity {$opportunity->id}: " . $e->getMessage());
            }

            // Stagger sends: random 2–5 second delay
            sleep(rand(2, 5));
        }
    }

    // -------------------------------------------------------------------------
    // Matching engine
    // -------------------------------------------------------------------------

    private function runMatchingEngine(array $newOpportunities): void
    {
        $users = User::whereNotNull('keywords')->get();

        if ($users->isEmpty()) {
            $this->info("No users with keywords configured. Skipping matching.");
            Log::info("Radar: No users with keywords — skipping matching engine");
            return;
        }

        foreach ($users as $user) {
            $keywords = $user->keywords ?? [];
            if (empty($keywords)) {
                continue;
            }

            foreach ($newOpportunities as $opportunity) {
                if (UserOpportunity::where('user_id', $user->id)
                    ->where('opportunity_id', $opportunity->id)
                    ->exists()) {
                    continue;
                }

                $searchText = strtolower(
                    $opportunity->title . ' ' .
                    $opportunity->company . ' ' .
                    ($opportunity->description ?? '') . ' ' .
                    implode(' ', $opportunity->tags ?? [])
                );

                $matched = false;
                foreach ($keywords as $keyword) {
                    if (str_contains($searchText, strtolower(trim($keyword)))) {
                        $matched = true;
                        break;
                    }
                }

                if (!$matched) {
                    continue;
                }

                $whatsappSent = $this->sendWhatsAppAlert($user, $opportunity);
                if (!$whatsappSent) {
                    $this->sendEmailAlert($user, $opportunity);
                }

                UserOpportunity::create([
                    'user_id'        => $user->id,
                    'opportunity_id' => $opportunity->id,
                    'notified_at'    => now(),
                ]);

                // Stagger sends: random 2–5 second delay
                sleep(rand(2, 5));
            }
        }
    }

    // -------------------------------------------------------------------------
    // Notifiers
    // -------------------------------------------------------------------------

    private function sendWhatsAppAlert(User $user, Opportunity $opportunity): bool
    {
        $phone = $user->phone ?? env('ADMIN_WHATSAPP_NUMBER');

        if (!$phone) {
            $this->warn("No WhatsApp number for user {$user->id}, skipping WhatsApp.");
            return false;
        }

        $timeAgo = $opportunity->posted_at
            ? \Carbon\Carbon::parse($opportunity->posted_at)->diffForHumans()
            : 'recently';

        $tags = !empty($opportunity->tags)
            ? implode(', ', array_slice($opportunity->tags, 0, 5))
            : 'Remote';

        $salaryLine = $opportunity->salary ? "\n💰 *Salary:* {$opportunity->salary}" : '';

        $messageBody = "Hey {$user->name} 👋 there's a new opportunity that matches your profile!\n\n"
            . "🎯 *{$opportunity->title}*\n"
            . "🏢 *{$opportunity->company}*\n"
            . "📍 *Location:* {$opportunity->location}"
            . $salaryLine . "\n"
            . "🏷️ *Tags:* {$tags}\n"
            . "⏰ *Posted:* {$timeAgo}\n\n"
            . "🔗 *Apply →* {$opportunity->url}\n\n"
            . "─────────────────\n"
            . "*Radar* • finding what's yours";

        try {
            $response = Http::timeout(5)->post('http://localhost:3000/send', [
                'phone'   => $phone,
                'message' => $messageBody,
            ]);

            if ($response->successful()) {
                $this->line("<info>WhatsApp sent to {$user->name} for: {$opportunity->title}</info>");
                Log::info("Radar: WhatsApp sent to user {$user->id} for opportunity {$opportunity->id}");
                return true;
            }

            $this->error("WhatsApp Bridge error for user {$user->id}: " . $response->body());
            Log::error("Radar: WhatsApp Bridge error for user {$user->id}: " . $response->body());
            return false;
        } catch (\Exception $e) {
            $this->error("WhatsApp failed for user {$user->id}: " . $e->getMessage());
            Log::error("Radar: WhatsApp failed for user {$user->id}: " . $e->getMessage());
            return false;
        }
    }

    private function sendEmailAlert(User $user, Opportunity $opportunity): void
    {
        if (!$user->email) {
            return;
        }

        $timeAgo = $opportunity->posted_at
            ? \Carbon\Carbon::parse($opportunity->posted_at)->diffForHumans()
            : 'recently';

        $tags = !empty($opportunity->tags)
            ? implode(', ', array_slice($opportunity->tags, 0, 5))
            : 'Remote';

        $salaryLine = $opportunity->salary ? "\nSalary: {$opportunity->salary}" : '';

        $messageBody = "Hey {$user->name} 👋 there's a new opportunity that matches your profile!\n\n"
            . "{$opportunity->title}\n"
            . "Company: {$opportunity->company}\n"
            . "Location: {$opportunity->location}"
            . $salaryLine . "\n"
            . "Tags: {$tags}\n"
            . "Posted: {$timeAgo}\n\n"
            . "Apply: {$opportunity->url}\n\n"
            . "─────────────────\n"
            . "Radar • finding what's yours";

        try {
            Mail::raw($messageBody, function ($message) use ($user, $opportunity) {
                $message->to($user->email)
                        ->subject("🎯 New Opportunity: " . $opportunity->title);
            });
            $this->line("<info>Fallback email sent to {$user->email} for: {$opportunity->title}</info>");
            Log::info("Radar: Fallback email sent to user {$user->id} for opportunity {$opportunity->id}");
        } catch (\Exception $e) {
            $this->error("Email failed for user {$user->id}: " . $e->getMessage());
            Log::error("Radar: Email failed for user {$user->id}: " . $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // HTTP helper
    // -------------------------------------------------------------------------

    private function fetchWithRetry(string $url): ?\Illuminate\Http\Client\Response
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $response = Http::timeout(10)->get($url);
                if ($response->successful()) {
                    return $response;
                }
                Log::warning("Radar: Attempt " . ($attempt + 1) . " failed for {$url} — HTTP " . $response->status());
            } catch (\Exception $e) {
                Log::warning("Radar: Attempt " . ($attempt + 1) . " exception for {$url}: " . $e->getMessage());
            }

            if ($attempt < 2) {
                sleep(pow(2, $attempt)); // 1s, 2s before retries 2 and 3
            }
        }

        return null;
    }
}
