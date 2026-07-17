<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Request;
use App\Http\Response;
use App\Http\View\ErrorPage;

/**
 * Renders the combined ArtsFolio help and developer reference section.
 *
 * The controller intentionally supports both topic() and article() because
 * older route bundles used different method names.  Keeping both avoids a
 * production white-screen when one route variant survives in public/index.php.
 */
final class HelpController
{
    /** @var array<string, array{title:string, body:string}> */
    private array $articles;

    public function __construct()
    {
        $this->articles = [
            'getting-started' => [
                'title' => 'Getting started',
                'body' => <<<'HTML'
<p>ArtsFolio gives you one place to build and operate your artist website: public pages, portfolio records, events, contact messages, email signups, sales, analytics, directory discovery, users, domains, and billing. The safest first goal is a small, complete, believable site that can be reviewed on a phone and shared with a collector.</p>
<h2>Before you start</h2><ul class="link-list"><li><strong>Start from your own site.</strong> Use the admin link from your welcome email or sign-in flow.</li><li><strong>Save in small batches.</strong> Change one area, save it, open the public page, and confirm the result before moving on.</li><li><strong>Keep a launch checklist.</strong> A site is ready to share when it has identity, About, Contact, at least one section, several published artworks, mobile review, contact testing, and stats visibility.</li></ul>
<h2>Recommended first build</h2><ol class="flow-list compact"><li><strong>Open your admin.</strong><span>Sign in, then click <strong>Dashboard</strong> in the sidebar. The dashboard points to messages, sales, stats, settings, artwork, events, and setup help.</span></li><li><strong>Set site identity.</strong><span>Click <strong>Settings</strong> in the sidebar. Confirm site name, public labels, titles, typography, palette, logo, watermark behavior, and visibility.</span></li><li><strong>Write About and Contact.</strong><span>Click <strong>Content</strong> in the sidebar. Add a clear About statement, practical Contact text, and selected site images.</span></li><li><strong>Create sections.</strong><span>Click <strong>Portfolio Sections</strong> in the sidebar. Create broad visitor-friendly groups such as Sculpture, Paintings, Installations, Available Work, or Archive.</span></li><li><strong>Add artwork.</strong><span>Click <strong>Upload Artwork</strong> in the sidebar. Add image, title, year, medium, dimensions, description, status, sale status, price, inventory, and sections.</span></li><li><strong>Curate the public experience.</strong><span>Click <strong>Curation</strong> in the sidebar and use the Artworks grid to decide what appears first.</span></li><li><strong>Add events.</strong><span>Click <strong>Events</strong> in the sidebar for exhibitions, open studios, fairs, talks, installations, residencies, and public history.</span></li><li><strong>Test visitor paths.</strong><span>Open homepage, portfolio, artwork details, About, Contact, email signup, cart if enabled, and mobile layout.</span></li><li><strong>Review operations.</strong><span>Click <strong>Messages</strong>, <strong>Email Signups</strong>, <strong>Stats</strong>, <strong>Audit Log</strong>, and <strong>Routes</strong> in the sidebar to review operations.</span></li></ol>
<h2>Where to go next</h2><p>Use the setup tour for the guided launch sequence, or use the function index when you know what you want to change and need the right admin page.</p><p><a class="button" href="/help/new-admin-tour">Open the new-admin setup tour</a> <a class="button" href="/help/tenant-admin-functions">Open your admin tools</a></p>
HTML,
            ],
            'new-admin-tour' => [
                'title' => 'New admin setup tour',
                'body' => <<<'HTML'
<p>This tour walks you from an empty site to a site you can confidently share. Follow it in order the first time. Later, use the function index as a map.</p><h2>1. Sign in and open the dashboard</h2><ol class="flow-list compact"><li><strong>Go to your admin page.</strong><span>Use the admin link from your welcome email or sign-in flow.</span></li><li><strong>Confirm the correct site.</strong><span>The sidebar should show your site name and your signed-in account. If the wrong site appears, sign out and use the correct host.</span></li><li><strong>Scan the dashboard.</strong><span>Use it for quick links, recent activity, sales status, and reminders.</span></li></ol><h2>2. Configure identity and branding</h2><ol class="flow-list compact"><li><strong>Open Settings.</strong><span>Click <strong>Settings</strong> in the sidebar.</span></li><li><strong>Confirm public identity.</strong><span>Set site name, title, subtitle or tagline where available, and navigation/page labels.</span></li><li><strong>Choose the visual system.</strong><span>Select palette and typography presets before adding custom CSS.</span></li><li><strong>Add the logo carefully.</strong><span>Upload/select the logo and verify it is not stretched. A correct logo keeps natural proportions with <code>height:auto</code>.</span></li><li><strong>Review watermark defaults.</strong><span>Protect images without damaging public presentation.</span></li><li><strong>Save and inspect.</strong><span>Open the public homepage in desktop and mobile widths.</span></li></ol><h2>3. Write public content</h2><ol class="flow-list compact"><li><strong>Open Content.</strong><span>Click <strong>Content</strong> in the sidebar.</span></li><li><strong>Write About.</strong><span>Explain the work, materials, questions, history, and current direction.</span></li><li><strong>Write Contact.</strong><span>Tell visitors what inquiries are welcome and what details to include.</span></li><li><strong>Select site images.</strong><span>Choose images that still work when cropped.</span></li></ol><h2>4. Build portfolio structure</h2><ol class="flow-list compact"><li><strong>Open Portfolio Sections.</strong><span>Click <strong>Portfolio Sections</strong> in the sidebar.</span></li><li><strong>Create visitor-friendly sections.</strong><span>Use names like Available Work, Sculpture, Installations, Public Art, Archive, or Recent Work.</span></li><li><strong>Sort sections.</strong><span>Put the strongest/current group first and review the public portfolio.</span></li></ol><h2>5. Upload and edit artwork</h2><ol class="flow-list compact"><li><strong>Start with Upload Artwork.</strong><span>Click <strong>Upload Artwork</strong> in the sidebar.</span></li><li><strong>Complete the edit page.</strong><span>Fill title, year, medium, dimensions, description, notes, status, sale status, price, inventory, one-off behavior, and sections.</span></li><li><strong>Publish intentionally.</strong><span>Drafts are for incomplete records. Published work appears publicly.</span></li><li><strong>Return to the grid.</strong><span>Use filters to find drafts, sale items, missing images, or section-specific work.</span></li></ol><h2>6. Curate the site</h2><ol class="flow-list compact"><li><strong>Open Curation.</strong><span>Click <strong>Curation</strong> in the sidebar.</span></li><li><strong>Choose what visitors see first.</strong><span>Feature the work that best represents the site today.</span></li><li><strong>Review public ordering.</strong><span>Open homepage, portfolio, and section pages after changes.</span></li></ol><h2>7. Add events</h2><ol class="flow-list compact"><li><strong>Open Events.</strong><span>Click <strong>Events</strong> in the sidebar.</span></li><li><strong>Add current and upcoming activity first.</strong><span>Use title, start date, end date, location, URL, description, and status.</span></li><li><strong>Backfill important history.</strong><span>Past exhibitions and installations add credibility after the current section is solid.</span></li></ol><h2>8. Test communication and sales</h2><ol class="flow-list compact"><li><strong>Submit a test contact message.</strong><span>Use the public Contact form, then click <strong>Messages</strong> in the sidebar and confirm it appears.</span></li><li><strong>Submit a test email signup.</strong><span>Use the public signup form, then click <strong>Email Signups</strong> in the sidebar and confirm it appears.</span></li><li><strong>Check sale-ready artwork.</strong><span>Confirm price, sale status, inventory, one-off behavior, shipping assumptions, and checkout setup.</span></li><li><strong>Review Sales and Sales Analytics.</strong><span>Know where order records, payment status, workflow status, Stripe identifiers, totals, and refunds live.</span></li></ol><h2>9. Invite helpers, verify domain, and launch</h2><ol class="flow-list compact"><li><strong>Invite users only when needed.</strong><span>Click <strong>Users</strong> in the sidebar.</span></li><li><strong>Verify domains.</strong><span>Click <strong>Domains</strong> in the sidebar for custom hostnames and DNS status.</span></li><li><strong>Confirm billing.</strong><span>Click <strong>Billing</strong> in the sidebar to verify plan state and feature access.</span></li><li><strong>Run diagnostics.</strong><span>Click <strong>Stats</strong>, <strong>Audit Log</strong>, and <strong>Routes</strong> in the sidebar.</span></li><li><strong>Final launch pass.</strong><span>Check public homepage, portfolio, artwork details, About, Contact, events, cart, directory listing, and phone layout.</span></li></ol><p><a class="button" href="/help/training-videos">View proposed training videos</a></p>
HTML,
            ],
            'tenant-admin-functions' => [
                'title' => 'Your admin function index',
                'body' => <<<'HTML'
<p>This is your map of the admin area. Use it when you know what you want to do but are not sure which page holds the lever. Each function below says what the page does, when to use it, what to check before saving, and where to learn more.</p><h2>Setup and dashboard</h2><ul class="link-list"><li><strong>Dashboard</strong>: your operational home base for shortcuts, recent activity, sales or message attention, and setup reminders.</li><li><strong>Getting Started</strong>: your launch checklist. See <a href="/help/new-admin-tour">new-admin setup tour</a>.</li></ul><h2>Identity, branding, and public content</h2><ul class="link-list"><li><strong>Settings</strong>: update your site name, page labels, public configuration, logo, colors, typography, watermark defaults, custom CSS, visibility, and directory summary fields. See <a href="/help/branding">branding</a>.</li><li><strong>Content</strong>: write your About copy, Contact copy, contact guidance, and selected site images. See <a href="/help/branding">branding</a>.</li></ul><h2>Artwork and portfolio</h2><ul class="link-list"><li><strong>Upload Artwork</strong>: create a new artwork record with an image. See <a href="/help/artworks">artwork management</a>.</li><li><strong>Artworks</strong>: search, filter, sort, edit, publish, unpublish, review missing images, adjust sale data, and manage section placement.</li><li><strong>Portfolio Sections</strong>: create, edit, sort, and publish visitor-facing artwork groups.</li><li><strong>Curation</strong>: choose featured work and public ordering.</li></ul><h2>Events and public history</h2><ul class="link-list"><li><strong>Events</strong>: manage exhibitions, openings, talks, fairs, residencies, installations, and dated history. See <a href="/help/events">events</a>.</li></ul><h2>Visitor communication</h2><ul class="link-list"><li><strong>Messages</strong>: review contact form submissions. See <a href="/help/messages-email">messages and email signups</a>.</li><li><strong>Email Signups</strong>: review visitors who requested updates.</li></ul><h2>Sales and commerce</h2><ul class="link-list"><li><strong>Sales</strong>: review orders, customer email, totals, payment status, workflow status, Stripe identifiers, and refunds. See <a href="/help/sales">sales</a>.</li><li><strong>Sales Analytics</strong>: review revenue, orders, and conversion clues.</li></ul><h2>Access, domains, billing, discovery, and diagnostics</h2><ul class="link-list"><li><strong>Users</strong>: invite, review, and remove your admins or helpers.</li><li><strong>Domains</strong>: manage custom hostnames and DNS verification.</li><li><strong>Billing</strong>: review plan, account standing, billing requirements, and feature availability.</li><li><strong>Directory</strong>: opt into the public artist directory, write the summary, and choose a thumbnail.</li><li><strong>Stats</strong>: review traffic and engagement.</li><li><strong>Audit Log</strong>: review sign-ins, security events, and admin changes.</li><li><strong>Routes</strong>: inspect route and hostname behavior.</li></ul>
HTML,
            ],
            'branding' => [
                'title' => 'Branding, settings, and content',
                'body' => <<<'HTML'
<p>Branding and content decide whether the public site feels intentional. Settings controls the site identity and visual system. Content controls longer public words and selected site images. Work from broad identity to fine details: name, palette, typography, logo, About, Contact, then optional custom CSS.</p><h2>Settings: what it is for</h2><ul class="link-list"><li><strong>Site identity.</strong> Use site name, titles, subtitles, and public labels. Keep names short enough for mobile headers.</li><li><strong>Palette and typography.</strong> Choose presets before custom CSS. Presets keep contrast, spacing, and mobile behavior predictable.</li><li><strong>Logo and brand image.</strong> Use a logo that remains legible at small sizes. After saving, verify it is not stretched, squeezed, cropped, or fuzzy.</li><li><strong>Watermark defaults.</strong> Protect images without overpowering the work.</li><li><strong>Public visibility and labels.</strong> Control page and navigation behavior.</li><li><strong>Custom CSS.</strong> Use only for adjustments presets cannot cover. Add small, commented changes and test mobile.</li></ul><h2>How to update branding</h2><ol class="flow-list compact"><li><strong>Open Settings.</strong><span>Click <strong>Settings</strong> in the sidebar.</span></li><li><strong>Change one group at a time.</strong><span>Update palette and typography, save, then check the public site before changing logo or CSS.</span></li><li><strong>Save the form.</strong><span>Watch for success or validation messages.</span></li><li><strong>Review public pages.</strong><span>Open homepage, portfolio, artwork detail, About, Contact, and events on desktop and mobile.</span></li><li><strong>Check contrast and readability.</strong><span>Text must be readable and links/buttons must look clickable.</span></li></ol><h2>Content: what it is for</h2><ul class="link-list"><li><strong>About page.</strong> Explain the work, materials, process, background, history, and current direction. Put the most important sentence first.</li><li><strong>Contact page.</strong> Tell visitors what messages are welcome and what information to include.</li><li><strong>Selected site images.</strong> Choose images that survive different crops and screen sizes.</li><li><strong>Static page details.</strong> Keep addresses, phone numbers, external links, studio visit notes, and commission instructions current.</li></ul><h2>How to update About and Contact</h2><ol class="flow-list compact"><li><strong>Open Content.</strong><span>Click <strong>Content</strong> in the sidebar.</span></li><li><strong>Edit About text.</strong><span>Write for a real visitor, not just a résumé parser.</span></li><li><strong>Edit Contact text.</strong><span>Include guidance for purchases, commissions, installation questions, press, exhibitions, or studio visits.</span></li><li><strong>Select or update site images.</strong><span>Preview how they render.</span></li><li><strong>Save and test.</strong><span>Open <code>/about</code> and <code>/contact</code>. Submit a test contact message if Contact changed.</span></li></ol><h2>Common mistakes</h2><ul class="link-list"><li><strong>Logo distortion.</strong> Use a better source image and avoid CSS that forces both width and height.</li><li><strong>Over-customized CSS.</strong> Tiny flourishes can become layout goblins on phones.</li><li><strong>Unreadable colors.</strong> Prioritize contrast.</li><li><strong>Outdated Contact details.</strong> Review these before every launch or announcement.</li></ul>
HTML,
            ],
            'artworks' => [
                'title' => 'Artwork, sections, and curation',
                'body' => <<<'HTML'
<p>Artwork records are the heart of a tenant site. A complete record has a strong image, accurate metadata, clear description, correct publication status, section placement, and sale fields when relevant.</p><h2>Upload a new artwork</h2><ol class="flow-list compact"><li><strong>Open Upload Artwork.</strong><span>Click <strong>Upload Artwork</strong> in the sidebar.</span></li><li><strong>Choose the best primary image.</strong><span>Use a clean, sharp image. Avoid tiny images and screenshots.</span></li><li><strong>Enter core metadata.</strong><span>Add title, year, medium, dimensions, and description.</span></li><li><strong>Choose status.</strong><span>Use draft for incomplete records and published only when ready.</span></li><li><strong>Assign sections.</strong><span>Place the work in one or more portfolio sections.</span></li><li><strong>Save and inspect.</strong><span>Open the artwork detail page and relevant section.</span></li></ol><h2>Edit and maintain artwork records</h2><ul class="link-list"><li><strong>Artworks grid.</strong> Use <strong>Artworks</strong> in the sidebar to search, filter, sort, and open records. Filters help find drafts, sale items, missing images, and section-specific groups.</li><li><strong>Edit page.</strong> Update metadata, descriptions, notes, images, publication, section placement, sale status, price, inventory, and one-off behavior.</li><li><strong>Notes.</strong> Keep internal information in admin notes and collector-facing text in public descriptions.</li><li><strong>Return state.</strong> Grid filters should survive edit-and-back workflows so bulk cleanup stays sane.</li></ul><h2>Publication status</h2><ul class="link-list"><li><strong>Draft.</strong> Use for work in progress.</li><li><strong>Published.</strong> Use when title, image, metadata, description, and placement are visitor-ready.</li><li><strong>Hidden/unpublished.</strong> Remove work from public pages without deleting the record.</li></ul><h2>Portfolio sections</h2><ol class="flow-list compact"><li><strong>Open Portfolio Sections.</strong><span>Use <strong>Portfolio Sections</strong> in the sidebar.</span></li><li><strong>Create visitor-friendly groups.</strong><span>Examples: Available Work, Sculpture, Installations, Recent Work, Archive.</span></li><li><strong>Order sections.</strong><span>Put the most important section first.</span></li><li><strong>Assign artwork.</strong><span>Select sections on artwork records. A work can appear in multiple sections when helpful.</span></li></ol><h2>Curation and ordering</h2><ol class="flow-list compact"><li><strong>Open Curation.</strong><span>Use <strong>Curation</strong> in the sidebar.</span></li><li><strong>Feature strong current work.</strong><span>The first images should explain the site quickly.</span></li><li><strong>Check public order.</strong><span>Review homepage, portfolio, section pages, and details.</span></li></ol><h2>Sale fields on artwork</h2><ul class="link-list"><li><strong>Sale status.</strong> Mark whether the work is available, unavailable, or sold according to current options.</li><li><strong>Price.</strong> Enter public price only when ready for visitors to act.</li><li><strong>Inventory quantity.</strong> Use quantity for editions, prints, multiples, or stocked items.</li><li><strong>One-off behavior.</strong> Use for unique work so availability changes after purchase.</li></ul><h2>Artwork QA checklist</h2><ul class="link-list"><li>Every published record has title, image, date context, medium, dimensions when relevant, and description.</li><li>Published work appears in at least one intentional section.</li><li>Sale-ready work has price, status, inventory, and shipping assumptions checked.</li><li>Images load on desktop and mobile and do not look stretched.</li><li>Public order puts the strongest work first.</li></ul>
HTML,
            ],
            'events' => [
                'title' => 'Your events and exhibitions',
                'body' => <<<'HTML'
<p>Events turn a portfolio into a living record. Use them for exhibitions, open studios, fairs, talks, residencies, installations, press events, deadlines, and important public history.</p><h2>Create an event</h2><ol class="flow-list compact"><li><strong>Open Events.</strong><span>Click <strong>Events</strong> in the sidebar.</span></li><li><strong>Create or edit a record.</strong><span>Use title, date range, location, URL, description, and publication status.</span></li><li><strong>Use clear titles.</strong><span>Name the exhibition, fair, talk, or project.</span></li><li><strong>Enter dates carefully.</strong><span>Use start and end dates for multi-day events.</span></li><li><strong>Add location.</strong><span>Include venue, city, state/region, and country when relevant.</span></li><li><strong>Add a URL.</strong><span>Use the venue, ticket, announcement, or press URL.</span></li><li><strong>Save and review.</strong><span>Open public event areas and confirm ordering.</span></li></ol><h2>What belongs in events</h2><ul class="link-list"><li><strong>Upcoming exhibitions.</strong> Add these first because they drive visitor action.</li><li><strong>Current exhibitions.</strong> Keep these accurate while live.</li><li><strong>Past exhibitions.</strong> Add important history after current events are solid.</li><li><strong>Open studios and fairs.</strong> Include booth, venue, or access details.</li><li><strong>Talks, panels, and workshops.</strong> Include time, host, location, and registration link.</li><li><strong>Residencies and installations.</strong> Use date ranges and location context.</li></ul><h2>Writing event descriptions</h2><ul class="link-list"><li><strong>Lead with visitor action.</strong> Say what it is, where it is, and when it happens.</li><li><strong>Keep descriptions practical.</strong> Include details that help a visitor attend or understand.</li><li><strong>Preserve history.</strong> For past events, include venue, city, project, and role.</li></ul><h2>Event QA checklist</h2><ul class="link-list"><li>Upcoming events have correct dates, location, and links.</li><li>Past events are not presented as current.</li><li>Titles are understandable outside the studio.</li><li>Public pages show sensible ordering.</li><li>Broken or obsolete external links are removed or replaced.</li></ul>
HTML,
            ],
            'sales' => [
                'title' => 'Sales, checkout, orders, analytics, and refunds',
                'body' => <<<'HTML'
<p>Sales connects your artwork availability to carts, Stripe checkout, order review, fulfillment, analytics, and refunds. Check every sale setting before taking real payments.</p>
<h2>Before enabling sales on your artwork</h2>
<ul class="link-list">
<li><strong>Publication.</strong> The artwork must be publicly visible.</li>
<li><strong>Sale status.</strong> Mark the work as available for purchase only when you are ready to sell it.</li>
<li><strong>Price.</strong> Confirm the price is final and matches any studio, gallery, or edition records you keep elsewhere.</li>
<li><strong>Inventory.</strong> Quantity should match reality. For unique work, inventory and one-off settings should prevent duplicate sales.</li>
<li><strong>Shipping.</strong> Confirm your shipping, pickup, installation, and timing notes before real payments.</li>
<li><strong>Stripe setup.</strong> Stripe must be connected before you rely on automatic payouts.</li>
</ul>
<h2>How you get paid</h2>
<p>Before buyers can check out online, click <strong>Settings</strong> in your sidebar, open the sales and payout area, and click <strong>Connect Stripe</strong>. Stripe will guide you through identity, banking, tax, and payout details on Stripe-hosted pages.</p>
<p>When you return to ArtsFolio, check the Stripe status box. Online checkout is enabled only when Stripe says your account can accept charges and receive payouts. This prevents your art sales from becoming platform-only charges that do not route to you.</p>
<h3>Stripe payout setup</h3>
<ol><li>Click <strong>Settings</strong> in the sidebar.</li><li>Find <strong>How you get paid</strong>.</li><li>Click <strong>Connect Stripe</strong> or <strong>Continue Stripe setup</strong>.</li><li>Complete the Stripe-hosted onboarding screens.</li><li>Return to ArtsFolio and confirm the status says connected and ready for checkout.</li></ol>
<p>ArtsFolio uses Stripe Connect for artwork payments. The buyer pays through Stripe Checkout. Stripe then routes the sale to your connected Stripe account and pays out to the bank account you have set up in Stripe.</p>
<ol class="flow-list compact">
<li><strong>Connect Stripe in Settings.</strong><span>Click <strong>Settings</strong> in the sidebar, then find the sales and payout area. Follow the Stripe guidance there and save your connected account details.</span></li>
<li><strong>Make sure your Stripe account is ready for payouts.</strong><span>In Stripe, finish identity, business, tax, bank, and payout setup. Stripe may pause payouts until those checks are complete.</span></li>
<li><strong>Sell through checkout.</strong><span>When a buyer completes checkout, Stripe records the payment and ArtsFolio records the order.</span></li>
<li><strong>Review the order in Sales.</strong><span>Click <strong>Sales</strong> in the sidebar to confirm the payment status, customer email, total, and Stripe identifiers.</span></li>
<li><strong>Watch Stripe for payout timing.</strong><span>Stripe sends money to your bank according to your Stripe payout schedule. New or recently changed Stripe accounts may have longer first-payout timing.</span></li>
</ol>
<p class="admin-help">Important: if Stripe is not connected, do not assume the money will automatically reach you. Check Settings before taking live sales, and use Stripe as the source of truth for actual money movement.</p>
<h2>How a visitor sale works</h2>
<ol class="flow-list compact">
<li><strong>Visitor views sale-ready artwork.</strong><span>Purchase controls appear only when the artwork and your sales setup allow it.</span></li>
<li><strong>Visitor adds the item to the cart.</strong><span>Your site keeps the cart tied to the current site and selected quantities.</span></li>
<li><strong>Visitor checks out through Stripe.</strong><span>ArtsFolio sends the cart to Stripe Checkout and records Stripe checkout/session identifiers with the order.</span></li>
<li><strong>Order appears in Sales.</strong><span>Click <strong>Sales</strong> in the sidebar to review payment status, workflow status, totals, customer email, and Stripe IDs.</span></li>
<li><strong>You fulfill the order.</strong><span>Use workflow status for handling, shipping, pickup, completion, cancellation, or other real-world steps.</span></li>
</ol>
<h2>Review orders</h2>
<ul class="link-list">
<li><strong>Order number.</strong> Use this for internal reference and customer communication.</li>
<li><strong>Payment status.</strong> Confirms whether payment succeeded, failed, was refunded, or needs review.</li>
<li><strong>Workflow status.</strong> Tracks fulfillment separately from payment.</li>
<li><strong>Totals.</strong> Compare subtotal, shipping, and total against the cart and Stripe if anything differs.</li>
<li><strong>Stripe identifiers.</strong> Use the checkout session ID and payment intent ID to cross-check Stripe.</li>
<li><strong>Customer email.</strong> Use it for fulfillment communication and protect personal data.</li>
</ul>
<h2>Refund guardrails</h2>
<ul class="link-list">
<li><strong>Verify before refunding.</strong> Confirm the order in ArtsFolio and Stripe.</li>
<li><strong>Do not retry blindly.</strong> A failed refund message is a stop sign. Check the exact failure, Stripe state, and logs first.</li>
<li><strong>Keep payment and workflow separate.</strong> Record operational status clearly.</li>
<li><strong>Use Stripe as source of truth.</strong> ArtsFolio should reflect Stripe money movement.</li>
</ul>
<h2>Sales Analytics</h2>
<ul class="link-list">
<li><strong>Revenue and order volume.</strong> Review totals to see whether sales pages are working.</li>
<li><strong>Conversion clues.</strong> Compare traffic, cart activity, and completed orders when available.</li>
<li><strong>Operational review.</strong> Spot stale inventory, confusing pricing, or checkout drop-off.</li>
</ul>
<h2>Sales QA checklist</h2>
<ul class="link-list">
<li>Stripe is connected before you take live sales.</li>
<li>Your Stripe account has complete identity, bank, tax, and payout information.</li>
<li>Sale-ready artwork has sale status, price, inventory, one-off behavior, and shipping checked.</li>
<li>Checkout is tested before launch.</li>
<li>You know where Stripe IDs appear in Sales.</li>
<li>Refunds are verified in Stripe before and after action.</li>
<li>Failed refund messages stop further refund attempts until investigated.</li>
</ul>
HTML,
            ],
            'messages-email' => [
                'title' => 'Messages and your email list',
                'body' => <<<'HTML'
<p>Messages and email signups are the visitor-response desk. Messages come from the public contact form. Email signups come from visitors asking for updates.</p><h2>Contact messages</h2><ol class="flow-list compact"><li><strong>Open Messages.</strong><span>Go to <strong>Messages</strong> in the sidebar.</span></li><li><strong>Review sender details.</strong><span>Check name, email, subject, body, timestamp, and context.</span></li><li><strong>Choose follow-up.</strong><span>Reply from the appropriate email account or external workflow if direct reply is not built into the screen.</span></li><li><strong>Watch for spam or abuse.</strong><span>Do not send attachments, passwords, private links, or sensitive information to suspicious messages.</span></li><li><strong>Use status or notes when available.</strong><span>Mark handled messages if the screen supports it.</span></li></ol><h2>Email signups</h2><ol class="flow-list compact"><li><strong>Open Email Signups.</strong><span>Go to <strong>Email Signups</strong> in the sidebar.</span></li><li><strong>Review the list.</strong><span>Check address, timestamp, source/context, and duplicates.</span></li><li><strong>Use signups respectfully.</strong><span>These are people asking for updates, not unrelated mail.</span></li><li><strong>Export or copy only when needed.</strong><span>Preserve consent context and remove stale/invalid addresses.</span></li></ol><h2>Testing the public forms</h2><ol class="flow-list compact"><li><strong>Submit a test Contact message.</strong><span>Use an address you control.</span></li><li><strong>Confirm admin receipt.</strong><span>Check <strong>Messages</strong> in the sidebar.</span></li><li><strong>Submit a test email signup.</strong><span>Use the public signup form.</span></li><li><strong>Confirm signup receipt.</strong><span>Check <strong>Email Signups</strong> in the sidebar.</span></li></ol><h2>Good operating habits</h2><ul class="link-list"><li><strong>Respond while the trail is warm.</strong> Collector inquiries cool quickly.</li><li><strong>Keep private data private.</strong> Do not paste contact lists into public documents or screenshots.</li><li><strong>Separate inquiries from signups.</strong> Purchase questions, press inquiries, and mailing-list signups need different follow-up.</li><li><strong>Check after changing Contact content.</strong> Submit another test message.</li></ul>
HTML,
            ],
            'users-domains-billing' => [
                'title' => 'Users, domains, and billing',
                'body' => <<<'HTML'
<p>Users, domains, and billing affect who can change the site, which hostname visitors use, and which features your site can access.</p><h2>Users</h2><ol class="flow-list compact"><li><strong>Open Users.</strong><span>Go to <strong>Users</strong> in the sidebar.</span></li><li><strong>Review existing access.</strong><span>Confirm every listed person still needs access.</span></li><li><strong>Invite helpers deliberately.</strong><span>Add users only when they need to edit content, manage sales, or administer the tenant.</span></li><li><strong>Use the minimum useful role.</strong><span>Avoid owner-level access for content-only helpers.</span></li><li><strong>Remove stale access.</strong><span>Remove helpers, contractors, galleries, or staff when access is no longer needed.</span></li></ol><h2>Domains</h2><ol class="flow-list compact"><li><strong>Open Domains.</strong><span>Go to <strong>Domains</strong> in the sidebar.</span></li><li><strong>Add the desired hostname.</strong><span>Use the public domain visitors should type.</span></li><li><strong>Follow DNS instructions exactly.</strong><span>DNS records must match the expected target.</span></li><li><strong>Wait for DNS propagation.</strong><span>Recheck verification after records update.</span></li><li><strong>Verify status.</strong><span>Use the domain screen and routes diagnostics.</span></li><li><strong>Check HTTPS.</strong><span>Open the public site with <code>https://</code> and check for browser warnings.</span></li></ol><h2>Billing</h2><ol class="flow-list compact"><li><strong>Open Billing.</strong><span>Go to <strong>Billing</strong> in the sidebar.</span></li><li><strong>Review plan state.</strong><span>Confirm the expected tier and features.</span></li><li><strong>Check account standing.</strong><span>Look for failed payment, incomplete checkout, cancellation, or feature-limit warnings.</span></li><li><strong>Resolve issues before launch.</strong><span>Billing problems can affect domains, sales, storage, or features depending on plan rules.</span></li></ol><h2>Access and account checklist</h2><ul class="link-list"><li>Only current helpers have access.</li><li>Owner/admin accounts use strong passwords and current email addresses.</li><li>Custom domains verify and load the correct tenant over HTTPS.</li><li>Billing state matches the desired plan.</li><li>Plan-dependent features are checked before launch.</li></ul>
HTML,
            ],
            'directory' => [
                'title' => 'Artist directory',
                'body' => <<<'HTML'
<p>The public artist directory helps visitors discover tenants from the platform site. Directory visibility has two gates: the platform directory must be enabled, and your site must opt in.</p><h2>Before opting in</h2><ul class="link-list"><li><strong>Published artwork.</strong> A small set of strong artwork should be public.</li><li><strong>Clear identity.</strong> Site name, logo, palette, and About should be presentable.</li><li><strong>Contact path.</strong> The Contact page and form should work.</li><li><strong>Directory summary.</strong> Explain the work in one or two compact sentences.</li><li><strong>Directory image.</strong> Use an image that reads well as a thumbnail.</li></ul><h2>Configure directory listing</h2><ol class="flow-list compact"><li><strong>Open Directory.</strong><span>Go to <strong>Directory</strong> in the sidebar, or use directory fields in Settings if the build places them there.</span></li><li><strong>Enable opt-in.</strong><span>Turn on platform directory visibility for the tenant.</span></li><li><strong>Write the summary.</strong><span>Mention medium, focus, location, or distinctive body of work.</span></li><li><strong>Choose the thumbnail.</strong><span>Select artwork or a site image that remains strong when cropped small.</span></li><li><strong>Save and review.</strong><span>Open the platform directory and confirm title, image, summary, and link.</span></li></ol><h2>Writing a useful directory summary</h2><ul class="link-list"><li><strong>Say what visitors will see.</strong> Use medium plus subject plus distinguishing approach.</li><li><strong>Keep it short.</strong> Directory cards are not the complete artist statement.</li><li><strong>Update it seasonally.</strong> Change it when the work or public direction changes.</li></ul><h2>Troubleshooting directory visibility</h2><ul class="link-list"><li><strong>Not listed.</strong> Confirm platform directory is enabled, tenant opt-in is enabled, and your site is public.</li><li><strong>Wrong image.</strong> Re-select thumbnail and refresh after save.</li><li><strong>Weak listing.</strong> Improve public artwork, About text, summary, and thumbnail.</li></ul>
HTML,
            ],
            'stats' => [
                'title' => 'Your stats',
                'body' => <<<'HTML'
<p>Stats show how visitors interact with your site site. Use them to see whether announcements, domain changes, events, directory listings, or public content changes caused movement.</p><h2>Open and read stats</h2><ol class="flow-list compact"><li><strong>Open Stats.</strong><span>Click <strong>Stats</strong> in the sidebar.</span></li><li><strong>Select a useful date range.</strong><span>Match the question: launch day, last week, campaign, exhibition period, or all history.</span></li><li><strong>Review total traffic.</strong><span>Look for changes after announcements, launches, events, and emails.</span></li><li><strong>Review page engagement.</strong><span>Identify which pages and artwork records attract attention.</span></li><li><strong>Look for odd patterns.</strong><span>Unnatural spikes may be bots, crawlers, previews, or admin testing.</span></li></ol><h2>How to use stats for decisions</h2><ul class="link-list"><li><strong>Portfolio order.</strong> If certain work gets attention, make related work easier to find.</li><li><strong>Contact and sales.</strong> Compare traffic with messages, signups, cart activity, and orders.</li><li><strong>Events.</strong> After adding an event, check whether related pages receive traffic.</li><li><strong>Directory performance.</strong> If directory traffic exists, improve the listing summary and landing pages.</li></ul><h2>Why stats may look wrong</h2><ul class="link-list"><li><strong>Bot filtering.</strong> Crawlers may be filtered or may still appear.</li><li><strong>Admin testing.</strong> Repeated refreshes during editing create noise.</li><li><strong>DNS/domain mismatch.</strong> Verify custom domains point to the correct tenant.</li><li><strong>Date range.</strong> Empty stats often come from looking at the wrong period.</li><li><strong>New site.</strong> A new site may simply not have traffic yet.</li></ul><h2>Stats QA checklist</h2><ul class="link-list"><li>Open the public site and confirm activity appears after the expected processing delay.</li><li>Check both platform subdomain and custom domain if both are used.</li><li>Compare traffic with messages, signups, carts, and sales.</li><li>Use audit log and routes when stats imply the wrong host or route.</li></ul>
HTML,
            ],
            'audit' => [
                'title' => 'Audit log and tenant diagnostics',
                'body' => <<<'HTML'
<p>Audit Log and Routes are diagnostic pages. Use them when something changed unexpectedly, a custom domain behaves oddly, or a route reaches the wrong page.</p><h2>Audit Log</h2><ul class="link-list"><li><strong>What it records.</strong> Sign-ins, security events, admin changes, content changes, domain actions, billing-related changes, or other tracked operations depending on the build.</li><li><strong>When to use it.</strong> Use it when a setting changed unexpectedly, access is in question, a domain action needs review, or you need to know who performed an admin action.</li><li><strong>How to read it.</strong> Review timestamp, actor, action, target, and details.</li><li><strong>What it does not replace.</strong> It is not a full server log, payment processor log, email delivery log, or backup.</li></ul><h2>Use Audit Log</h2><ol class="flow-list compact"><li><strong>Open Audit Log.</strong><span>Click <strong>Audit Log</strong> in the sidebar.</span></li><li><strong>Scan by time.</strong><span>Start with the time window when the change likely occurred.</span></li><li><strong>Identify actor and action.</strong><span>Look for user, event type, and target record.</span></li><li><strong>Compare with public behavior.</strong><span>If content changed, open the public page and confirm the result.</span></li></ol><h2>Routes</h2><ul class="link-list"><li><strong>What it shows.</strong> Route and hostname behavior for the current site environment.</li><li><strong>When to use it.</strong> Use it when a custom domain loads the wrong site, an admin route redirects unexpectedly, a public page 404s, or local/prod route behavior differs.</li><li><strong>What to compare.</strong> Current host, tenant slug, expected path, HTTP method, and whether the route is platform, tenant public, or your admin.</li></ul><h2>Route troubleshooting workflow</h2><ol class="flow-list compact"><li><strong>Confirm the hostname.</strong><span>Make sure the browser is on your site domain you intend to edit or view.</span></li><li><strong>Open Routes.</strong><span>Click <strong>Routes</strong> in the sidebar.</span></li><li><strong>Check the expected path.</strong><span>Find the page/action and confirm method and route group.</span></li><li><strong>Check Domains.</strong><span>If custom hostname is involved, click <strong>Domains</strong> in the sidebar.</span></li><li><strong>Check Audit Log.</strong><span>Look for recent settings or domain changes.</span></li></ol><h2>Escalation notes</h2><ul class="link-list"><li>For payment discrepancies, compare ArtsFolio with Stripe before acting.</li><li>For email delivery issues, review email/outbox operations.</li><li>For domain issues, record expected hostname and DNS target before repeated changes.</li><li>For access concerns, remove stale users first, then review audit history.</li></ul>
HTML,
            ],
            'training-videos' => [
                'title' => 'Training video directory',
                'body' => <<<'HTML'
<p>This directory lists proposed ArtsFolio tenant-admin training videos. Links will be added after recording and publishing. Until then, each entry explains what the video will teach and which help page covers the same material in writing.</p><h2>Video directory</h2><ul class="link-list"><li><strong>01. Your admin orientation.</strong> Covers signing in, using the sidebar, reading the dashboard, finding help, and following the tour. Written guide: <a href="/help/new-admin-tour">new-admin setup tour</a>. <span>Video link pending.</span></li><li><strong>02. Site identity, branding, and content.</strong> Covers Settings, Content, site name, logo, palette, typography, About, Contact, site images, watermark defaults, and public review. Written guide: <a href="/help/branding">branding</a>. <span>Video link pending.</span></li><li><strong>03. Artwork upload and portfolio structure.</strong> Covers Upload Artwork, Artworks grid, edit pages, publication, sale fields, sections, filters, return-to-grid workflow, and curation. Written guide: <a href="/help/artworks">artwork management</a>. <span>Video link pending.</span></li><li><strong>04. Events and public history.</strong> Covers event records, date ranges, locations, URLs, descriptions, ordering, and QA. Written guide: <a href="/help/events">events</a>. <span>Video link pending.</span></li><li><strong>05. Messages and email signups.</strong> Covers Contact testing, message review, email signups, privacy, and follow-up. Written guide: <a href="/help/messages-email">messages and email signups</a>. <span>Video link pending.</span></li><li><strong>06. Sales, orders, analytics, and refunds.</strong> Covers sale-ready artwork, carts, Stripe checkout, orders, workflow status, analytics, refund verification, and failed-refund stop rules. Written guide: <a href="/help/sales">sales</a>. <span>Video link pending.</span></li><li><strong>07. Users, domains, billing, and diagnostics.</strong> Covers invitations, access cleanup, custom domains, DNS, billing, directory, stats, audit log, and routes. <span>Video link pending.</span></li></ul><h2>How to use these videos when they are recorded</h2><ol class="flow-list compact"><li><strong>Watch orientation first.</strong><span>It gives the map and vocabulary.</span></li><li><strong>Watch branding before artwork.</strong><span>Artwork pages are easier to judge after the site frame is stable.</span></li><li><strong>Watch sales only when needed.</strong><span>If you do not sell through the site, focus on portfolio, contact, directory, and stats first.</span></li><li><strong>Use written help while editing.</strong><span>Articles stay on screen while you work.</span></li></ol><h2>Script archive</h2><p>The full scripts were exported as <code>ArtsFolio_Tenant_Admin_Training_Video_Scripts_20260708.docx</code>. Later video publishing only needs URL updates.</p>
HTML,
            ],
        ];
    }

