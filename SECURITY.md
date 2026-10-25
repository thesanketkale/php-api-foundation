# Security Policy

`php-api-foundation` takes the security of its code and its users very seriously. If you believe you have discovered a vulnerability, please read this policy for instructions on reporting it responsibly.

---

## Supported Versions

Only the latest minor release is actively supported with security updates.

| Version | Supported          | Security Fixes Until |
| ------- | ------------------ | -------------------- |
| 0.1.x   | :white_check_mark: | Active               |
| < 0.1   | :x:                | Unsupported          |

---

## Reporting a Vulnerability

**Please do not disclose security vulnerabilities publicly via GitHub Issues, Discussions, or social media.**

To report a vulnerability responsibly:

1. **GitHub Private Vulnerability Advisory (Preferred)**:
   Navigate to the [Security Advisories](https://github.com/thesanketkale/php-api-foundation/security/advisories) tab and click **"Report a vulnerability"**. This allows secure collaboration and discussion in private.
2. **Email Fallback**:
   If you cannot access the GitHub advisory interface, please email details directly to `thesanketkale@users.noreply.github.com` with the subject `[SECURITY VULNERABILITY] php-api-foundation`.

### Information to Include
To help us triage and patch issues quickly, please provide:
- A descriptive summary of the vulnerability.
- Step-by-step reproduction steps or a minimal proof of concept (PoC).
- The affected component, file, or endpoint.
- Any potential impact or attack scenario.
- Suggested mitigations if known.

---

## Response Timeline

- **Acknowledgment**: Within 48 hours of report submission.
- **Triage & Assessment**: Within 5 business days.
- **Fix & Disclosure**: We aim to release a patch and advisory within 14 business days, coordinating disclosure dates with the reporter.

---

## Security Best Practices for Deployments

When running `php-api-foundation` in production:
- **Environment Variables**: Always store `APP_KEY`, `JWT_SECRET`, and database credentials in `.env` or cloud secret managers (AWS Secrets Manager, Azure Key Vault). Never commit secrets to source control.
- **File Permissions**: In shared hosting environments, ensure permissions on `.env` are `0600` or `0400`, and that the web root points strictly to `public/`.
- **HTTPS Enforced**: Ensure all traffic is encrypted with TLS 1.3 or 1.2. The built-in `SecurityHeadersMiddleware` emits `Strict-Transport-Security` headers.
- **Database User**: Grant least-privilege permissions to the database user (`SELECT`, `INSERT`, `UPDATE`, `DELETE`).
