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

#[Signature('opportunities:scrape-feeds')]
#[Description('Scrapes freelance RSS feeds (Upwork, WWR, PeoplePerHour, Staff Me Up, Freelancer) for multimedia and dev gigs')]
class ScrapeFreelanceFeeds extends Command
{
    // Each entry: [url, source, tags hint for RSS feeds that don't embed tags]
    private array $feeds = [
        // ── We Work Remotely ─────────────────────────────────────────────────
        [
            'url'    => 'https://weworkremotely.com/categories/remote-design-jobs.rss',
            'source' => 'weworkremotely',
            'tags'   => ['design', 'remote'],
        ],
        [
            'url'    => 'https://weworkremotely.com/categories/remote-programming-jobs.rss',
            'source' => 'weworkremotely',
            'tags'   => ['programming', 'remote'],
        ],
        [
            'url'    => 'https://weworkremotely.com/categories/remote-marketing-jobs.rss',
            'source' => 'weworkremotely',
            'tags'   => ['marketing', 'remote'],
        ],
        [
            'url'    => 'https://weworkremotely.com/categories/remote-full-stack-programming-jobs.rss',
            'source' => 'weworkremotely',
            'tags'   => ['full-stack', 'programming', 'remote'],
        ],

        // ── RemoteOK tag-specific RSS (same server as working API) ───────────
        [
            'url'    => 'https://remoteok.com/remote-video-jobs.rss',
            'source' => 'remoteok',
            'tags'   => ['video', 'video editing'],
        ],
        [
            'url'    => 'https://remoteok.com/remote-content-jobs.rss',
            'source' => 'remoteok',
            'tags'   => ['content creation', 'content'],
        ],
        [
            'url'    => 'https://remoteok.com/remote-design-jobs.rss',
            'source' => 'remoteok',
            'tags'   => ['design', 'creative'],
        ],
        [
            'url'    => 'https://remoteok.com/remote-marketing-jobs.rss',
            'source' => 'remoteok',
            'tags'   => ['marketing', 'social media'],
        ],

        // ── Dribbble (design & creative) ─────────────────────────────────────
        [
            'url'    => 'https://dribbble.com/jobs.rss',
            'source' => 'dribbble',
            'tags'   => ['design', 'creative', 'ui/ux'],
        ],

        // ── Smashing Magazine Jobs (web, design, development) ────────────────
        [
            'url'    => 'https://jobs.smashingmagazine.com/jobs.rss',
            'source' => 'smashing',
            'tags'   => ['web', 'design', 'development'],
        ],

        // ── Mandy.com (film, TV, video production) ────────────────────────────
        [
            'url'    => 'https://www.mandy.com/jobs/rss',
            'source' => 'mandy',
            'tags'   => ['film', 'video production', 'tv', 'media'],
        ],
    ];

    public function handle()
    {
        $isBaseline = !Opportunity::whereIn('source', ['weworkremotely', 'remoteok', 'dribbble', 'smashing', 'mandy'])->exists();

        if ($isBaseline) {
            $this->info("Empty database detected — running silent baseline scrape (no notifications).");
            Log::info("Radar: Freelance feeds baseline scrape started — notifications suppressed");
        } else {
            $this->info("Starting freelance feed scrape (" . count($this->feeds) . " feeds)...");
            Log::info("Radar: Starting freelance feed scrape");
        }

        $newOpportunities = [];

        foreach ($this->feeds as $feed) {
            $results = $this->scrapeFeed($feed['url'], $feed['source'], $feed['tags']);
            $newOpportunities = array_merge($newOpportunities, $results);
        }

        $this->info("Total new opportunities saved: " . count($newOpportunities));
        Log::info("Radar: Freelance feeds — total new: " . count($newOpportunities));

        if (empty($newOpportunities) || $isBaseline) {
            $this->info($isBaseline ? "Baseline complete. Next run will send notifications." : "Nothing new to match or notify.");
            return;
        }

        $this->notifyAdmin($newOpportunities);
        $this->runMatchingEngine($newOpportunities);
    }