    public function index(Request $request, ?array $currentUser = null): Response
    {
        return $this->topic($request, 'getting-started', $currentUser);
    }

    public function topic(Request $request, string|array $slug = 'getting-started', ?array $currentUser = null): Response
    {
        return $this->article($request, $slug, $currentUser);
    }

    public function article(Request $request, string|array $slug = 'getting-started', ?array $currentUser = null): Response
    {
        if (is_array($slug)) {
            $slug = (string) ($slug['article'] ?? $slug['topic'] ?? $slug['slug'] ?? 'getting-started');
        }

        $slug = $this->normalizeArticleSlug((string) $slug);

        if ($slug === 'developer') {
            return $this->developer($request, $currentUser);
        }

        $article = $this->articles[$slug] ?? null;
        if (!$article) {
            return Response::html($this->layout('Help article not found', '<p class="admin-error">That help article does not exist.</p>', '', $currentUser), 404);
        }

        return Response::html($this->layout($article['title'], $article['body'], $slug, $currentUser));
    }

    public function developer(Request $request, ?array $currentUser = null): Response
    {
        if ($this->isDeveloperResourceRequest($request) && !$currentUser) {
            return new Response('', 302, ['Location' => '/login']);
        }

        if (!$currentUser && isset($GLOBALS['artsfolio_current_user']) && is_array($GLOBALS['artsfolio_current_user'])) {
            $currentUser = $GLOBALS['artsfolio_current_user'];
        }

        if (!$currentUser) {
            return new Response('', 302, ['Location' => '/login?next=/help/developer']);
        }

        $body = <<<HTML
<p class="admin-muted">Developer information is visible only after login. This reference gives each major browser/API route a practical usage description and a copy-pasteable example.</p>

<h2>How to read this reference</h2>
<p>Examples assume the command is run from a trusted workstation and that browser-authenticated routes use the same session cookie a user receives after login. Replace <code>https://artsfol.io</code>, tenant hostnames, IDs, and form values with real deployment values.</p>

<h2>Browser authentication</h2>
<div class="feature-grid developer-route-grid">
    <article><h3>GET /login</h3><p>Render the branded login form. Use this route when redirecting a browser user who needs to authenticate before reaching admin, developer, or account pages.</p><pre><code>curl -i https://artsfol.io/login</code></pre></article>
    <article><h3>POST /login</h3><p>Submit local email/password credentials from the branded login form. A successful response sets the browser session cookie and redirects the user.</p><pre><code>curl -i -X POST https://artsfol.io/login \
  -d 'email=admin@example.com' \
  -d 'password=replace-with-real-password'</code></pre></article>
    <article><h3>POST /login/password</h3><p>Backward-compatible password-login endpoint retained for older forms and scripts. Prefer <code>POST /login</code> for new browser form work unless you are maintaining an old flow.</p><pre><code>curl -i -X POST https://artsfol.io/login/password \
  -d 'email=admin@example.com' \
  -d 'password=replace-with-real-password'</code></pre></article>
    <article><h3>POST /logout</h3><p>Clear the active browser session. Use this from logout buttons in authenticated platform or your admin screens.</p><pre><code>curl -i -X POST https://artsfol.io/logout \
  -H 'Cookie: artsfolio_session=SESSION_COOKIE_VALUE'</code></pre></article>
</div>

<h2>Platform public routes</h2>
<div class="feature-grid developer-route-grid">
    <article><h3>GET /</h3><p>Render the ArtsFolio public landing page. Use this as the canonical platform homepage for marketing, directory entry points, help links, and signup calls to action.</p><pre><code>curl -i https://artsfol.io/</code></pre></article>
    <article><h3>GET /pricing</h3><p>Render public plan and pricing information. Use this route from marketing pages, emails, and onboarding flows where users compare tiers.</p><pre><code>curl -i https://artsfol.io/pricing</code></pre></article>
    <article><h3>GET /signup</h3><p>Render your site signup form. Use this for new artists or organizations starting an ArtsFolio tenant.</p><pre><code>curl -i https://artsfol.io/signup</code></pre></article>
    <article><h3>POST /signup</h3><p>Create a tenant, public slug, initial owner user, membership, and provisioning jobs. The form is CSRF-protected in the browser path, so scripts should be limited to controlled tests unless an API-specific signup endpoint is added.</p><pre><code>curl -i -X POST https://artsfol.io/signup \
  -d 'csrf_token=TOKEN_FROM_FORM' \
  -d 'site_name=Example Studio' \
  -d 'slug=example-studio' \
  -d 'admin_name=Example Admin' \
  -d 'email=admin@example.com' \
  -d 'password=long-development-password'</code></pre></article>
    <article><h3>GET /directory</h3><p>Render the public artist directory. Results require the platform directory to be enabled and each listed tenant to have opted into discovery.</p><pre><code>curl -i https://artsfol.io/directory</code></pre></article>
    <article><h3>GET /help</h3><p>Render the public help landing article. Use this as the general support entry point for artists and your admins.</p><pre><code>curl -i https://artsfol.io/help</code></pre></article>
    <article><h3>GET /help/{article}</h3><p>Render a specific help article, such as branding, artworks, events, directory, stats, audit, or developer. Developer reference is login-gated.</p><pre><code>curl -i https://artsfol.io/help/branding</code></pre></article>
    <article><h3>GET /developer</h3><p>Compatibility route for the developer reference. It requires login and should redirect anonymous users to the login flow.</p><pre><code>curl -i -H 'Cookie: artsfolio_session=SESSION_COOKIE_VALUE' \
  https://artsfol.io/developer</code></pre></article>
</div>

<h2>Platform admin routes</h2>
<div class="feature-grid developer-route-grid">
    <article><h3>GET /platform/admin</h3><p>Open the platform admin dashboard. This is for global ArtsFolio operations, not tenant-site editing.</p><pre><code>curl -i -H 'Cookie: artsfolio_session=SESSION_COOKIE_VALUE' \
  https://artsfol.io/platform/admin</code></pre></article>
    <article><h3>GET /platform/admin/platform-settings</h3><p>Edit global platform settings such as platform branding, copyright, directory behavior, OAuth configuration notes, and directory thumbnail sizing.</p><pre><code>curl -i -H 'Cookie: artsfolio_session=SESSION_COOKIE_VALUE' \
  https://artsfol.io/platform/admin/platform-settings</code></pre></article>
    <article><h3>POST /platform/admin/platform-settings</h3><p>Save platform settings. Use the browser form so CSRF and audit behavior remain intact.</p><pre><code>curl -i -X POST https://artsfol.io/platform/admin/platform-settings \
  -H 'Cookie: artsfolio_session=SESSION_COOKIE_VALUE' \
  -d 'csrf_token=TOKEN_FROM_FORM' \
  -d 'platform_footer_copyright_html=© {year} ArtsFolio'</code></pre></article>
    <article><h3>GET /platform/admin/domains</h3><p>Review custom-domain status, DNS verification state, and related jobs. Use this to troubleshoot tenant custom domains.</p><pre><code>curl -i -H 'Cookie: artsfolio_session=SESSION_COOKIE_VALUE' \
  https://artsfol.io/platform/admin/domains</code></pre></article>
    <article><h3>POST /platform/admin/domains/action</h3><p>Run domain actions such as DNS verification. The response returns to the domain admin screen with status messaging.</p><pre><code>curl -i -X POST https://artsfol.io/platform/admin/domains/action \
  -H 'Cookie: artsfolio_session=SESSION_COOKIE_VALUE' \
  -d 'csrf_token=TOKEN_FROM_FORM' \
  -d 'domain_id=123' \
  -d 'action=verify_dns'</code></pre></article>
    <article><h3>GET /platform/admin/stats</h3><p>View platform analytics, including aggregate day/hour charts and location/IP drill-downs when analytics data is present.</p><pre><code>curl -i -H 'Cookie: artsfolio_session=SESSION_COOKIE_VALUE' \
  https://artsfol.io/platform/admin/stats</code></pre></article>
    <article><h3>GET /platform/admin/audit-log</h3><p>Review platform audit events for security and administrative changes.</p><pre><code>curl -i -H 'Cookie: artsfolio_session=SESSION_COOKIE_VALUE' \
  https://artsfol.io/platform/admin/audit-log</code></pre></article>
    <article><h3>GET /platform/admin/audit-log.csv</h3><p>Export platform audit entries as CSV for review or archival.</p><pre><code>curl -OJ -H 'Cookie: artsfolio_session=SESSION_COOKIE_VALUE' \
  https://artsfol.io/platform/admin/audit-log.csv</code></pre></article>
</div>

<h2>Tenant public routes</h2>
<div class="feature-grid developer-route-grid">
    <article><h3>GET /</h3><p>Render your site public homepage on your site hostname. The platform tenant resolver chooses your site from the host.</p><pre><code>curl -i https://bxiie.com/</code></pre></article>
    <article><h3>GET /portfolio</h3><p>Render your site portfolio page. Section filters may be applied by query string when portfolio sections exist.</p><pre><code>curl -i 'https://bxiie.com/portfolio?section=sculpture'</code></pre></article>
    <article><h3>GET /artwork/{slug}</h3><p>Render a public artwork detail page by artwork slug. Use this for collector, press, and directory links to specific works.</p><pre><code>curl -i https://bxiie.com/artwork/example-work</code></pre></article>
    <article><h3>GET /about</h3><p>Render your site about page, including configured copy and exhibition/event content when present.</p><pre><code>curl -i https://bxiie.com/about</code></pre></article>
    <article><h3>GET /contact</h3><p>Render your site contact page and contact form.</p><pre><code>curl -i https://bxiie.com/contact</code></pre></article>
    <article><h3>POST /contact</h3><p>Submit a tenant contact message. The browser path is CSRF and rate-limit protected, and should be used through the rendered form.</p><pre><code>curl -i -X POST https://bxiie.com/contact \
  -d 'csrf_token=TOKEN_FROM_FORM' \
  -d 'name=Collector Name' \
  -d 'email=collector@example.com' \
  -d 'subject=Inquiry' \
  -d 'message=I am interested in this work.'</code></pre></article>
    <article><h3>POST /signup</h3><p>Submit a tenant mailing-list signup when that form is exposed by your site site. The request is tenant-scoped by hostname.</p><pre><code>curl -i -X POST https://bxiie.com/signup \
  -d 'csrf_token=TOKEN_FROM_FORM' \
  -d 'email=collector@example.com'</code></pre></article>
</div>

<h2>Your admin routes</h2>
<div class="feature-grid developer-route-grid">
    <article><h3>GET /admin</h3><p>Open the your admin dashboard for the current site hostname. Use this for site/content work, not global platform operations.</p><pre><code>curl -i -H 'Cookie: artsfolio_session=SESSION_COOKIE_VALUE' \
  https://bxiie.com/admin</code></pre></article>
    <article><h3>GET /admin/settings</h3><p>Edit tenant branding, public labels, CSS, page copy settings, slugs, colors, and public-site options.</p><pre><code>curl -i -H 'Cookie: artsfolio_session=SESSION_COOKIE_VALUE' \
  https://bxiie.com/admin/settings</code></pre></article>
    <article><h3>GET /admin/directory</h3><p>Configure tenant discovery opt-in, directory summary, and featured directory thumbnail selection.</p><pre><code>curl -i -H 'Cookie: artsfolio_session=SESSION_COOKIE_VALUE' \
  https://bxiie.com/admin/directory</code></pre></article>
    <article><h3>POST /admin/directory</h3><p>Save tenant directory settings. Use the browser form to preserve CSRF validation and audit logging.</p><pre><code>curl -i -X POST https://bxiie.com/admin/directory \
  -H 'Cookie: artsfolio_session=SESSION_COOKIE_VALUE' \
  -d 'csrf_token=TOKEN_FROM_FORM' \
  -d 'platform_directory_opt_in=1' \
  -d 'platform_directory_summary=Contemporary geometric work.'</code></pre></article>
    <article><h3>GET /admin/artworks</h3><p>Manage tenant artwork inventory, image uploads, metadata, publish status, and portfolio assignments.</p><pre><code>curl -i -H 'Cookie: artsfolio_session=SESSION_COOKIE_VALUE' \
  https://bxiie.com/admin/artworks</code></pre></article>
    <article><h3>GET /admin/portfolio-sections</h3><p>Manage portfolio sections and ordering. Use this before assigning or manually ordering artworks in public groupings.</p><pre><code>curl -i -H 'Cookie: artsfolio_session=SESSION_COOKIE_VALUE' \
  https://bxiie.com/admin/portfolio-sections</code></pre></article>
    <article><h3>GET /admin/events</h3><p>Manage exhibitions, fairs, talks, residencies, and other date-based public history.</p><pre><code>curl -i -H 'Cookie: artsfolio_session=SESSION_COOKIE_VALUE' \
  https://bxiie.com/admin/events</code></pre></article>
    <article><h3>GET /admin/stats</h3><p>Review tenant-scoped analytics and content engagement for the current site.</p><pre><code>curl -i -H 'Cookie: artsfolio_session=SESSION_COOKIE_VALUE' \
  https://bxiie.com/admin/stats</code></pre></article>
    <article><h3>GET /admin/audit-log</h3><p>Review tenant-scoped administrative and security events.</p><pre><code>curl -i -H 'Cookie: artsfolio_session=SESSION_COOKIE_VALUE' \
  https://bxiie.com/admin/audit-log</code></pre></article>
</div>
HTML;

        return Response::html($this->layout('Developer reference', $body, 'developer', $currentUser));
    }

