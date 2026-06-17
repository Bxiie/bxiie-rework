# Platform legal pages

ArtsFolio exposes public legal pages at `/terms` and `/privacy` on the platform host.

Implementation notes:

- Routes are registered in the front controller next to the other public platform routes.
- Legal copy is rendered by `Platform\MarketingController`.
- Platform footers should link to Help, Terms, Privacy, and Contact.
- The privacy page includes data deletion instructions for direct requests, Facebook Login, and Google Login.
- The terms page covers tenant content rights, public/discovery image usage, directory opt-in, sales, fees, shipping, signup codes, acceptable use, suspension, liability, and contact.

This copy should be reviewed by counsel before broad commercial launch.

<!-- End of file. -->