    // -------------------------------------------------------------------------
    // RSS feed scraper (shared logic for all feeds)
    // -------------------------------------------------------------------------

    private function scrapeFeed(string $url, string $source, array $defaultTags): array
    {
        try {
            $response = $this->fetchWithRetry($url);
            if (!$response) {
                $this->warn("{$source}: all retries exhausted for {$url}, skipping.");
                Log::warning("Radar: {$source} all retries exhausted for {$url}");
                return [];
            }

            $xml = simplexml_load_string($response->body());
            if (!$xml || !isset($xml->channel->item)) {
                $this->warn("{$source}: empty or invalid RSS from {$url}");
                Log::warning("Radar: {$source} empty/invalid RSS at {$url}");
                return [];
            }

            $new = [];
            foreach ($xml->channel->item as $item) {
                $link = trim((string) ($item->link ?? $item->guid ?? ''));
                if (!$link || Opportunity::where('url', $link)->exists()) {
                    continue;
                }

                [$title, $company] = $this->parseTitle((string) $item->title, $source);
                $description = strip_tags((string) $item->description);
                $pubDate     = (string) $item->pubDate;

                // Some feeds embed category tags
                $tags = $defaultTags;
                if (isset($item->category)) {
                    foreach ($item->category as $cat) {
                        $catStr = trim((string) $cat);
                        if ($catStr && !in_array($catStr, $tags)) {
                            $tags[] = $catStr;
                        }
                    }
                }

                $new[] = Opportunity::create([
                    'title'       => $title,
                    'company'     => $company,
                    'description' => $description,
                    'tags'        => $tags,
                    'salary'      => $this->extractSalary($description),
                    'location'    => $this->extractLocation($description, $source),
                    'url'         => $link,
                    'source'      => $source,
                    'posted_at'   => $pubDate ? \Carbon\Carbon::parse($pubDate) : now(),
                ]);
            }

            $this->info("{$source}: " . count($new) . " new from " . parse_url($url, PHP_URL_HOST) . ".");
            Log::info("Radar: {$source} {$url} — " . count($new) . " new");
            return $new;
        } catch (\Exception $e) {
            $this->error("{$source} feed failed ({$url}): " . $e->getMessage());
            Log::error("Radar: {$source} feed failed ({$url}): " . $e->getMessage());
            return [];
        }
    }

    // -------------------------------------------------------------------------
    // Parsing helpers
    // -------------------------------------------------------------------------

    private function parseTitle(string $raw, string $source): array
    {
        $raw = trim(html_entity_decode(strip_tags($raw)));

        // WWR format: "Company: Job Title" or "Company - Region: Job Title"
        if ($source === 'weworkremotely' && str_contains($raw, ':')) {
            $parts = explode(':', $raw, 2);
            $company = trim(preg_replace('/\s*-\s*[A-Z]{2,}$/', '', $parts[0]));
            return [trim($parts[1]), $company ?: 'See listing'];
        }

        // Freelancer / PeoplePerHour format: often just the title
        // Upwork: title may have budget appended in parens — strip it
        $title = preg_replace('/\s*\(\$[\d,]+.*?\)\s*$/', '', $raw);

        return [$title ?: $raw, $source === 'upwork' ? 'Upwork Client' : 'See listing'];
    }

    private function extractSalary(string $description): ?string
    {
        // Match patterns like "$50/hr", "$500 fixed", "Budget: $200-$500"
        if (preg_match('/budget[:\s]+\$?([\d,]+)\s*[-–]\s*\$?([\d,]+)/i', $description, $m)) {
            return '$' . $m[1] . ' – $' . $m[2];
        }
        if (preg_match('/\$\s*([\d,]+)\s*\/\s*hr/i', $description, $m)) {
            return '$' . $m[1] . '/hr';
        }
        if (preg_match('/fixed[- ]price[:\s]+\$?([\d,]+)/i', $description, $m)) {
            return '$' . $m[1] . ' fixed';
        }
        return null;
    }

