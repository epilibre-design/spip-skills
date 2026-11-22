# SPIP 4.4.31 — verifications

Date: 2026-11-22

Each result says whether it comes from a **run** on the instance or a **source read**.

## Instance

- `/src/jetables/v4.4.31` (former `v4.4.30`, upgraded 2026-11-22; backup
  `/src/jetables/v4.4.30-sauvegarde-2026-11-22.tgz`): `spip/spip` `62485d649fcb7abba91ced4b768eaa3afe621ba6`
  (tag 4.4.31), `spip/ecrire` 4.4.31 `5d7078d2`, `spip/prive` 1.1.10 `693c1396`, `spip/dist` 4.2.10 `c2beb84a`,
  `spip/security` 1.6.7 `12bb638d`, PHP 8.4.28 (built-in server on `http://127.0.0.1:18931`), SQLite.
- plugins-dist (tags): aide 3.2.9, archiviste 2.2.7, bigup 3.3.15, compagnon 3.3.2, compresseur 2.2.5, dump 2.2.5,
  filtres_images 4.3.12, forum 3.1.20, mediabox 3.2.2, medias 4.4.18, mots 4.3.3, plan 4.1.8, porte_plume 3.1.15,
  revisions 3.1.15, safehtml 3.2.3, sites 4.4.9, statistiques 3.2.3, svp 3.2.8, textwheel 3.4.1, urls_etendues 4.2.6.
- Core schema `2026_09_24_00`; no database upgrade from 4.4.30.
- Survey: `spip_loader.api` latest 4.4 = 4.4.31 on 2026-11-22.

## Runs

| Question | Result | How |
|---|---|---|
| Instance serves 4.4.31 | `/` 200, `?page=plan` 200, `?page=backend` 200, `/ecrire/` 302; generator `SPIP 4.4.31` | `curl` on 2026-11-22 |

## Source reads

| Question | Result | File |
|---|---|---|
