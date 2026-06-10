# Comprehensive testing options

ArtsFolio can get broad automated coverage inexpensively by combining existing PHP static/preflight checks with browser tests.

## Recommended low-cost stack

1. Keep the existing PHP static and preflight scripts as the fast gate.
2. Add Playwright for UI coverage. Playwright is free, supports Chromium/Firefox/WebKit, screenshots, traces, and headed or headless runs.
3. Run Playwright locally on the workstation and in GitHub Actions for pull requests. GitHub Actions has a free tier for public repositories and a limited included tier for private repositories.
4. Use one seeded local or staging database, not production, for destructive create/edit/delete flows.
5. Keep production smoke tests read-mostly and route-focused: login, dashboard loads, route status, branding, and core counts.

## Practical test layers

| Layer | Tool | Cost | Purpose |
| --- | --- | --- | --- |
| PHP syntax/static smoke | Existing scripts plus `php -l` | Free | Catch broken PHP, missing routes, route markers, schema/query drift. |
| Backend integration smoke | Project-specific PHP scripts against local/staging DB | Free | Verify repositories, migrations, login/session behavior, dashboard counts. |
| Browser UI tests | Playwright | Free | Verify real pages, forms, nav visibility, branded errors, mobile viewport rendering. |
| Hosted browser grid | BrowserStack/Sauce/LambdaTest only if needed | Paid | Cross-browser/device depth after local Playwright is not enough. |
| Visual regression | Playwright screenshots or Percy/Chromatic | Free to paid | Catch layout/branding regressions. |

## Suggested Playwright scopes

- Anonymous tenant visitor: home, portfolio, artwork detail, contact, email signup, and absence of Admin tab.
- Tenant admin: login failure branding, successful login, dashboard counts, artwork edit, settings save, contact list, email signups, sales analytics.
- Platform admin: login, dashboard counts, tenant detail billing override notice, tenant user password notice, sales analytics, workers warning.
- Mobile: tenant home, portfolio, artwork detail, admin dashboard, and settings page at narrow viewport.

## Guardrails

- Do not run destructive UI tests against production.
- Do not make preflight or browser tests delete real tenants, custom domains, artworks, subscribers, or orders.
- Use deterministic test tenants and explicit test data.
- Store test credentials in local secrets or CI secrets, never in repository documentation.

# End of file.
