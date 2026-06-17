<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Request;
use App\Http\Response;
use App\Platform\Contact\PlatformContactMessageRepository;
use App\Platform\Email\EmailOutboxRepository;
use App\Services\FirstPartyCaptcha;
use PDO;
use Throwable;

/**
 * Public marketing pages for artsfol.io.
 *
 * These pages are intentionally unauthenticated. They explain the platform,
 * provide public support/legal routes, and expose an opt-in tenant directory.
 */
final class MarketingController
{
    private const PLATFORM_NOTIFICATION_EMAIL = 'info@artsfol.io';

    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function home(Request $request): Response
    {
        $tenantCards = $this->tenantCards(limit: 6);
        $imageMosaic = $this->imageMosaic(limit: 10);

        $captcha = FirstPartyCaptcha::render('platform_contact', 0, $this->turnstileSiteKey());

        $body = <<<HTML
<section class="platform-hero">
    <div>
        <p class="eyebrow">Artist portfolio software with sales baked in</p>
        <h1>A portfolio that works as hard as the art.</h1>
        <p class="hero-copy">ArtsFolio gives artists a fast, elegant portfolio, collector-ready artwork pages, email capture, contact tools, analytics, and room to grow into sales without rebuilding the whole machine later.</p>
        <div class="hero-actions">
            <a class="button primary" href="/signup">Start your portfolio</a>
            <a class="button secondary" href="/directory">Explore artists</a>
        </div>
    </div>
    <div class="hero-mosaic">
        {$imageMosaic}
    </div>
</section>

<section class="platform-section">
    <p class="eyebrow">Why ArtsFolio</p>
    <h2>Made for artists who need more than a pretty brochure.</h2>
    <div class="feature-grid">
        <article>
            <h3>Portfolio-first</h3>
            <p>Artwork pages, sections, public navigation, page images, exhibition history, and human-readable tenant CSS.</p>
        </article>
        <article>
            <h3>Collector-ready</h3>
            <p>Contact, interest capture, sales metadata, email signup, and future commerce flow without bolting on a junk drawer of plugins.</p>
        </article>
        <article>
            <h3>Admin that respects your time</h3>
            <p>Edit public content, organize artworks, review messages, manage subscribers, and see basic traffic from one place.</p>
        </article>
        <article>
            <h3>Custom domains</h3>
            <p>Use an artsfol.io subdomain by default or bring your own domain when your practice needs a more formal front door.</p>
        </article>
        <article>
            <h3>Search visibility</h3>
            <p>Clean public pages, useful metadata, sensible page structure, and a discovery directory for opted-in tenants.</p>
        </article>
        <article>
            <h3>Built to mature</h3>
            <p>OAuth, local login, tenant isolation, audit logs, and platform automation are part of the foundation, not a future apology.</p>
        </article>
    </div>
</section>

<section class="platform-section split">
    <div>
        <p class="eyebrow">New user flow</p>
        <h2>From blank wall to public portfolio.</h2>
    </div>
    <ol class="flow-list">
        <li><strong>Sign in</strong><span>Use Google, Facebook, or email/password.</span></li>
        <li><strong>Name your site</strong><span>Choose an artsfol.io subdomain or prepare a custom domain.</span></li>
        <li><strong>Add artwork</strong><span>Upload images, titles, medium, dimensions, sales status, and portfolio sections.</span></li>
        <li><strong>Publish pages</strong><span>Write home, about, contact, and exhibition content with optional HTML.</span></li>
        <li><strong>Invite collectors</strong><span>Enable email signup, contact forms, analytics, and directory opt-in.</span></li>
    </ol>
</section>

<section class="platform-section">
    <div class="section-heading-row">
        <div>
            <p class="eyebrow">Opted-in artists</p>
            <h2>Discover work already living on ArtsFolio.</h2>
        </div>
        <a class="text-link" href="/directory">View directory</a>
    </div>
    <div class="tenant-card-grid">
        {$tenantCards}
    </div>
</section>

<section class="platform-cta">
    <h2>Your art deserves a site that can keep up.</h2>
    <p>Start simple. Grow into sales, analytics, custom domains, and collector workflows without changing platforms every time your practice evolves.</p>
    <a class="button primary" href="/signup">Create your ArtsFolio site</a>
</section>
HTML;

        return $this->page('ArtsFolio | Artist portfolio and sales platform', $body, 'home');
    }

    public function directory(Request $request): Response
    {
        $cards = $this->tenantCards(limit: 100);

        $captcha = FirstPartyCaptcha::render('platform_contact', 0, $this->turnstileSiteKey());

        $body = <<<HTML
<section class="platform-page-heading">
    <p class="eyebrow">Artist directory</p>
    <h1>Opted-in ArtsFolio sites</h1>
    <p>These artists have chosen to appear in the public ArtsFolio directory.</p>
</section>
<div class="tenant-card-grid directory-grid">
    {$cards}
</div>
HTML;

        return $this->page('Artist Directory | ArtsFolio', $body, 'directory');
    }

    public function signup(Request $request): Response
    {
        $captcha = FirstPartyCaptcha::render('platform_contact', 0, $this->turnstileSiteKey());

        $body = <<<HTML
<section class="signup-panel">
    <p class="eyebrow">Start your site</p>
    <h1>Create your ArtsFolio account</h1>
    <p>Use SSO for the fastest start, or create a local account with email and password.</p>
    <div class="signup-actions">
        <a class="button primary" href="/auth/google">Continue with Google</a>
        <a class="button primary" href="/auth/facebook">Continue with Facebook</a>
        <a class="button secondary" href="/register">Use email/password</a>
    </div>
    <ol class="flow-list compact">
        <li><strong>Account</strong><span>Create or sign in.</span></li>
        <li><strong>Tenant</strong><span>Name your portfolio and choose a subdomain.</span></li>
        <li><strong>Artwork</strong><span>Upload first images and organize sections.</span></li>
        <li><strong>Publish</strong><span>Share your public site.</span></li>
    </ol>
</section>
HTML;

        return $this->page('Sign up | ArtsFolio', $body, 'signup');
    }