    private function layout(string $title, string $body, string $active, ?array $currentUser): string
    {
        $safeTitle = self::escape($title);
        $nav = $this->nav($active, $currentUser !== null);
        $platformAdminLink = \App\Http\View\PlatformChrome::platformAdminLink();
        $canonicalNav = \App\Http\View\PlatformChrome::topNavigation('help');
        $tenantHelpAdminTopLink = $this->tenantHelpAdminTopLink($currentUser, $canonicalNav);
        if ($tenantHelpAdminTopLink !== '') {
            $canonicalNav = str_replace('</nav>', $tenantHelpAdminTopLink . '</nav>', $canonicalNav);
        }
        $platformCopyright = \App\Http\View\PlatformChrome::copyrightLine();
        $auth = $currentUser
            ? '<form class="plan-edit-form" method="post" action="/logout" class="inline-form"><button type="submit">Log out</button></form>'
            : '<a href="/login">Sign in</a>';

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{$safeTitle} | ArtsFolio Help</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/platform.css?v=20260708-logo-aspect">
    <link rel="stylesheet" href="/assets/platform-custom.css">
    <link rel="stylesheet" href="/assets/tenant-admin.css?v=20260717-help-sidebar-contrast">

<style id="help-sidebar-inline-contrast">
/* HELP_SIDEBAR_INLINE_CONTRAST
   Keep help navigation readable regardless of tenant or platform theme rules. */
.tenant-admin-sidebar {
    background: #151515 !important;
    color: #ffffff !important;
    border-right-color: rgba(255, 255, 255, 0.22) !important;
}

.tenant-admin-sidebar,
.tenant-admin-sidebar *,
.tenant-admin-sidebar a,
.tenant-admin-sidebar strong,
.tenant-admin-sidebar h1,
.tenant-admin-sidebar h2,
.tenant-admin-sidebar h3,
.tenant-admin-sidebar p,
.tenant-admin-sidebar span {
    color: #ffffff !important;
    opacity: 1 !important;
    text-shadow: none !important;
}

.tenant-admin-sidebar .tenant-admin-sidebar-title span,
.tenant-admin-sidebar .help-sidebar-subtitle {
    color: #e2e2e2 !important;
}

.tenant-admin-sidebar nav a {
    display: block;
    color: #ffffff !important;
    background: transparent !important;
}

.tenant-admin-sidebar nav a:hover,
.tenant-admin-sidebar nav a:focus-visible {
    color: #111111 !important;
    background: #f2f2f2 !important;
    outline: 3px solid #ffffff !important;
    outline-offset: 2px !important;
}

.tenant-admin-sidebar nav a.active,
.tenant-admin-sidebar nav a[aria-current="page"] {
    color: #111111 !important;
    background: #ffffff !important;
    font-weight: 900 !important;
}
/* End of help sidebar inline contrast. */
</style>

</head>
<body class="tenant-admin-page platform-help-page">
<header class="platform-header platform-help-header">
    <a class="platform-brand logo-brand compact-logo" href="/"><img src="/assets/logo_2.png" alt="ArtsFolio"></a>
    {$canonicalNav}
</header>
<div class="tenant-admin-shell">
    <aside class="tenant-admin-sidebar" aria-label="Help navigation">
        <div class="tenant-admin-sidebar-title"><strong>Help</strong><span>Guides and reference</span></div>
        {$nav}
    </aside>
    <main class="tenant-admin-main">
        <section class="tenant-admin-panel help-article"><h1>{$safeTitle}</h1>{$body}</section>
    </main>
</div>
<footer class="platform-footer"><span>{$platformCopyright}</span><nav><a href="/help">Help</a><a href="/terms">Terms</a><a href="/privacy">Privacy</a><a href="/contact">Contact</a></nav></footer>
</body>
</html>
HTML;
    }


