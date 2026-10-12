<?php
return [
 // Local backup folder. Keep it outside the web root (storage/ is not served).
 'path'=>env('BACKUP_PATH',storage_path('backups')),
 'retention_days'=>(int)env('BACKUP_RETENTION_DAYS',30),
 // Second location: a folder/UNC share (D:\Backups, \\NAS\park) or an rclone remote (remote:folder). Empty disables the copy.
 'copy_to'=>trim((string)env('BACKUP_COPY_TO','')),
 'mysqldump'=>env('BACKUP_MYSQLDUMP','C:\\xampp\\mysql\\bin\\mysqldump.exe'),
 'mysql'=>env('BACKUP_MYSQL','C:\\xampp\\mysql\\bin\\mysql.exe'),
 'rclone'=>env('BACKUP_RCLONE','rclone'),
];
