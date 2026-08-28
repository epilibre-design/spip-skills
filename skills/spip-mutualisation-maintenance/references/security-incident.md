# Security incident and compromise investigation

Preserve evidence before cleanup. The agent may run bounded read-only observations; quarantine, cache purge, updates, secret rotation, and deletion are proposed operator actions only.

Sources: installed code/configuration, exact official [SPIP source](https://git.spip.net/spip/spip) for the installed branch, exact official plugin tags/archives, and current [Mutualisation facile source](https://git.spip.net/spip-contrib-extensions/mutualisation).

## Immediate gate

Do not begin with `find -delete`, `rm`, cache purge, core/plugin update, or “repair permissions.” These actions alter timestamps/content and can destroy the evidence needed to find persistence or the entry point.

If active harm is continuing, propose containment that preserves evidence: remove the affected virtual host from service, restrict access at the reverse proxy/firewall, or snapshot/isolate the host using the operator's existing mechanism. Conditional containment is a state-changing action and therefore needs its own complete action-plan row; do not leave it as a prose instruction. Do not improvise a destructive containment command.

## 1. Open an evidence record

Record in UTC:

- who observed what and when;
- verified shared root and effective mutualisation directory;
- host identity, time synchronization state, running kernel, PHP/web versions;
- affected domains and initial indicators;
- every read-only command and its output hash;
- chain of custody for copied artifacts.

Store evidence outside the web root on protected storage. Do not expose customer data, tokens, credentials, or full connection configuration in the response.

## 2. Bound the impact radius

| Scope | Indicators | Consequence |
|---|---|---|
| Site-local | Files only in one site's writable paths, one admin/content set, no shared changes | Investigate that site, but still test the shared boundary |
| Shared farm | Modified core, shared plugin/template/configuration, multiple sites with same payload | Treat every site loading that code as potentially affected |
| Host | Unexpected accounts/keys, cron/timers, services, processes, system binaries, or outbound connections | Stop treating this as only a SPIP incident; use a host incident-response procedure |

A PHP file under one `IMG/` directory is an indicator, not proof that compromise is site-local.

## 3. Preserve volatile and file evidence

Read-only examples, adapted to the verified host:

```bash
date -u +'%Y-%m-%dT%H:%M:%SZ'
uname -a
ps -eo user,pid,ppid,lstart,cmd --sort=lstart
ss -plant
systemctl list-timers --all
```

System/process output may contain secrets in command arguments. Inspect locally and redact before including it in a report.

Search without following links and without deletion:

```bash
mutu_dir=/srv/www/example-spip/verified-site-directory
incident_since='2026-08-22 22:31:00'
mutu_dir="$(realpath -e -- "$mutu_dir")"
test -n "$mutu_dir" && test "$mutu_dir" != /

find -P "$mutu_dir" -xdev -type f -newermt "$incident_since" \
  -printf '%TY-%Tm-%TdT%TH:%TM:%TS %s %m %u:%g %p\n'
find -P "$mutu_dir" -xdev -type f \
  \( -path '*/IMG/*.php' -o -path '*/local/*.php' -o -path '*/tmp/*.php' \) \
  -print0 | xargs -0 -r stat --printf='%n\t%s\t%a\t%U:%G\t%y\n'
find -P "$mutu_dir" -xdev -type l -printf '%p -> %l\n'
```

Hash identified files without executing them:

```bash
sha256sum -- /verified/path/to/suspicious.php
file --brief --mime -- /verified/path/to/suspicious.php
```

Do not invoke PHP, shell, an antivirus “clean” mode, or application includes on a suspect artifact.

Preserve relevant Nginx/Apache access and error logs, PHP-FPM logs, SPIP logs, authentication/audit records, and database/admin changes covering time before and after the earliest indicator. Copying and hashing evidence are mutations to the evidence store and must be proposed with explicit source/destination, preservation flags, capacity check, and operator approval.

## 4. Compare with trusted exact sources

Identify the installed version before comparison. Use the matching official tag/archive and checksums where available.

### Git-managed code

```bash
git -C "$spip_root" status --short
git -C "$spip_root" diff --no-ext-diff --binary
git -C "$spip_root" fsck --no-dangling
```

Confirm the repository itself and remote are trusted; an attacker can alter Git metadata.

### Archive/SVP or unknown code

Build a clean comparison tree outside production from the exact official release, then compare metadata and contents read-only. Exclude only paths proven to be site data; do not use a broad exclusion that hides unexpected PHP in writable trees.

Apply the same exact-version comparison to shared plugins and site-specific code. A modified file is not automatically malicious; classify expected local changes separately.

## 5. Search for entry point and persistence

Correlate, rather than merely listing files:

- earliest suspicious file timestamp and matching HTTP requests;
- vulnerable SPIP/plugin version and exposed endpoint;
- new or changed SPIP administrators, sessions, configuration, content, and scheduled jobs;
- web-server rewrite/configuration changes;
- cron, systemd timers/services, SSH authorized keys, shell histories where authorized, and unexpected system users;
- outbound connections/processes and other sites touched in the same window.

Do not conclude “clean” because known filenames were removed. If host-level persistence is plausible, recommend isolation/rebuild from trusted media and specialist incident handling.

## 6. Recoverable quarantine proposal

Quarantine precedes deletion. The operator plan must:

1. validate every source path remains under the verified incident root;
2. choose a protected destination outside the document root;
3. preserve relative paths, timestamps, ownership, modes, and extended metadata when available;
4. hash before and after transfer;
5. maintain a manifest and chain of custody;
6. prevent execution/read access by the web worker;
7. verify the known-good replacement before considering permanent deletion.

Never provide a single broad `find ... -delete` command. Prefer a reviewed NUL-delimited file list of previously inventoried artifacts and a recoverable copy/move process. The agent presents the plan; it does not perform quarantine.

## 7. Remediation order

1. Contain ongoing harm.
2. Preserve evidence and complete the impact assessment.
3. Identify the entry point and compromise window.
4. Replace shared/site code from known-good exact sources; restore affected data from a known-good checkpoint when justified.
5. Patch the exploited SPIP/plugin/server weakness.
6. Rotate potentially exposed SPIP admin, database, SSH, SMTP, API, deployment, and backup secrets through their proper systems; invalidate sessions and tokens.
7. Validate every site plus the host boundary.
8. Restore service gradually and monitor logs, hashes, accounts, processes, and outbound connections.

An update is part of remediation only after preservation and scope analysis. It does not prove persistence was removed.

## 8. Incident validation

For each site and the shared host, record:

- exact known-good code identity;
- absence of unexplained executables in writable paths;
- reviewed administrators and authentication/session state;
- content/database checks appropriate to the indicator;
- public/private functional checks;
- clean PHP/web/SPIP logs during a monitored window;
- host persistence checks or an explicit escalation to host rebuild.

Keep evidence under the retention policy even after service is restored. Permanent deletion is a later operator decision, not a skill action.
