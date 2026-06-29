# Tenant Admin

## Tenant custom domains

Tenant admins can manage their own custom domains from Tenant Admin > Domains. They can add a hostname, delete tenant-owned custom domains, and queue DNS verification. The default artsfol.io subdomain is protected from tenant deletion.

## Tenant custom domains on tenant hosts

Tenant admins can open `/admin/domains` on their own tenant domain, including custom domains such as `bxiie.com`, to add domains, delete tenant-owned custom domains, and queue DNS verification.

## Artwork upload acknowledgement

After a successful artwork upload, ArtsFolio redirects back to the branded upload page with a success notice. The upload fields are cleared and ready for the next image, and the uploaded artwork is saved as an unpublished draft unless the tenant default publishes new uploads.

## Site image thumbnail pickers

Admin fields that select site images use thumbnail radio-card pickers instead of blind UUID/dropdown controls. Available choices come from tenant artworks marked with the `Site Images` artwork type, including draft and published site-image assets.

## Site image picker draft images

Site image pickers include draft and published `Site Images` assets. Both draft and published choices show admin thumbnails. Draft choices are also marked `draft: will not show in interface until published.`

<!-- End of file. -->
