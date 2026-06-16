# Duplicate email signup notifications

ArtsFolio does not send another tenant-admin notification when someone submits an email-list signup form for an address that is already active on that tenant's list.

Active addresses are those with consent status `pending` or `confirmed`. The submission still returns the normal public success response so the form does not reveal whether the address was already on the list.

If an address was previously unsubscribed, a later public signup may reactivate it as pending and send a notification.

<!-- End of file. -->
