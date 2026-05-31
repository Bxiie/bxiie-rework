# Production deploy email smoke behavior

Production deploy preflight validates that email jobs can be queued and rendered. It intentionally skips sending real SMTP messages during deploy so test recipients do not block deployment and no accidental customer email is sent.

If email delivery must be verified, inspect the worker service and recent logs after deployment:

```bash
systemctl status artsfolio-email-worker.service --no-pager
journalctl -u artsfolio-email-worker.service -n 100 --no-pager
```

# End of file.
