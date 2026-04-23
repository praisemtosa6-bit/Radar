<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Models\Gig;

#[Signature('gigs:scrape-nitter')]
#[Description('Scrapes X (Twitter) for dev gigs using Nitter RSS and sends WhatsApp alerts')]
class ScrapeNitterGigs extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $keywords = [
            '"looking for developer"',
            '"need a developer"',
            '"need react native"',
            '"looking for react"',
            '"need someone to build"',
            '"hiring developer"',
            '"need a web developer"',
            '"build me an app"'
        ];

        // Combine keywords with OR to save requests
        $query = implode(' OR ', $keywords);
        $encodedQuery = urlencode($query);
        
        $instances = [
            'nitter.poast.org',
            'nitter.privacydev.net',
            'nitter.projectsegfau.lt',
            'nitter.pericles.xyz',
            'nitter.rawbit.ninja',
            'nitter.rocks',
        ];

        $xml = null;

        foreach ($instances as $instance) {
            $rssUrl = "https://{$instance}/search/rss?f=tweets&q={$encodedQuery}";
            $this->info("Fetching RSS from: {$rssUrl}");
            Log::info("Radar Scraper: Fetching RSS from {$instance}");

            try {
                $response = Http::timeout(10)->get($rssUrl);

                if ($response->successful()) {
                    $xml = simplexml_load_string($response->body());
                    if ($xml && isset($xml->channel->item)) {
                        $this->info("Successfully fetched from {$instance}");
                        Log::info("Radar Scraper: Successfully fetched from {$instance}");
                        break; // Success! Exit loop.
                    }
                }
                Log::warning("Radar Scraper: {$instance} returned non-successful response or empty XML.");
            } catch (\Exception $e) {
                $this->warn("Failed connecting to {$instance}. Trying next...");
                Log::error("Radar Scraper Error: " . $e->getMessage());
            }
        }

        if (!$xml || !isset($xml->channel->item)) {
            $this->error('No items found or all Nitter instances failed.');
            Log::error('Radar Scraper: All Nitter instances failed or no items found.');
            return;
        }

        $count = 0;
            foreach ($xml->channel->item as $item) {
                $title = (string)$item->title;
                $link = (string)$item->link;
                $pubDate = (string)$item->pubDate;
                $description = strip_tags((string)$item->description);

                $this->info("Found Gig: {$title}");
                $this->line("Link: {$link}");
                
                // Check if we have already alerted about this gig
                if (Gig::where('url', $link)->exists()) {
                    Log::info("Radar Scraper: Skipping already notified gig: {$title}");
                    continue;
                }

                Log::info("Radar Scraper: NEW GIG DETECTED! Sending alerts for: {$title}");

                // Store in Database to prevent duplicate WhatsApp messages
                Gig::create([
                    'url' => $link,
                    'title' => $title,
                    'description' => $description
                ]);

                // Send WhatsApp
                $whatsappSent = $this->sendWhatsAppAlert($title, $link, $description, $pubDate);
                
                // Fallback to Email if WhatsApp fails
                if (!$whatsappSent) {
                    $this->sendEmailAlert($title, $link, $description, $pubDate);
                }
                
                $count++;
            }

            if ($count === 0) {
                $this->info("No new gigs found.");
            } else {
                $this->info("Scraped and processed {$count} new gigs.");
            }

    }

    private function sendWhatsAppAlert($title, $link, $description, $pubDate)
    {
        $recipientNumber = env('ADMIN_WHATSAPP_NUMBER'); 

        if (!$recipientNumber) {
            $this->error('ADMIN_WHATSAPP_NUMBER missing in .env');
            return false;
        }

        $timeAgo = \Carbon\Carbon::parse($pubDate)->diffForHumans();
        $cleanDescription = trim($description);

        $messageBody = "Hey Praise 👋 there's a new gig on *X (Twitter)* you might want to check out!\n\n"
            . "🎯 *{$title}*\n\n"
            . "⏰ *Posted:* {$timeAgo}\n\n"
            . "*Details:*\n"
            . "{$cleanDescription}\n\n"
            . "🔗 *View Gig →* {$link}\n\n"
            . "─────────────────\n"
            . "*Radar* • finding what's yours";
        
        try {
            // Hit our local Node.js bridge
            $response = Http::timeout(5)->post("http://localhost:3000/send", [
                'phone' => $recipientNumber,
                'message' => $messageBody
            ]);

            if ($response->successful()) {
                $this->line("<info>WhatsApp message sent via Baileys for: {$title}</info>\n");
                Log::info("Radar: WhatsApp message sent via Baileys for: {$title}");
                return true;
            } else {
                $this->error("WhatsApp Bridge error: " . $response->body());
                Log::error("Radar: WhatsApp Bridge error: " . $response->body());
                return false;
            }
        } catch (\Exception $e) {
            $this->error("Failed to connect to WhatsApp Bridge: " . $e->getMessage());
            Log::error("Radar: Failed to connect to WhatsApp Bridge: " . $e->getMessage());
            return false;
        }
    }

    private function sendEmailAlert($title, $link, $description, $pubDate)
    {
        $timeAgo = \Carbon\Carbon::parse($pubDate)->diffForHumans();
        $cleanDescription = trim($description);

        $messageBody = "Hey Praise 👋 there's a new gig on X (Twitter) you might want to check out!\n\n"
            . "🎯 {$title}\n\n"
            . "⏰ Posted: {$timeAgo}\n\n"
            . "Details:\n"
            . "{$cleanDescription}\n\n"
            . "🔗 View Gig: {$link}\n\n"
            . "─────────────────\n"
            . "Radar • finding what's yours";

        try {
            Mail::raw($messageBody, function ($message) use ($title) {
                $message->to('blacksleeky84@gmail.com')
                        ->subject("🔥 New Dev Gig: " . $title);
            });
            $this->line("<info>Fallback Email sent to blacksleeky84@gmail.com for: {$title}</info>\n");
        } catch (\Exception $e) {
            $this->error("Failed to send fallback email: " . $e->getMessage());
        }
    }
}