    /**
     * Adds a signed-in Admin link on tenant help pages.
     *
     * PlatformChrome::topNavigation() only adds Admin for platform-role users.
     * Artists reading help on their own site still need a visible way back to
     * their site admin, so tenant-host help pages add a local /admin link.
     */
    private function tenantHelpAdminTopLink(?array $currentUser, string $canonicalNav): string
    {
        if (!$currentUser || str_contains($canonicalNav, 'platform-admin-top-link')) {
            return '';
        }

        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $host = preg_replace('/:\\d+$/', '', $host) ?: $host;
        $platformHosts = ['artsfol.io', 'www.artsfol.io'];

        if (in_array($host, $platformHosts, true)) {
            return '';
        }

        return '<a class="platform-admin-top-link" href="/admin">Admin</a>';
    }

    /**
     * Builds the help sidebar with stable machine slugs as array keys.
     *
     * A previous update accidentally wrote numeric arrays as [label, href].
     * The existing renderer expects slug => [href, label], so the browser saw
     * labels as URLs and opened paths such as /help/New%20admin%20setup%20tour.
     */
    private function nav(string $active, bool $loggedIn): string
    {
        $items = [
            'getting-started' => ['/help', 'Getting started'],
            'new-admin-tour' => ['/help/new-admin-tour', 'New admin setup tour'],
            'tenant-admin-functions' => ['/help/tenant-admin-functions', 'Your admin tools'],
            'branding' => ['/help/branding', 'Branding and content'],
            'artworks' => ['/help/artworks', 'Artwork and curation'],
            'events' => ['/help/events', 'Events and exhibitions'],
            'sales' => ['/help/sales', 'Sales and refunds'],
            'messages-email' => ['/help/messages-email', 'Messages and email signups'],
            'users-domains-billing' => ['/help/users-domains-billing', 'Users, domains, and billing'],
            'directory' => ['/help/directory', 'Artist directory'],
            'stats' => ['/help/stats', 'Your stats'],
            'audit' => ['/help/audit', 'Audit and diagnostics'],
            'training-videos' => ['/help/training-videos', 'Training videos'],
        ];

        if ($loggedIn) {
            $items['developer'] = ['/help/developer', 'Developer reference'];
        }

        $html = '<nav>';
        foreach ($items as $key => [$href, $label]) {
            $class = $active === $key ? ' class="active"' : '';
            $html .= '<a' . $class . ' href="' . self::escape($href) . '">' . self::escape($label) . '</a>';
        }
        $html .= '</nav><p class="admin-muted"><a href="/">← Back to ArtsFolio</a></p>';

        return $html;
    }

