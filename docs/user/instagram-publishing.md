# Publishing artwork to Instagram

ArtsFolio can publish artwork to a connected Instagram Creator or Business account on Studio, Professional, and Collective plans.

You need tenant-admin access or an Editor account that has been granted **Publish to social media** permission.

Before the first connection, a tenant administrator configures that tenant's own Meta App ID and App Secret under **Tenant Admin → Instagram → Meta App credentials**. ArtsFolio encrypts the App Secret and never displays it again after saving. The Meta app must register the OAuth Redirect URI shown on the Instagram settings page.

## Start from an artwork

When you have publishing permission, **Post to Instagram** is available from:

- the Artworks list;
- the Edit Artwork page;
- the public artwork page while you are signed in.

Selecting it opens **Instagram Compose**.

## Social Caption and hashtags

The artwork editor includes a **Social Caption** field. Use it when you want social-media wording that differs from the artwork's formal gallery description.

Artwork-specific hashtags can also be saved there. Compose combines them with the account's default hashtags.

Private/Internal Notes are never included in Instagram posts.

## Compose

Instagram Compose starts with the account's configured caption template. Everything in the caption remains editable before submission.

You may select another template, adjust hashtags, and select additional artwork images for a carousel. Images from the same portfolio section are presented before unrelated artwork to make related-image selection faster.

A carousel can contain up to ten images. Use the up/down controls to arrange their order.

Each selected image has a crop preview:

- Original
- Square
- Portrait 4:5
- Landscape 1.91:1

Horizontal and vertical focus sliders let you choose which portion remains visible when cropping. This does not change the original ArtsFolio artwork image.

## Post now

Choose **Post Now**, then confirm the publication. ArtsFolio submits the post through its background publisher immediately rather than making the browser wait for Meta to finish.

You receive an email when publication succeeds. The Instagram history page also stores the Instagram link when Meta supplies it.

## Schedule

Choose the desired date and time and select **Schedule Post**. The displayed time zone is your ArtsFolio time-zone preference.

A scheduled post is a snapshot. Later edits to the artwork do not silently change that scheduled post.

To intentionally modify it, open **Tenant Admin → Instagram**, choose the pending post's **Edit** action, and resubmit it. The saved carousel order and crop focus are restored when you reopen Compose.

## History

**Tenant Admin → Instagram** shows submitted and published posts. Pending posts may be edited or canceled until publishing begins.

Published posts include a link to Instagram when available. ArtsFolio does not delete already-published posts from Instagram.

If a post says **Authorization required**, ask a tenant administrator to reconnect Instagram. If a post reaches **Failed**, its history entry contains the publishing error that needs attention.

<!-- End of file. -->
