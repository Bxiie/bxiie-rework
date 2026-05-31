# ArtsFolio Admin Deployment Notes

A production deploy must clearly report final success or failure. The final banner should be one of:

- `== DEPLOY SUCCEEDED ==`
- `== DEPLOY FAILED ==`

The background worker is required. If `artsfolio-background-worker.service` is missing or inactive, the deploy must fail rather than continue with a warning. Platform admin worker health messages and DNS verification results depend on this worker.

To inspect the worker on production:

```bash
systemctl status artsfolio-background-worker.service --no-pager
journalctl -u artsfolio-background-worker.service -n 100 --no-pager
```

# End of file.
