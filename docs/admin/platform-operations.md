# Platform Operations


## Logo rendering review

Platform pages use the shared ArtsFolio illustrated logo in headers, auth cards,
help pages, directory/pricing pages, platform admin, and branded error pages. The
CSS now constrains logo images with max-width/max-height and `object-fit:
contain` while leaving `width` and `height` on `auto`, so admins should not see
stretched or squeezed logos when page headers change size.
<!-- End of file. -->
