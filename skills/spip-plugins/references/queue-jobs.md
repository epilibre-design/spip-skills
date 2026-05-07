# Queue / Jobs — Asynchronous processing with spip_jobs

SPIP's job queue defers work that should not block the current HTTP request. Jobs are stored in
`spip_jobs`, executed in the background on subsequent requests via the cron mechanism, and
optionally linked to editorial objects via `spip_jobs_liens`.

Source: `ecrire/inc/queue.php`

---

## Adding a job

```php
// ecrire/inc/queue.php:56
$id_job = queue_add_job(
    $function,      // string: PHP function name to call
    $description,   // string: human-readable label (shown in admin)
    $arguments,     // array: arguments passed to $function (serialised)
    $file,          // string: file to include before calling $function
                    //   '' = function already loaded
                    //   'inc/foo' = include_spip('inc/foo')
                    //   'genie/' = charger_fonction($function, 'genie')
    $no_duplicate,  // bool|'function_only': skip if identical job exists
                    //   true = match on function + args + file
                    //   'function_only' = match on function name only
    $time,          // int: Unix timestamp when to run (0 = ASAP)
    $priority       // int: -10 (low) to +10 (high), default 0
);
// Returns: (int) id_job on success or existing id if deduplicated
```

### Minimal example

```php
include_spip('inc/queue');

queue_add_job(
    'myplugin_envoyer_email',
    'Send welcome email',
    [$id_auteur],
    'inc/myplugin_email',   // include_spip('inc/myplugin_email') before calling
    true,                   // no duplicate: only one pending email per auteur
    0,                      // ASAP
    0                       // default priority
);
```

The function called by the queue receives the `$arguments` array spread as positional arguments:

```php
// inc/myplugin_email.php
function myplugin_envoyer_email($id_auteur) {
    // runs asynchronously
}
```

### Scheduled (future) job

```php
queue_add_job(
    'myplugin_rappel',
    'Send reminder in 7 days',
    [$id_objet, 'rappel'],
    'inc/myplugin_rappel',
    false,
    time() + 7 * 24 * 3600,  // 7 days from now
    -5                         // low priority
);
```

---

## Removing a job

```php
// ecrire/inc/queue.php:147
queue_remove_job($id_job);
// Returns: int|bool — result of sql_delete
```

If the removed job was a periodic cron task (stored in `genie/`), it is automatically
rescheduled at its normal period.

---

## Linking a job to editorial objects

Linking lets the admin UI show which object a job is associated with, and lets the queue
be purged by object if needed.

```php
// ecrire/inc/queue.php:198
queue_link_job($id_job, ['objet' => 'article', 'id_objet' => 42]);

// Link to multiple objects at once
queue_link_job($id_job, [
    ['objet' => 'article',  'id_objet' => 42],
    ['objet' => 'rubrique', 'id_objet' => 5],
]);

// Remove links
queue_unlink_job($id_job);
```

---

## `spip_jobs` table schema

| Column | Type | Meaning |
|---|---|---|
| `id_job` | `bigint(21) NOT NULL` | Primary key |
| `fonction` | `varchar(255)` | PHP function name |
| `descriptif` | `text` | Human-readable label |
| `args` | `longtext` | Serialised arguments array |
| `md5args` | `varchar(32)` | MD5 of `args` (used for deduplication) |
| `inclure` | `varchar(255)` | File to include before calling the function |
| `priorite` | `tinyint` | -10 to +10 |
| `date` | `datetime` | Scheduled execution time |
| `status` | `tinyint` | `1` = scheduled (`_JQ_SCHEDULED`), `0` = running (`_JQ_PENDING`) |

---

## Status constants

```php
define('_JQ_SCHEDULED', 1);  // job waiting to be executed
define('_JQ_PENDING',   0);  // job currently executing (lock)
```

A job in `_JQ_PENDING` state for more than 180 seconds is considered dead and forcibly closed
by `queue_update_next_job_time()`.

---

## Execution model

Jobs are executed during `queue_schedule()`, which is called on every HTTP request. The
scheduler:

1. Skips if the next scheduled job is still in the future
2. Acquires a lock (delete the row, re-insert with `status = _JQ_PENDING`) to prevent
   concurrent execution
3. Calls `queue_start_job()` — deserialises args, includes the file, calls the function
4. On completion: calls `queue_close_job()` — purges the job, reschedules if periodic

**Fallback:** if `sql_insertq('spip_jobs', ...)` fails (table missing, upgrade in progress),
the job function is called **synchronously** in the current request.

### Execution limits

| Constant | Default | Meaning |
|---|---|---|
| `_JQ_MAX_JOBS_EXECUTE` | `200` | Max jobs per `queue_schedule()` call |
| `_JQ_MAX_JOBS_TIME_TO_EXECUTE` | `min(15, max_exec/2)` | Max seconds per call |
| `_JQ_NB_JOBS_OVERFLOW` | `10000` | Job count above which cron is forced on shutdown |

Override by defining these constants before the scheduler runs (e.g. in `config/mes_options.php`).

---

## Periodic tasks (genie)

Cron tasks declared in `paquet.xml` via `<genie>` are also stored in `spip_jobs`. They are
automatically rescheduled after execution. To declare a periodic task:

```xml
<!-- paquet.xml -->
<genie nom="myplugin_maintenance" periode="86400" inclure="genie/myplugin_maintenance.php" />
```

```php
// genie/myplugin_maintenance.php
function genie_myplugin_maintenance_dist($t) {
    // $t = timestamp of last execution
    // return > 0 to reschedule normally
    // return < 0 to reschedule sooner (|return| = delay in seconds)
    return 1;
}
```

---

## Invariants

- `queue_add_job` returns the existing `id_job` (not 0) when `$no_duplicate` prevents insertion
- Job functions receive `$arguments` as a spread — pass positional args, not a single array
- The queue is entirely driven by HTTP traffic; if the site has no visits, jobs will not run
  until the next request (or until `?action=cron` is hit directly)
- `spip_jobs_liens` rows are purged when the linked job is deleted

---

## See also

- `references/pipelines.md` → `taches_generales` pipeline (to add cron tasks programmatically)
- `references/actions.md` → `?action=cron` and `?action=forcer_job`
- `references/paquet-xml.md` → `<genie>` element
