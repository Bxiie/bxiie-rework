# Platform email settings

Platform email delivery is configured from Platform Admin > Platform Settings > Email delivery.

The Postmark message stream field is stored as `smtp_x_pm_message_stream` in `platform_settings`. When set, outbound SMTP messages include this header:

```text
X-PM-Message-Stream: <configured value>
```

Use Postmark stream values such as `outbound` or `broadcasts` according to the stream configured in Postmark.

<!-- End of file. -->
