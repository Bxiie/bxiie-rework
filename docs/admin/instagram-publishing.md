# Instagram publishing for tenant administrators

Instagram publishing is available to ArtsFolio Studio, Professional, and Collective tenants. It requires an Instagram Creator or Business account.

## Configure the tenant's Meta app

Each ArtsFolio tenant uses its own Meta app credentials. Open **Tenant Admin → Instagram** and configure **Meta App credentials** before connecting Instagram.

Enter the tenant's Meta **App ID** and **App Secret**. ArtsFolio stores the App ID as tenant settings data and encrypts the App Secret before database persistence. The App Secret is never displayed after it is saved; leaving the secret field blank later keeps the existing stored secret.

Register the **OAuth Redirect URI** displayed by ArtsFolio in the tenant's Meta app. The production default is `https://artsfol.io/social/instagram/callback`. All tenant-owned Meta apps may use the same callback because ArtsFolio signs tenant identity into the OAuth state and restores the initiating tenant when Meta returns.

ArtsFolio's platform social encryption and OAuth-state keys remain platform-managed. The Meta App ID and App Secret do not belong in the ArtsFolio server environment.

## Connect Instagram

After the tenant's Meta app credentials are saved, choose **Connect Instagram**. Complete Meta's authorization flow for the professional Instagram account you want ArtsFolio to publish to.

ArtsFolio does not ask for or store the Instagram password. The connection may require reauthorization later if Meta expires or revokes the access token.

Disconnecting the account preserves publishing history. Pending posts are moved to an authorization-required state until the account is reconnected.

## Default hashtags

The Instagram settings page has a tenant-wide default hashtag field. ArtsFolio normalizes hashtag text and combines tenant defaults with artwork-specific hashtags when Compose is opened.

Artwork-specific hashtags and **Social Caption** are edited from the artwork editor. Internal artwork Notes are never included in an Instagram template.

## Caption templates

ArtsFolio creates one editable Default template. You may add additional named templates, set a different tenant default, and assign a template to a portfolio section.

If an artwork belongs to one section with an assigned template, Compose starts from that template. If an artwork belongs to several sections, ArtsFolio does not guess which section wins. It uses the tenant default and lets the publisher explicitly select a template.

Useful placeholders include artist name, artwork title, year, medium, dimensions, description, Social Caption, artwork URL, site URL, portfolio URL, section names, price, availability, copyright, and hashtag groups. The settings page displays the current complete placeholder list.

## Grant an editor permission

Tenant owners and administrators can publish automatically. Editors do not receive publishing access by default.

On **Tenant Admin → Users**, an Editor row includes **Publish to social media**. Select that checkbox to grant the editor permission to compose, schedule, edit pending posts, cancel pending posts, and view Instagram history. Clear it to remove the capability.

An editor with this permission also receives Instagram publication-success and final-failure emails. Other ordinary tenant users do not.

## Compose a post

Instagram actions appear on the Artworks list, Edit Artwork page, and public artwork page for a signed-in user who has publishing permission.

Compose provides:

- an editable caption generated from the selected template;
- combined default and artwork-specific hashtags;
- image selection across the tenant's artwork library;
- same-portfolio-section images listed first;
- carousel ordering up to ten images;
- Original, Square, Portrait, and Landscape crop previews;
- horizontal and vertical crop focus controls;
- Post Now with confirmation;
- exact scheduled date/time in the signed-in ArtsFolio user's configured time zone.

Changing the crop does not modify the original artwork file. ArtsFolio creates a separate JPEG publishing derivative.

## Scheduled-post behavior

A scheduled post is a snapshot. If the artwork title, image, description, Social Caption, template, or hashtags are changed later, the already-scheduled post does not silently change.

Choose **Edit** from Instagram history when you intentionally want to change a pending post. Reopening it restores its saved carousel order and crop focus. Pending posts may be edited, rescheduled, posted immediately, or canceled until background publishing begins.

Once a post is published, ArtsFolio keeps the history record and links to Instagram. ArtsFolio does not delete published posts from Instagram.

## Statuses

Common statuses are:

- **Scheduled**: waiting for its publication time.
- **Publishing**: claimed by a background worker and no longer editable.
- **Published**: Instagram publication was confirmed.
- **Failed**: the provider rejected the publication or a non-recoverable failure occurred.
- **Authorization required**: reconnect the Instagram account.
- **Cancelled**: an ArtsFolio user canceled the pending post.

Transient Meta rate-limit and server failures may retry automatically before the post reaches final Failed status.

## Notifications

ArtsFolio queues email when a post is successfully published and when a final failure requires attention. Recipients are tenant owners/admins plus editors who have **Publish to social media** permission.

The success message includes the Instagram result/permalink when available. Failure notices include a concise reason and a link back to the Instagram history area.

## If publishing stops

Check **Tenant Admin → Instagram** first. A single account showing authorization problems normally needs reconnection rather than a server restart. If OAuth cannot start, verify that tenant's Meta App ID, App Secret, and registered callback URI.

If several tenants are affected at once, a platform administrator should verify the ArtsFolio background workers, platform social encryption/state keys, callback configuration, and queue state. Tenant Meta App credentials should not be shared globally.

<!-- End of file. -->
