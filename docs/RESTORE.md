# Backup and restore

## What runs nightly
`scripts\backup-nightly.ps1` runs `php artisan db:backup` at 2 AM. It:
1. dumps the application database with `mysqldump` (consistent snapshot, routines, triggers, events);
2. writes `storage\backups\<database>_<YYYYMMDD_HHMMSS>.sql.gz` (a `.part` file is renamed only after the dump is verified complete);
3. copies it to `BACKUP_COPY_TO` if set;
4. deletes `*.sql.gz` in `storage\backups` older than `BACKUP_RETENTION_DAYS` (default 30).

Output goes to `storage\logs\backup.log`. A non-zero exit means the dump or the copy failed. A failed copy still keeps the local backup.

## Set up
1. In `.env`, set the second location (one value, either kind):
   - folder or network share: `BACKUP_COPY_TO=\\NAS\park-backups` or `BACKUP_COPY_TO=D:\Backups`
   - rclone remote: `BACKUP_COPY_TO=gdrive:ageless-backups` (rclone installed and `rclone config` done as the same Windows user; set `BACKUP_RCLONE` if it is not on PATH).

   Optional: `BACKUP_RETENTION_DAYS`, `BACKUP_PATH`, `BACKUP_MYSQLDUMP`, `BACKUP_MYSQL`.
2. Test once by hand: `php artisan db:backup`
3. Register the schedule (PowerShell, from the project folder):
   ```powershell
   .\scripts\register-backup-task.ps1
   Start-ScheduledTask -TaskName 'Ageless Park - Nightly DB Backup'
   ```
   By default the task runs as you, only while you are logged on, which keeps access to network shares. If the PC must back up while logged off, use `-Credential (Get-Credential)`; the account must be able to reach the share. If the PC is off at 2 AM the task runs when it next starts. Remove it with `-Remove`.

The second-location copy is not pruned; clean it up on the destination side.

## Restore
Always restore into a scratch database first and check it. `db:restore` refuses the live database unless you add `--allow-live`.

1. Pick a file from `storage\backups` (or the second location).
2. Restore into a new name:
   ```
   php artisan db:restore storage\backups\ageless_park_20261012_020001.sql.gz ageless_restore_check
   ```
   The database is created if missing. If it already has tables the command stops; use a new name, or `--force` to load over it.
3. Check the data, for example with phpMyAdmin or:
   `C:\xampp\mysql\bin\mysql.exe -u root -p -e "SELECT COUNT(*) FROM ageless_restore_check.sales"`
4. Drop the scratch database when finished: `DROP DATABASE ageless_restore_check;`

## Recovering the live system
1. Stop the app (close `02-start-windows.bat`, or stop Apache).
2. Take a fresh `php artisan db:backup` of the current state, in case you need it back.
3. Restore the chosen file into a scratch database and verify it (steps above).
4. Either point `DB_DATABASE` in `.env` at the verified scratch database, or restore over the live one:
   `php artisan db:restore <file> ageless_park --allow-live --force`
   Tables in the backup replace tables of the same name; extra tables not in the backup stay.
5. Start the app and run `php artisan park:database-check`.

Backups contain customer, staff and attendance data and password hashes. Keep both locations private. `.env` and `APP_KEY` are not included; back them up separately.
