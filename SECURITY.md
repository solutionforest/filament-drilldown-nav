# Security policy

## Supported versions

| Version | Supported |
|---|---|
| latest `0.x` minor | ✅ fixes |
| older `0.x` | ❌ upgrade to the latest minor |

After 1.0, the latest major gets fixes.

## Reporting a vulnerability

**Do not open a public issue.** Report it privately to the maintainers (Solution Forest), using GitHub's private vulnerability reporting ("Security" tab → "Report a vulnerability") where it is enabled for this repository, or through your Solution Forest contact.

Please include: the package and Filament versions, the panel configuration involved, steps to reproduce, and the impact you see. We aim to reply within 5 working days and to ship a fix, with a changelog entry and a release note, as soon as it is confirmed.

## Scope worth knowing

- The plugin renders labels, URLs and badges you give it; it escapes text, but HTML you pass on purpose (an `Htmlable` header, render-hook output) is rendered as is.
- `pinnable(database: true)` stores URLs and labels per user; only paths under the panel (and tenant) are accepted.
- The `fi-drilldown-open` cookie is not encrypted (the browser writes it); it only holds section keys and 0/1 and is validated on read.
- Filament itself has published security advisories for some 5.x versions below 5.8.3. The supported range (`^5.7`) is a compatibility range, not a security recommendation: run a patched Filament.
