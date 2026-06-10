# Background Jobs

Background jobs perform tenant provisioning and domain maintenance work outside the web request.

Platform admins can review jobs from Platform Admin Jobs. Failed provisioning jobs can be retried after deploying the missing handler or correcting the underlying configuration.

For production operations, the worker should be enabled as `artsfolio-background-worker.service`.

Useful commands:

```bash
systemctl status artsfolio-background-worker.service --no-pager
journalctl -u artsfolio-background-worker.service -n 100 --no-pager
ARTSFOLIO_ENV_FILE=/etc/artsfolio/artsfolio.env php /var/www/artsfolio/scripts/workers/run_once.php
```

A running worker with `No handler for job type` messages means the service is healthy but the application needs handler registration for that job type.

<!-- End of file. -->