    public function contact(Request $request): Response
    {
        $notice = '';
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $message = trim((string) ($_POST['message'] ?? ''));
            $captcha = FirstPartyCaptcha::verify('platform_contact', 0, $_POST, $this->turnstileSecretKey(), $this->requestIp($request));
            if (!$captcha->passed) {
                $notice = '<p class="error" role="alert">Please complete the human confirmation.</p>';
            } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $message === '') {
                $notice = '<p class="error" role="alert">Please enter a valid email address and message.</p>';
            } else {
                try {
                    $this->recordPlatformContact($request, $name, $email, $message);
                    $notice = '<p class="notice" role="status">Thank you. Your message has been sent.</p>';
                } catch (Throwable $exception) {
                    error_log('ArtsFolio platform contact notification queue failed: ' . $exception->getMessage());
                    $notice = '<p class="error" role="alert">Your message could not be queued. Please email info@artsfol.io directly.</p>';
                }
            }
        }

        $captcha = FirstPartyCaptcha::render('platform_contact', 0, $this->turnstileSiteKey());

        $body = <<<HTML
<section class="platform-page-heading">
    <p class="eyebrow">Contact</p>
    <h1>Talk to ArtsFolio</h1>
    <p>For platform questions, onboarding help, billing, custom domains, or partnership inquiries, contact the ArtsFolio team.</p>
</section>
{$notice}
<form class="plan-edit-form" class="platform-form js-submit-form" method="post" action="/contact">
    <label>Name <input name="name" autocomplete="name"></label>
    <label>Email <input name="email" type="email" autocomplete="email" required></label>
    <label>Message <textarea name="message" rows="8" required></textarea></label>
    {$captcha}
    <button type="submit" data-loading-label="Sending…">Send message</button>
    <p class="form-progress" aria-live="polite">Sending message…</p>