    /**
     * Accepts canonical slugs and repairs label-shaped URLs created by the
     * broken sidebar so existing pasted/bookmarked links still land correctly.
     */
    private function normalizeArticleSlug(string $slug): string
    {
        $slug = trim(rawurldecode($slug), '/');
        if ($slug === '') {
            return 'getting-started';
        }

        $canonical = strtolower(trim($slug));
        $canonical = preg_replace('/[^a-z0-9]+/', '-', $canonical) ?: 'getting-started';
        $canonical = trim($canonical, '-');

        $aliases = [
            'new-admin-setup-tour' => 'new-admin-tour',
            'tenant-function-index' => 'tenant-admin-functions',
            'branding-and-content' => 'branding',
            'artwork-and-curation' => 'artworks',
            'events-and-exhibitions' => 'events',
            'sales-and-refunds' => 'sales',
            'messages-and-email-signups' => 'messages-email',
            'users-domains-and-billing' => 'users-domains-billing',
            'artist-directory' => 'directory',
            'audit-and-diagnostics' => 'audit',
            'training-videos' => 'training-videos',
            'getting-started' => 'getting-started',
        ];

        return $aliases[$canonical] ?? $canonical;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Developer resources are internal operational documentation and require login.
     */
    private function isDeveloperResourceRequest(Request $request): bool
    {
        $path = $request->path();
        $topic = (string) ($_GET['topic'] ?? $_GET['article'] ?? '');

        return str_contains($path, 'developer')
            || str_contains($path, 'resources')
            || str_contains($topic, 'developer')
            || str_contains($topic, 'api')
            || str_contains($topic, 'webhook');
    }

}

// End of file.
