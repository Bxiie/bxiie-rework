# Ui Css


## Logo aspect-ratio guard

Shared logo images must not receive fixed width and fixed height together. Use
max-width/max-height, leave width/height as `auto`, and include `object-fit:
contain` for defensive rendering. The regression check is
`scripts/test/logo_aspect_ratio_static.php`.
<!-- End of file. -->