</form>
HTML;

        return $this->page('Contact | ArtsFolio', $body, 'contact');
    }


    /**
     * Persists public platform contact submissions, then queues an admin notice.
     *
     * This intentionally mirrors tenant contacts: the message lives in
     * contact_messages for admin workflow, while email_outbox is only the
     * notification transport.
     */
    private function recordPlatformContact(Request $request, string $name, string $email, string $message): int
    {
        $messageId = (new PlatformContactMessageRepository($this->pdo))->create(
            senderName: $name !== '' ? $name : 'Platform contact visitor',
            senderEmail: $email,
            message: $message,
            subject: 'ArtsFolio platform contact',
            ipAddress: $this->requestIp($request),
            userAgent: (string) $request->server('HTTP_USER_AGENT', ''),
        );

        $this->queuePlatformContactNotification($messageId, $name, $email, $message);

        return $messageId;
    }

    /**
     * Queue public platform contact submissions into the normal email outbox.
     */
    private function queuePlatformContactNotification(int $messageId, string $name, string $email, string $message): int
    {
        $displayName = $name !== '' ? $name : 'Platform contact visitor';
        $subject = 'New ArtsFolio platform contact from ' . $displayName;
        $body = implode("\n", [
            'A public ArtsFolio platform contact form was submitted.',
            '',
            'Platform contact message ID: ' . $messageId,
            'Manage it: /platform/admin/contacts',
            '',
            'From: ' . $displayName . ' <' . $email . '>',
            'Reply to the sender manually at: ' . $email,
            '',
            'Message:',
            $message,
            '',
        ]);

        return (new EmailOutboxRepository($this->pdo))->queue(
            recipientEmail: $this->platformNotificationEmail(),
            subject: $subject,
            bodyText: $body,
            templateKey: 'platform.contact_notification',
        );
    }

    /**
     * Resolve the platform contact destination without using test domains.
     */
    private function platformNotificationEmail(): string
    {
        $configured = strtolower(trim((string) (getenv('ARTSFOLIO_PLATFORM_CONTACT_EMAIL') ?: '')));
        if ($configured !== '' && filter_var($configured, FILTER_VALIDATE_EMAIL)) {
            return $configured;
        }

        $fallback = strtolower(trim((string) (getenv('ARTSFOLIO_DEFAULT_NOTIFICATION_EMAIL') ?: '')));
        if ($fallback !== '' && filter_var($fallback, FILTER_VALIDATE_EMAIL)) {
            return $fallback;
        }

        return self::PLATFORM_NOTIFICATION_EMAIL;
    }

    public function help(Request $request): Response
    {
        $captcha = FirstPartyCaptcha::render('platform_contact', 0, $this->turnstileSiteKey());

        $body = <<<HTML
<section class="platform-page-heading">
    <p class="eyebrow">Help center</p>
    <h1>ArtsFolio help</h1>
    <p>Guidance for setting up a tenant, publishing a polished artist site, managing inquiries and subscribers, and using discovery, stats, and audit tools.</p>
</section>

<section class="platform-section docs-section">
    <h2>First-time setup</h2>
    <ol class="flow-list compact">
        <li><strong>Sign in</strong><span>Use Google, Facebook, or local email/password.</span></li>
        <li><strong>Create tenant</strong><span>Choose site title, artist name, and an artsfol.io subdomain.</span></li>
        <li><strong>Brand the site</strong><span>Set browser title, public labels, copyright, top bar, colors, background, and tenant CSS.</span></li>
        <li><strong>Add public copy</strong><span>Write home, about, contact, exhibitions, and social/contact content.</span></li>
        <li><strong>Add artwork</strong><span>Upload images, assign portfolio sections, fill metadata, and publish selected works.</span></li>
    </ol>
</section>

<section class="platform-section docs-section">
    <h2>Directory and discovery</h2>
    <p>Directory visibility is opt-in. Tenants are hidden from the ArtsFolio directory and random front-page image mosaic until an admin enables discovery.</p>
    <ol class="flow-list compact">
        <li><strong>Open tenant admin</strong><span>Sign in and go to the tenant admin dashboard.</span></li>
        <li><strong>Open Discovery</strong><span>Use the left admin navigation tab named Discovery.</span></li>
        <li><strong>Opt in</strong><span>Check “Show this tenant in the public ArtsFolio directory.”</span></li>
        <li><strong>Add summary</strong><span>Write a short public description of the artist/site.</span></li>
        <li><strong>Save</strong><span>Published public artworks may then be eligible for artsfol.io directory and mosaic display.</span></li>
    </ol>
</section>

<section class="platform-section docs-section">
    <h2>Admin sections explained</h2>
    <div class="feature-grid">
        <article><h3>Settings</h3><p>Branding, browser metadata, copyright, colors, top bar, tenant CSS, slugs, and public page labels.</p></article>
        <article><h3>Content</h3><p>Home introduction, about text, contact text, page images, social links, and formatted public copy.</p></article>
        <article><h3>Artworks</h3><p>Artwork upload, editing, status, metadata, sales fields, and portfolio assignment.</p></article>
        <article><h3>Portfolio sections</h3><p>Named groupings for artworks. Sections can be ordered and optionally shown as public tabs.</p></article>
        <article><h3>Events</h3><p>Exhibitions, fairs, residencies, shows, and chronology items shown on public pages.</p></article>
        <article><h3>Messages</h3><p>Contact submissions with search, status filtering, archive, hard delete, and CSV export.</p></article>
        <article><h3>Email signups</h3><p>Subscribers, import/export, consent status, unsubscribe, and hard delete tools.</p></article>
        <article><h3>Stats</h3><p>Traffic, artwork views, day/hour graphs, referrers, and location rollups when analytics is writing events.</p></article>
        <article><h3>Audit log</h3><p>Security and admin activity trail for important changes.</p></article>
        <article><h3>Discovery</h3><p>Opt-in controls for public platform directory and random image inclusion.</p></article>
    </div>
</section>

<section class="platform-section docs-section">
    <h2>Launch checklist</h2>
    <ol class="flow-list compact">
        <li><strong>Mobile review</strong><span>Check home, portfolio, artwork detail, about, contact, and events on a phone.</span></li>
        <li><strong>Image review</strong><span>Confirm images load quickly and are not giant originals on small pages.</span></li>
        <li><strong>Contact test</strong><span>Submit a contact message and confirm it appears in admin.</span></li>
        <li><strong>Email test</strong><span>Subscribe publicly and confirm consent status in admin.</span></li>
        <li><strong>Stats test</strong><span>Visit public pages and confirm events appear in Stats.</span></li>
        <li><strong>Audit test</strong><span>Save a setting and confirm a corresponding audit row appears.</span></li>
    </ol>
</section>
HTML;

        return $this->page('Help | ArtsFolio', $body, 'help');
    }


    public function developer(Request $request): Response
    {
        if (!$this->currentUser()) {
            return new Response('', 302, ['Location' => '/login?next=/developer']);
        }

        $captcha = FirstPartyCaptcha::render('platform_contact', 0, $this->turnstileSiteKey());

        $body = <<<HTML
<section class="platform-page-heading">
    <p class="eyebrow">Developer documentation</p>
    <h1>Build against the ArtsFolio platform.</h1>
    <p>This section is for third-party developers, agencies, integration partners, and technical tenant teams working with ArtsFolio APIs and exports.</p>
</section>

<section class="platform-section docs-section">
    <h2>API integration overview</h2>
    <p>ArtsFolio should be treated as a tenant-scoped platform. API clients must authenticate, resolve a tenant context, enforce tenant permissions, and avoid assuming globally unique human-facing slugs.</p>
    <div class="feature-grid">
        <article><h3>Artwork sync</h3><p>Read or update artwork metadata, publication status, inventory/sales fields, section assignments, and display order.</p></article>
        <article><h3>Media workflows</h3><p>Upload optimized artwork images, associate assets with artworks, and prepare derivatives for thumbnails, page images, and future CDN delivery.</p></article>
        <article><h3>Collector flow</h3><p>Integrate contact messages, artwork inquiries, email signups, consent changes, and future collector records with external CRM tools.</p></article>
        <article><h3>Analytics export</h3><p>Export tenant-scoped events, artwork hits, referrers, locations, and day/hour aggregates when public tracking is enabled.</p></article>
        <article><h3>Custom domains</h3><p>Verify DNS, check domain status, and monitor generated vhost artifacts using platform-approved automation.</p></article>
        <article><h3>Background jobs</h3><p>Long-running work should be queued, retried, and observable through job attempts and worker heartbeats.</p></article>
    </div>
</section>

<section class="platform-section docs-section">
    <h2>Authentication and authorization</h2>
    <ol class="flow-list compact">
        <li><strong>Browser admin</strong><span>Session-based, CSRF-protected, role-checked tenant admin routes.</span></li>
        <li><strong>API access</strong><span>OAuth2 bearer tokens or platform-issued scoped credentials for integrations.</span></li>
        <li><strong>Tenant scope</strong><span>Every integration request must resolve to one tenant or an explicit platform-level permission.</span></li>
        <li><strong>Auditability</strong><span>Mutations should create audit records containing actor, action, entity type, entity ID, IP, and details.</span></li>
    </ol>
</section>

<section class="platform-section docs-section">
    <h2>Resource model</h2>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Area</th><th>Resources</th><th>Integration notes</th></tr></thead>
            <tbody>
                <tr><td>Tenant</td><td>tenant profile, settings, domains</td><td>Use tenant IDs for internal identity; slugs/domains are routing affordances.</td></tr>
                <tr><td>Artwork</td><td>artworks, media assets, sections</td><td>Imports should preserve source IDs and be safe to rerun.</td></tr>
                <tr><td>Engagement</td><td>messages, subscribers, consent</td><td>Never overwrite consent history blindly.</td></tr>
                <tr><td>Analytics</td><td>events, views, location aggregates</td><td>Events must be written before dashboards can show useful graphs.</td></tr>
                <tr><td>Operations</td><td>jobs, attempts, heartbeats, audit logs</td><td>Operational visibility matters as much as happy-path API responses.</td></tr>
            </tbody>
        </table>
    </div>
</section>

<section class="platform-section docs-section">
    <h2>Rules for third-party developers</h2>
    <p>Do not scrape admin pages. Do not put secrets in browser code. Do not bypass tenant permissions. Prefer idempotent imports, explicit dry runs, structured logs, and small reversible batches. If an integration changes public content, make the change traceable.</p>
</section>
HTML;

        return $this->page('Developers | ArtsFolio', $body, 'developer');
    }


    public function terms(Request $request): Response
    {
        $body = <<<'HTML'
<section class="platform-page-heading">
    <p class="eyebrow">Legal</p>
    <h1>Terms and Conditions</h1>
    <p>These Terms govern access to and use of ArtsFolio, including artist portfolio sites, public pages, admin tools, email capture, contact forms, directory features, and sales workflows.</p>
    <p><strong>Effective date:</strong> June 16, 2026</p>
</section>

<div class="legal-copy">
    <h2>1. Acceptance of these Terms</h2>
    <p>By creating an account, administering a tenant site, visiting a public ArtsFolio page, contacting an artist, joining an email list, purchasing artwork, or otherwise using ArtsFolio, you agree to these Terms and to the ArtsFolio Privacy Policy. If you use ArtsFolio on behalf of an organization, gallery, collective, studio, or other entity, you represent that you have authority to bind that entity.</p>

    <h2>2. What ArtsFolio provides</h2>
    <p>ArtsFolio provides hosted artist portfolio software, tenant websites, custom-domain support, public artwork pages, email signup tools, contact tools, analytics, directory features, administrative workflows, and optional sales functionality. ArtsFolio may add, modify, suspend, or discontinue features as the platform develops.</p>

    <h2>3. Accounts, roles, and security</h2>
    <p>You are responsible for maintaining the confidentiality of login credentials, OAuth accounts, administrator invitations, and devices used to access ArtsFolio. You must provide accurate account information, keep contact information current, and promptly notify ArtsFolio of suspected unauthorized access. Tenant owners are responsible for users they invite and for assigning appropriate roles.</p>

    <h2>4. Tenant sites and public content</h2>
    <p>Tenant administrators control the content published on their tenant sites, including artwork images, artwork metadata, statements, biography, events, contact information, pricing, availability, and custom styling. Published tenant content is public and may be viewed, indexed, cached, linked, shared, or archived by visitors and third-party services.</p>

    <h2>5. Artist ownership and license to ArtsFolio</h2>
    <p>Artists and tenants retain ownership of artwork, images, text, trademarks, and other content they upload or publish, subject to any rights they have granted elsewhere. By uploading, publishing, or storing content on ArtsFolio, you grant ArtsFolio a non-exclusive, worldwide, royalty-free license to host, store, reproduce, resize, cache, transmit, display, format, and otherwise use that content as needed to operate, secure, support, improve, and promote the platform and the applicable tenant site.</p>

    <h2>6. Use of public images, listings, and promotion</h2>
    <p>If a tenant publishes images or other content publicly, ArtsFolio may display that public content on that tenant site and in normal platform surfaces that support the tenant site. If a tenant opts into platform discovery, directory listing, featured artist modules, random image features, or similar public discovery features, ArtsFolio may use public tenant names, site links, summaries, thumbnails, and publicly visible artwork images on ArtsFolio home pages, directory pages, discovery pages, social previews, internal promotional placements, and platform marketing surfaces. Tenants can opt out of directory/discovery features in tenant admin where those controls are provided.</p>

    <h2>7. Directory opt-in and visibility</h2>
    <p>Tenant sites are not required to appear in the public ArtsFolio directory. When a tenant opts in, ArtsFolio may list the tenant name, public URL, summary, thumbnail, and selected public content. Opting out removes the tenant from future platform directory displays, but previously cached pages, screenshots, links, social previews, search engine entries, backups, and external references may persist outside ArtsFolio control.</p>

    <h2>8. Sales, purchases, and artist responsibility</h2>
    <p>ArtsFolio may provide tools for artists to list artwork for sale, accept inquiries, manage carts, record orders, and use payment workflows. Unless ArtsFolio expressly states otherwise in writing, the artist or tenant is the seller of record and is responsible for artwork descriptions, availability, pricing, taxes, shipping, insurance, fulfillment, authenticity, condition, refunds, exchanges, customer service, export/import restrictions, and compliance with applicable laws. Buyers should review artwork details, shipping terms, and artist policies before purchasing.</p>

    <h2>9. Payments, fees, commissions, taxes, and chargebacks</h2>
    <p>Paid plans, platform fees, payment-card processing fees, sales commissions, transaction fees, shipping charges, taxes, and other amounts may apply depending on plan and configuration. ArtsFolio may use third-party payment processors. Payment processors may have their own terms, privacy policies, fees, fraud controls, dispute processes, payout timing, and identity-verification requirements. Tenants are responsible for taxes and reporting obligations associated with their sales unless a written agreement says otherwise. ArtsFolio may offset, reverse, or withhold amounts related to refunds, chargebacks, suspected fraud, processor actions, or unpaid fees.</p>

    <h2>10. Shipping, returns, damage, and disputes</h2>
    <p>Unless ArtsFolio expressly agrees otherwise, artists and tenants are responsible for packing, shipping, insurance, delivery communication, returns, damage claims, customs forms, import/export compliance, and buyer disputes. ArtsFolio may provide workflow tools but does not guarantee delivery, buyer satisfaction, artwork condition, or resolution of buyer-seller disputes.</p>

    <h2>11. Plans, billing, trials, complimentary access, and signup codes</h2>
    <p>ArtsFolio may offer free plans, paid plans, trials, complimentary tenants, promotional access, and signup codes. Plan features, limits, pricing, and eligibility may change. Signup codes and free-access codes may expire, be revoked, be limited to certain users or redemption counts, or be changed if misused. Complimentary or trial access does not waive sales commissions, payment-card fees, taxes, chargebacks, shipping, or other third-party costs unless expressly stated.</p>

    <h2>12. Acceptable use</h2>
    <p>You may not use ArtsFolio to violate laws, infringe rights, upload malware, send spam, scrape or attack the platform, interfere with other tenants, impersonate others, misrepresent artwork or authorship, sell prohibited goods, process fraudulent orders, harass people, or publish content that is unlawful, exploitative, defamatory, abusive, or otherwise unsafe for the platform. ArtsFolio may remove content, limit features, suspend accounts, disable tenant sites, cancel orders, or terminate access when needed to protect users, tenants, buyers, artists, the public, or the platform.</p>

    <h2>13. Copyright and intellectual-property complaints</h2>
    <p>If you believe content on ArtsFolio infringes your rights, contact ArtsFolio with enough detail to identify the content, the rights claimed, your contact information, and the action requested. ArtsFolio may remove or restrict content while reviewing a complaint and may require additional information. Tenants are responsible for ensuring they have rights to upload and publish their content.</p>

    <h2>14. Third-party services</h2>
    <p>ArtsFolio may integrate with third-party services such as domain/DNS providers, OAuth identity providers, email providers, payment processors, analytics services, CAPTCHA providers, maps, storage, hosting, and other operational vendors. Third-party services are governed by their own terms and privacy practices. ArtsFolio is not responsible for third-party outages, policy changes, fees, or account decisions.</p>

    <h2>15. Data, analytics, backups, and logs</h2>
    <p>ArtsFolio may collect operational logs, analytics, security events, audit records, contact messages, email signup records, order records, payment status, and similar data to operate, secure, debug, support, and improve the platform. Backups and logs may retain information for a limited period after public deletion or account closure.</p>

    <h2>16. Suspension, termination, and removal</h2>
    <p>You may stop using ArtsFolio at any time. ArtsFolio may suspend or terminate accounts, tenant sites, public content, sales features, or access when required by law, payment risk, security risk, nonpayment, suspected abuse, infringement claims, platform integrity, or violation of these Terms. Termination may not erase completed transaction records, audit logs, legal records, backups, or information ArtsFolio must retain for legitimate business, security, compliance, or dispute-resolution purposes.</p>

    <h2>17. Disclaimers</h2>
    <p>ArtsFolio is provided on an “as is” and “as available” basis. ArtsFolio does not guarantee uninterrupted service, search ranking, sales volume, buyer behavior, artistic success, custom-domain availability, payment approval, email deliverability, data-loss immunity, or compatibility with every browser, device, integration, or third-party service.</p>

    <h2>18. Limitation of liability</h2>
    <p>To the maximum extent permitted by law, ArtsFolio and its owners, operators, employees, contractors, and service providers will not be liable for indirect, incidental, consequential, special, exemplary, or punitive damages, or for lost profits, lost sales, lost data, lost goodwill, business interruption, buyer-seller disputes, payment processor actions, third-party outages, or unauthorized access that occurs despite reasonable safeguards.</p>

    <h2>19. Indemnification</h2>
    <p>You agree to indemnify and hold ArtsFolio harmless from claims, losses, damages, liabilities, costs, and expenses arising from your content, tenant site, sales activity, buyer interactions, misuse of the platform, violation of these Terms, violation of law, or infringement of third-party rights.</p>

    <h2>20. Changes to these Terms</h2>
    <p>ArtsFolio may update these Terms as the platform changes. The effective date will be updated when material changes are made. Continued use of ArtsFolio after changes become effective means you accept the updated Terms.</p>

    <h2>21. Governing law and contact</h2>
    <p>These Terms are intended to be governed by the laws of Vermont, United States, without regard to conflict-of-law principles, unless applicable law requires otherwise. Questions about these Terms can be sent through the ArtsFolio contact page or by email to info@artsfol.io.</p>
</div>
HTML;

        return $this->page('Terms and Conditions | ArtsFolio', $body, 'terms');
    }

    public function privacy(Request $request): Response
    {
        $body = <<<'HTML'
<section class="platform-page-heading">
    <p class="eyebrow">Privacy</p>
    <h1>Privacy Policy</h1>
    <p>This Privacy Policy explains how ArtsFolio collects, uses, shares, retains, and deletes information when people use ArtsFolio platform pages, tenant sites, admin tools, contact forms, email signup tools, OAuth login, analytics, and sales workflows.</p>
    <p><strong>Effective date:</strong> June 16, 2026</p>
</section>

<div class="legal-copy">
    <h2>1. Who this policy covers</h2>
    <p>This policy covers ArtsFolio users, tenant administrators, artists, invited users, public site visitors, buyers, collectors, email-list subscribers, people who submit contact forms, and people who authenticate through Google, Facebook, or another supported identity provider.</p>

    <h2>2. Information you provide</h2>
    <p>ArtsFolio may collect account information, names, email addresses, passwords or password hashes for local accounts, OAuth identifiers, tenant names, site slugs, custom domains, public profile text, artwork records, artwork images, event and exhibition information, contact details, email signup records, buyer and order information, support messages, administrative settings, and content uploaded or entered into tenant sites.</p>

    <h2>3. Information collected automatically</h2>
    <p>ArtsFolio may collect IP address, user agent, request path, referrer, timestamps, session identifiers, security events, audit logs, device/browser information, approximate location derived from IP address, page views, artwork views, contact-form metadata, email signup metadata, and operational diagnostics needed to run and secure the service.</p>

    <h2>4. Public content</h2>
    <p>Published tenant content is public. This may include artwork images, titles, descriptions, prices, availability, artist statements, biographies, contact links, events, and other public page content. Public content may be viewed, indexed, cached, shared, copied, or archived by visitors and third-party services outside ArtsFolio control.</p>

    <h2>5. Directory and home-page discovery</h2>
    <p>If a tenant opts into ArtsFolio directory or discovery features, ArtsFolio may use public tenant names, summaries, public URLs, thumbnails, and public artwork images on ArtsFolio home pages, directory pages, discovery modules, social previews, and platform marketing surfaces. Tenants can opt out where tenant admin controls are provided, but external caches and prior references may persist.</p>

    <h2>6. OAuth login data</h2>
    <p>When you sign in with Google, Facebook, or another supported identity provider, ArtsFolio may receive identifiers, email address, email-verification status, display name, profile information, and authentication tokens needed to complete login. ArtsFolio uses this information to create or access your ArtsFolio account, link identities, prevent duplicate accounts, and secure authentication. ArtsFolio does not use Google or Facebook login data for unrelated advertising.</p>

    <h2>7. Payments and sales data</h2>
    <p>When sales features are used, ArtsFolio may process order records, cart records, artwork details, prices, fees, commission information, fulfillment status, buyer contact information, shipping information, payout/accounting metadata, refund or chargeback metadata, and payment status. Payment-card details may be handled by third-party payment processors rather than stored directly by ArtsFolio.</p>

    <h2>8. Contact forms and email lists</h2>
    <p>When visitors submit tenant contact forms or join tenant email lists, ArtsFolio stores the submitted information for the tenant to review and act on. Tenant administrators can see and manage their own messages and subscriber records. ArtsFolio may also queue notification emails to tenant administrators unless a duplicate active signup does not require another notification.</p>

    <h2>9. Cookies, sessions, CAPTCHA, and security tools</h2>
    <p>ArtsFolio uses cookies and similar technologies for login sessions, CSRF protection, form safety, list-filter preferences, CAPTCHA or human-confirmation checks, fraud prevention, rate limiting, and operational security. Platform forms may use Cloudflare Turnstile. Tenant forms may use ArtsFolio built-in CAPTCHA and anti-spam controls.</p>

    <h2>10. How ArtsFolio uses information</h2>
    <p>ArtsFolio uses information to provide tenant sites, authenticate users, operate admin tools, process contact messages, manage email signups, support sales workflows, show public content, provide analytics, maintain audit logs, send operational emails, provide support, improve product reliability, prevent abuse, comply with legal obligations, and enforce Terms.</p>

    <h2>11. How information is shared</h2>
    <p>ArtsFolio may share information with tenant administrators, invited tenant users, buyers and sellers as needed for transactions, service providers that host or operate the platform, email providers, payment processors, OAuth providers, CAPTCHA/security providers, analytics/diagnostic providers, professional advisers, law enforcement or legal recipients when required, and successor entities in connection with a business transfer. ArtsFolio does not sell personal information to advertisers.</p>

    <h2>12. Tenant responsibility</h2>
    <p>Tenant administrators are responsible for how they use exported contact messages, email lists, buyer information, and other tenant-controlled data. Tenants must comply with applicable privacy, consumer protection, tax, email-marketing, and sales laws when using data collected through their sites.</p>

    <h2>13. Retention</h2>
    <p>ArtsFolio retains information for as long as needed to provide the service, support tenant sites, maintain security and auditability, comply with legal and financial obligations, resolve disputes, enforce agreements, and maintain backups. Deleted content may remain in backups, logs, transaction records, audit records, or security records for a limited period or where retention is legally or operationally necessary.</p>

    <h2>14. Data deletion instructions</h2>
    <p>You may request deletion of your ArtsFolio account data, OAuth-linked identity data, contact-form submissions, email-list subscriber records, or tenant content by contacting ArtsFolio at info@artsfol.io or through the ArtsFolio contact page. Use the subject line “Data deletion request” and include the email address associated with the account, the tenant/site involved, and the data you want deleted. ArtsFolio may need to verify your identity or tenant authority before deleting data.</p>
    <p>If you used Facebook Login and want ArtsFolio to delete data received from Facebook, send a request to info@artsfol.io with the subject “Facebook data deletion request” and include the email address used for login. If you used Google Login and want ArtsFolio to delete data received from Google, send a request with the subject “Google data deletion request.” You may also disconnect ArtsFolio from your Google or Facebook account through those providers, but provider-side disconnection does not automatically delete ArtsFolio records already created in ArtsFolio.</p>
    <p>Some information may be retained when necessary for security, fraud prevention, transaction records, tax/accounting records, chargebacks, dispute resolution, audit logs, backups, legal compliance, or legitimate platform operations. When deletion is not possible, ArtsFolio may restrict, anonymize, or minimize information where practical.</p>

    <h2>15. Your choices</h2>
    <p>You may update account information, change tenant public content, unpublish content, opt out of directory features, unsubscribe from email lists where unsubscribe tools are available, ask a tenant to remove subscriber/contact records, request account deletion, or contact ArtsFolio for help. Browser settings may allow you to block some cookies, but required cookies are necessary for login and form protection.</p>

    <h2>16. Security</h2>
    <p>ArtsFolio uses technical and organizational measures intended to protect information, including authentication controls, session protections, CSRF protections, audit logs, restricted admin areas, database-backed permissions, and operational monitoring. No system is perfectly secure, and ArtsFolio cannot guarantee that unauthorized access, disclosure, or loss will never occur.</p>

    <h2>17. Children</h2>
    <p>ArtsFolio is not intended for children under 13. Children should not create accounts, administer tenant sites, purchase artwork, or submit personal information through ArtsFolio without appropriate consent where required by law.</p>

    <h2>18. International users</h2>
    <p>ArtsFolio is operated from the United States. If you use ArtsFolio from another country, information may be processed in the United States or other locations where service providers operate.</p>

    <h2>19. Changes to this Privacy Policy</h2>
    <p>ArtsFolio may update this Privacy Policy as the platform, laws, vendors, or data practices change. The effective date will be updated when material changes are made.</p>

    <h2>20. Contact</h2>
    <p>Questions, privacy requests, and data deletion requests can be sent to info@artsfol.io or through the ArtsFolio contact page.</p>
</div>
HTML;

        return $this->page('Privacy Policy | ArtsFolio', $body, 'privacy');
    }


    private function page(string $title, string $body, string $active): Response
    {
        $turnstileScript = FirstPartyCaptcha::isConfigured($this->turnstileSiteKey()) ? '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>' : '';
        $platformAdminLink = \App\Http\View\PlatformChrome::platformAdminLink();
        $activeClass = static fn (string $key): string => $active === $key ? ' class="active"' : '';
        $loggedIn = (bool) $this->currentUser();
        $authLink = $loggedIn ? '' : '<a class="login-link" href="/login">Sign in</a>';
        $developerLink = $loggedIn ? '<a' . $activeClass('developer') . ' href="/developer">Developers</a>' : '';
        $developerFooterLink = $loggedIn ? '<a href="/developer">Developers</a>' : '';

        $html = <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{$this->escape($title)}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="ArtsFolio is an artist portfolio and sales platform for working artists.">
    <link rel="stylesheet" href="/assets/platform.css">
    <link rel="stylesheet" href="/assets/platform-custom.css">
    {$turnstileScript}
</head>
<body>
<header class="platform-header">
    <a class="platform-brand" href="/">ArtsFolio</a>
    <nav>
        <a{$activeClass('home')} href="/">Home</a>
        <a{$activeClass('pricing')} href="/pricing">Pricing</a>
        <a{$activeClass('directory')} href="/directory">Artists</a>
        <a{$activeClass('help')} href="/help">Help</a>
        {$developerLink}
        <a{$activeClass('contact')} href="/contact">Contact</a>
        {$platformAdminLink}
        {$authLink}
    </nav>
</header>
<main>
{$body}
</main>
<footer class="platform-footer">
    <span>{$this->platformCopyrightLine()}</span>
    <nav>
        <a href="/help">Help</a>
        {$developerFooterLink}
        <a href="/terms">Terms</a>
        <a href="/privacy">Privacy</a>
        <a href="/contact">Contact</a>
    </nav>
</footer>
{$this->platformInteractionScript()}
<script src="/assets/platform.js" defer></script></body>
</html>
HTML;

        return Response::html($html);
    }



    /**
     * Returns the configured Cloudflare Turnstile site key for platform forms.
     */
    private function turnstileSiteKey(): string
    {
        return $this->platformSetting('turnstile_site_key') ?: (string) getenv('ARTSFOLIO_TURNSTILE_SITE_KEY');
    }

    /**
     * Returns the configured Cloudflare Turnstile secret key for platform forms.
     */
    private function turnstileSecretKey(): string
    {
        return $this->platformSetting('turnstile_secret_key') ?: (string) getenv('ARTSFOLIO_TURNSTILE_SECRET_KEY');
    }

    private function platformSetting(string $key): string
    {
        try {
            $stmt = $this->pdo->prepare('SELECT setting_value FROM platform_settings WHERE setting_key = :setting_key LIMIT 1');
            $stmt->execute(['setting_key' => $key]);

            return trim((string) ($stmt->fetchColumn() ?: ''));
        } catch (Throwable) {
            return '';
        }
    }

    private function requestIp(Request $request): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            $value = trim((string) $request->server($key, ''));
            if ($value === '') {
                continue;
            }

            $first = trim(explode(',', $value)[0]);
            if (filter_var($first, FILTER_VALIDATE_IP)) {
                return $first;
            }
        }

        return '';
    }

    private function platformInteractionScript(): string
    {
        return <<<'HTML'
<script>
(function () {
  document.querySelectorAll('.js-submit-form').forEach(function (form) {
    form.addEventListener('submit', function () {
      var button = form.querySelector('button[type="submit"]');
      form.classList.add('is-submitting');
      if (button) {
        button.disabled = true;
        button.textContent = button.getAttribute('data-loading-label') || 'Sending…';
      }
    });
  });
})();
</script>
HTML;
    }

    private function platformCopyrightLine(): string
    {
        try {
            $stmt = $this->pdo->prepare("SELECT setting_value FROM platform_settings WHERE setting_key = 'platform_footer_copyright_html' LIMIT 1");
            $stmt->execute();
            $value = trim((string) ($stmt->fetchColumn() ?: ''));
            if ($value !== '') {
                return str_replace('{year}', $this->escape(date('Y')), $this->allowSafeInlineHtml($value));
            }
        } catch (Throwable) {
        }

        return '© ' . $this->escape(date('Y')) . ' artsfol.io';
    }

    private function allowSafeInlineHtml(string $html): string
    {
        $html = strip_tags($html, '<a><strong><em><span><br>');
        $html = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
        $html = preg_replace('/href\s*=\s*([\'"])javascript:[^\'"]*\1/i', 'href="#"', $html) ?? $html;

        return $html;
    }

    private function tenantCards(int $limit): string
    {
        $tenants = $this->optedInTenants($limit);

        if (!$tenants) {
            return '<article class="tenant-card empty"><h3>Directory opening soon</h3><p>Opted-in artists will appear here as the platform grows.</p></article>';
        }

        $html = '';
        foreach ($tenants as $tenant) {
            $name = $this->escape((string) ($tenant['display_name'] ?? $tenant['slug'] ?? 'Artist site'));
            $summary = $this->escape((string) ($tenant['summary'] ?? 'Artist portfolio on ArtsFolio.'));
            $href = $this->escape((string) ($tenant['href'] ?? '#'));

            $html .= <<<HTML
<a class="tenant-card" href="{$href}">
    <h3>{$name}</h3>
    <p>{$summary}</p>
    <span>Visit site</span>
</a>
HTML;
        }

        return $html;
    }

    private function imageMosaic(int $limit): string
    {
        $images = $this->optedInImages($limit);

        if (!$images) {
            return <<<HTML
<div class="mosaic-placeholder one"></div>
<div class="mosaic-placeholder two"></div>
<div class="mosaic-placeholder three"></div>
<div class="mosaic-placeholder four"></div>
HTML;
        }

        $html = '';
        foreach ($images as $image) {
            $src = $this->escape((string) $image['src']);
            $alt = $this->escape((string) ($image['alt'] ?? 'Artwork'));
            $href = $this->escape((string) ($image['href'] ?? '#'));
            $html .= "<a href=\"{$href}\"><img src=\"{$src}\" alt=\"{$alt}\" loading=\"lazy\"></a>";
        }

        return $html;
    }

    private function optedInTenants(int $limit): array
    {
        if (!$this->platformDirectoryEnabled()) {
            return [];
        }

        try {
            $settingsTable = $this->settingsTable();
            if ($settingsTable === null) {
                return [];
            }

            // Keep this query schema-aligned with the MariaDB migrations:
            // tenants.name, tenant_domains.hostname, and artworks.primary_media_id.
            // Do not place aliases inside GROUP BY; MariaDB rejects that syntax.
            $sql = "
                SELECT
                    t.id,
                    t.slug,
                    t.name AS display_name,
                    COALESCE(summary.setting_value, '') AS summary,
                    COALESCE(primary_domain.hostname, fallback_domain.hostname, CONCAT(t.slug, '.artsfol.io')) AS domain
                FROM tenants t
                INNER JOIN {$settingsTable} opt
                    ON opt.tenant_id = t.id
                   AND opt.setting_key = 'platform_directory_opt_in'
                   AND LOWER(TRIM(opt.setting_value)) IN ('1', 'true', 'yes', 'on')
                LEFT JOIN {$settingsTable} summary
                    ON summary.tenant_id = t.id
                   AND summary.setting_key = 'platform_directory_summary'
                LEFT JOIN tenant_domains primary_domain
                    ON primary_domain.tenant_id = t.id
                   AND primary_domain.is_primary = TRUE
                   AND primary_domain.status = 'active'
                LEFT JOIN tenant_domains fallback_domain
                    ON fallback_domain.id = (
                        SELECT td.id
                        FROM tenant_domains td
                        WHERE td.tenant_id = t.id
                          AND td.status = 'active'
                        ORDER BY td.is_primary DESC, td.id ASC
                        LIMIT 1
                    )
                WHERE t.status = 'active'
                ORDER BY t.name ASC
                LIMIT :limit
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as &$row) {
                $domain = (string) ($row['domain'] ?? '');
                $row['href'] = str_starts_with($domain, 'http') ? $domain : 'https://' . $domain;
            }

            return $rows;
        } catch (Throwable $e) {
            error_log('ArtsFolio directory tenant query failed: ' . $e->getMessage());
            return [];
        }
    }

    private function optedInImages(int $limit): array
    {
        if (!$this->platformDirectoryEnabled()) {
            return [];
        }

        try {
            $settingsTable = $this->settingsTable();
            if ($settingsTable === null) {
                return [];
            }

            $sql = "
                SELECT
                    a.slug AS artwork_slug,
                    a.title,
                    m.uuid AS media_uuid,
                    COALESCE(primary_domain.hostname, fallback_domain.hostname, CONCAT(t.slug, '.artsfol.io')) AS domain
                FROM tenants t
                INNER JOIN {$settingsTable} opt
                    ON opt.tenant_id = t.id
                   AND opt.setting_key = 'platform_directory_opt_in'
                   AND LOWER(TRIM(opt.setting_value)) IN ('1', 'true', 'yes', 'on')
                INNER JOIN artworks a
                    ON a.tenant_id = t.id
                   AND a.status = 'published'
                INNER JOIN media_assets m
                    ON m.id = a.primary_media_id
                   AND m.is_private = 0
                LEFT JOIN tenant_domains primary_domain
                    ON primary_domain.tenant_id = t.id
                   AND primary_domain.is_primary = TRUE
                   AND primary_domain.status = 'active'
                LEFT JOIN tenant_domains fallback_domain
                    ON fallback_domain.id = (
                        SELECT td.id
                        FROM tenant_domains td
                        WHERE td.tenant_id = t.id
                          AND td.status = 'active'
                        ORDER BY td.is_primary DESC, td.id ASC
                        LIMIT 1
                    )
                WHERE t.status = 'active'
                ORDER BY RAND()
                LIMIT :limit
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $images = [];
            foreach ($rows as $row) {
                $domain = (string) ($row['domain'] ?? '');
                $base = str_starts_with($domain, 'http') ? $domain : 'https://' . $domain;
                $uuid = (string) ($row['media_uuid'] ?? '');
                if ($uuid === '') {
                    continue;
                }

                $images[] = [
                    'src' => $base . '/media?uuid=' . rawurlencode($uuid),
                    'href' => $base . '/artwork/' . rawurlencode((string) ($row['artwork_slug'] ?? '')),
                    'alt' => (string) ($row['title'] ?? 'Artwork'),
                ];
            }

            return $images;
        } catch (Throwable $e) {
            error_log('ArtsFolio directory image query failed: ' . $e->getMessage());
            return [];
        }
    }

    private function platformDirectoryEnabled(): bool
    {
        try {
            $stmt = $this->pdo->prepare("SELECT setting_value FROM platform_settings WHERE setting_key = 'platform_directory_enabled' LIMIT 1");
            $stmt->execute();
            $value = $stmt->fetchColumn();
            if ($value === false) {
                return true;
            }

            return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
        } catch (Throwable) {
            return true;
        }
    }

    private function settingsTable(): ?string
    {
        foreach (['tenant_settings', 'settings'] as $table) {
            try {
                $stmt = $this->pdo->query("SHOW TABLES LIKE " . $this->pdo->quote($table));
                if ($stmt && $stmt->fetchColumn()) {
                    return $table;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private function currentUser(): ?array
    {
        return isset($GLOBALS['artsfolio_current_user']) && is_array($GLOBALS['artsfolio_current_user'])
            ? $GLOBALS['artsfolio_current_user']
            : null;
    }
    /**
     * Format plan admin-user limits for pricing display.
     */
    private function formatAdminUsers(mixed $value): string
    {
        if ($value === null || $value === '' || (int) $value < 0) {
            return 'Unlimited admin users';
        }

        $count = (int) $value;
        return $count === 1 ? '1 admin user' : $count . ' admin users';
    }
}
