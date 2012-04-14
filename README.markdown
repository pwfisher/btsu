#BTSU!

A set of command line programs that make backing up to and restoring from Amazon's S3 easy and scriptable.

##Features

Supports simple backup "modules" that can be easily built. Currently includes the modules "files" (for backing up individual files or entire directories) and "mysql" (for backing up individual MySQL databases, or all databases). A PostgreSQL module will be coming soon (because I need it shortly).

Supports automatic pruning (deleting) of old backups based on reasonably complex rules, so you can (for example) keep daily backups for a month, weekly backups for a year, and monthly backups forever. This is the reason I built these scripts, there are plenty of scripts to push tar files to S3, but nothing to prune based on the rules I wanted to use.

Supports multiple backup sets, each with it's own retention rules. So you can keep daily backups of your MySQL data for a year, but only keep your Apache logs backed up for a month. Another feature I was unable to find in other S3 backup scripts.

Supports large file backups (many GB) to S3 by breaking up the large file into smaller pieces (the pieces are reassembled by S3 once the upload is complete).

Optionally supports different buckets and even different access ID's per backup set, so you can store your Apache logs in one bucket and your MySQL data in a different one.

Includes an easy restore script, to list or restore any backup set or all backups for a date. Restore dumps the backed up tar and gzip files to a local folder. You can also of course download the files directly from Amazon.

Backups are stored in standard file formats (eg: tar and gzip) so they're easy to get at - no proprietary file formats to deal with.

##Usage

./s3backup backs up one or all of the backup sets defined in your s3backup.ini config file. Type ./s3backup --help for options.

./s3restore restores the backups from S3 to a local folder. Type ./s3restore --help for options.

s3backup.ini is the config file that stores all your settings and defines the backup sets. See s3backup-sample.ini for documentation on all the variables available in this file and how to setup your own backup sets. Both backup and restore scripts expect to find s3backup.ini in your home directory. If you wish to keep it somewhere else you must specify --conf=/path/to/s3backup.ini when running them.

##Building your own backup modules

This script is currently supplied with files and MySQL modules. You can build your own if you like, to support different types of backups. See the files in the "modules" directory for examples. Basically you get passed a path to a file and a backup set name, and your job is to populate that file with useful data. The main scripts will take care of uploading, restoring, and automatically pruning that data.

##Requirements

Requires PHP 5.2.1 with the curl module enabled (pretty standard stuff). Does not require any external libraries (Amazon's Python libraries for example, are not required).

##Warning

At the moment BTSU is very alpha code. You should not use it on production servers without monitoring it very closely and having a second backup plan. You can, however, run it with the --nodelete option, which prevents the scripts from pruning (but still tells you what should have been removed). Doing this should keep your S3 data safe in the event there are any bugs in the pruning process. I of course take no responsibility for anything the script does (or doesn't do) to your data. Please use with extreme caution at least until it's a little more stable.