    private function extractLocation(string $description, string $source): string
    {
        if (in_array($source, ['upwork', 'peopleperhour', 'freelancer'])) {
            return 'Remote';
        }
        if (preg_match('/location[:\s]+([^\n,]+)/i', $description, $m)) {
            return trim($m[1]);
        }
        return 'Remote';
    }

    // -------------------------------------------------------------------------
    // Admin broadcast
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
                : 'Freelance';

            $salaryLine    = $opportunity->salary ? "\n💰 *Budget/Rate:* {$opportunity->salary}" : '';
            $sourceLabel   = strtoupper($opportunity->source);

            $messageBody = "👋 New gig on *{$sourceLabel}*!\n\n"
                . "🎯 *{$opportunity->title}*\n"
                . "🏢 *{$opportunity->company}*\n"
                . "📍 *Location:* {$opportunity->location}"
                . $salaryLine . "\n"
                . "🏷️ *Tags:* {$tags}\n"
                . "⏰ *Posted:* {$timeAgo}\n\n"
                . "🔗 *View →* {$opportunity->url}\n\n"
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
            return false;
        }

        $timeAgo = $opportunity->posted_at
            ? \Carbon\Carbon::parse($opportunity->posted_at)->diffForHumans()
            : 'recently';

        $tags        = !empty($opportunity->tags) ? implode(', ', array_slice($opportunity->tags, 0, 5)) : 'Freelance';
        $salaryLine  = $opportunity->salary ? "\n💰 *Budget/Rate:* {$opportunity->salary}" : '';
        $sourceLabel = strtoupper($opportunity->source);

        $messageBody = "Hey {$user->name} 👋 there's a new gig on *{$sourceLabel}* that matches your profile!\n\n"
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

            Log::error("Radar: WhatsApp Bridge error for user {$user->id}: " . $response->body());
            return false;
        } catch (\Exception $e) {
            Log::error("Radar: WhatsApp failed for user {$user->id}: " . $e->getMessage());
            return false;
        }
    }

    private function sendEmailAlert(User $user, Opportunity $opportunity): void
    {
        if (!$user->email) {
            return;
        }

        $timeAgo     = $opportunity->posted_at ? \Carbon\Carbon::parse($opportunity->posted_at)->diffForHumans() : 'recently';
        $tags        = !empty($opportunity->tags) ? implode(', ', array_slice($opportunity->tags, 0, 5)) : 'Freelance';
        $salaryLine  = $opportunity->salary ? "\nBudget/Rate: {$opportunity->salary}" : '';
        $sourceLabel = strtoupper($opportunity->source);

        $messageBody = "Hey {$user->name} 👋 new gig on {$sourceLabel} matching your profile!\n\n"
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
                        ->subject("🎯 New Gig: " . $opportunity->title);
            });
            Log::info("Radar: Fallback email sent to user {$user->id} for opportunity {$opportunity->id}");
        } catch (\Exception $e) {
            Log::error("Radar: Email failed for user {$user->id}: " . $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // HTTP helper — sends browser-like User-Agent to avoid RSS blocks
    // -------------------------------------------------------------------------

    private function fetchWithRetry(string $url): ?\Illuminate\Http\Client\Response
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; Radar/1.0; +https://radar.app)',
                    'Accept'     => 'application/rss+xml, application/xml, text/xml, */*',
                ])->timeout(10)->get($url);

                if ($response->successful()) {
                    return $response;
                }

                Log::warning("Radar: Attempt " . ($attempt + 1) . " failed for {$url} — HTTP " . $response->status());
            } catch (\Exception $e) {
                Log::warning("Radar: Attempt " . ($attempt + 1) . " exception for {$url}: " . $e->getMessage());
            }

            if ($attempt < 2) {
                sleep(pow(2, $attempt));
            }
        }

        return null;
    }
}
